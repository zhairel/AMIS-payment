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
            'reference no',
            'ref no',
            'ref. no',
            'amount sent',
            'amount paid',
            'paid to',
            'sent to',
            'express send',
            'send money',
            'transfer complete',
            'payment received',
            'gcash',
            'maya',
            'bdo',
            'instapay',
            'pesonet',
        ];

        $receiptPhraseHits = collect($receiptPhrases)
            ->filter(fn (string $phrase) => str_contains($text, $phrase))
            ->count();

        $detectedRef = $ocr['detected_ref'] ?? $ocr['reference_number'] ?? null;
        $detectedAmount = $ocr['detected_amount'] ?? $ocr['amount'] ?? null;
        $detectedMethod = $ocr['detected_method'] ?? $ocr['provider'] ?? null;
        $detectedDate = $ocr['detected_datetime'] ?? $ocr['transaction_date'] ?? null;

        $coreFieldCount = collect([$detectedMethod, $detectedRef, $detectedAmount, $detectedDate])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->count();

        $wordCount = preg_match_all('/[a-z]{3,}/i', $text);

        // If it's an unrelated screenshot with words and zero financial/receipt keywords and zero detected fields:
        if ($wordCount >= 3 && $receiptPhraseHits === 0 && $coreFieldCount === 0 && !str_contains($text, 'php') && !str_contains($text, '₱')) {
            return [
                'type' => 'not_receipt',
                'score' => -5,
                'message' => 'This image does not appear to be a payment receipt. Upload the actual payment confirmation.',
            ];
        }

        $score = $receiptPhraseHits * 2;
        $score += !empty($detectedMethod) ? 2 : 0;
        $score += !empty($detectedRef) ? 2 : 0;
        $score += ($detectedAmount !== null) ? 2 : 0;
        $score += !empty($detectedDate) ? 1 : 0;

        if ($score >= 3 || $coreFieldCount >= 1 || $receiptPhraseHits >= 1) {
            return [
                'type' => 'receipt',
                'score' => max(5, $score),
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
