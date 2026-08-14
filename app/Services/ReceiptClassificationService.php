<?php

namespace App\Services;

class ReceiptClassificationService
{
    /**
     * Classify OCR output without treating an OCR failure as a failed payment.
     * Clearly identified reminders, SOAs, and fee schedules are blocked.
     */
    public function classify(array $ocr): array
    {
        $text = mb_strtolower((string) ($ocr['raw_text'] ?? ''));

        if ($text === '') {
            return [
                'type' => 'uncertain',
                'score' => 0,
                'message' => 'Receipt text was not detected; Finance will manually verify it.',
            ];
        }

        $nonReceiptPhrases = [
            'statement of account',
            'schedule of fees',
            'tuition fees',
            'monthly installments',
            'payment reminder',
            'monthly payment is due',
            'payment is due soon',
            'due soon',
            'required payment monthly',
            'due monthly payment',
            'total amount to pay',
            'discount status',
            'grade level',
        ];
        $nonReceiptHits = collect($nonReceiptPhrases)
            ->filter(fn (string $phrase) => str_contains($text, $phrase))
            ->count();

        $isPaymentReminder = str_contains($text, 'reminder')
            && (str_contains($text, 'monthly payment')
                || str_contains($text, 'payment is due')
                || str_contains($text, 'due soon')
                || str_contains($text, 'have not yet settled'));

        if ($isPaymentReminder || str_contains($text, 'statement of account') || str_contains($text, 'schedule of fees') || $nonReceiptHits >= 2) {
            return [
                'type' => 'not_receipt',
                'score' => -10,
                'message' => 'This looks like a payment reminder, statement of account, or fee document—not proof of a completed payment.',
            ];
        }

        $receiptPhrases = [
            'successful',
            'successfully',
            'transaction details',
            'reference number',
            'transaction number',
            'amount sent',
            'amount paid',
            'paid to',
            'sent to',
            'transfer complete',
            'payment received',
        ];

        $receiptPhraseHits = collect($receiptPhrases)
            ->filter(fn (string $phrase) => str_contains($text, $phrase))
            ->count();
        $coreFieldCount = collect([
            $ocr['detected_method'] ?? null,
            $ocr['detected_ref'] ?? null,
            $ocr['detected_amount'] ?? null,
            $ocr['detected_datetime'] ?? null,
        ])->filter(fn ($value) => $value !== null && $value !== '')->count();
        $wordCount = preg_match_all('/[a-z]{3,}/i', $text);

        if ($wordCount >= 2 && $receiptPhraseHits === 0 && $coreFieldCount < 2) {
            return [
                'type' => 'not_receipt',
                'score' => -5,
                'message' => 'This image does not appear to be a payment receipt. Upload the actual payment confirmation.',
            ];
        }

        $score = $receiptPhraseHits * 2;
        $score += !empty($ocr['detected_method']) ? 2 : 0;
        $score += !empty($ocr['detected_ref']) ? 2 : 0;
        $score += isset($ocr['detected_amount']) && $ocr['detected_amount'] !== null ? 2 : 0;
        $score += !empty($ocr['detected_datetime']) ? 1 : 0;

        if ($score >= 5) {
            return [
                'type' => 'receipt',
                'score' => $score,
                'message' => 'Payment receipt detected. Please double-check the auto-filled details.',
            ];
        }

        return [
            'type' => 'uncertain',
            'score' => $score,
            'message' => 'Some receipt details could not be confirmed; Finance will manually verify them.',
        ];
    }
}
