<?php

namespace App\Services;

use App\Models\PaymentSubmission;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class AmisReceiptRiskService
{
    public function __construct(private readonly ReceiptFingerprintService $fingerprints)
    {
    }

    /** @return array{status:string, flags:array<int, array{code:string,severity:string,message:string}>} */
    public function assess(array $context): array
    {
        $flags = [];
        $add = function (string $code, string $severity, string $message) use (&$flags): void {
            if (!collect($flags)->contains('code', $code)) {
                $flags[] = compact('code', 'severity', 'message');
            }
        };

        /** @var CarbonInterface $transactionAt */
        $transactionAt = $context['transaction_at'];
        /** @var CarbonInterface $now */
        $now = $context['now'];
        if ($transactionAt->gt($now)) {
            $add('INVALID_DATE', 'block', 'The transaction date is in the future.');
        } elseif ($transactionAt->lt($now->copy()->subDays(90))) {
            $add('RECEIPT_EXPIRED', 'review', 'The transaction date is older than 90 days. Finance must review it.');
        }

        if (empty($context['reference']) || empty($context['transaction_date']) || empty($context['entered_amount'])) {
            $add('REQUIRED_DETAILS_MISSING', 'review', 'Reference number, amount, or transaction date is missing.');
        }

        $reference = Str::of((string) ($context['reference'] ?? ''))->upper()->replaceMatches('/[^A-Z0-9]+/', '')->value();
        $format = match ($context['payment_mode'] ?? '') {
            'gcash' => '/^\d{13}$/',
            'maya' => '/^[A-Z0-9]{8,24}$/',
            default => '/^[A-Z0-9]{6,30}$/',
        };
        if ($reference !== '' && !preg_match($format, $reference)) {
            $add('INVALID_REFERENCE_FORMAT', 'review', 'The reference format does not look typical for the selected payment mode.');
        }

        $query = PaymentSubmission::query();
        if (!empty($context['ignore_submission_id'])) {
            $query->where('id', '!=', $context['ignore_submission_id']);
        }
        if ($reference !== '' && (clone $query)->where('reference_normalized', Str::lower($reference))->exists()) {
            $add('REFERENCE_ALREADY_USED', 'block', 'This reference number was already used by another payment.');
        }
        if (!empty($context['receipt_hash']) && (clone $query)->where('receipt_hash', $context['receipt_hash'])->exists()) {
            $add('DUPLICATE_RECEIPT', 'block', 'This exact receipt image was already submitted.');
        }

        if (!empty($context['perceptual_hash'])) {
            $similar = (clone $query)->whereNotNull('perceptual_hash')->pluck('perceptual_hash');
            if ($similar->contains(fn ($hash) => $this->fingerprints->hammingDistance($context['perceptual_hash'], $hash) <= 7)) {
                $add('POSSIBLE_REUSED_RECEIPT', 'review', 'A visually similar receipt was submitted before. Finance must review it.');
            }
        }

        $confidence = $context['ocr_confidence'] ?? null;
        $highConfidence = $confidence !== null && (float) $confidence >= 0.75;
        if ($confidence !== null && (float) $confidence < 0.55) {
            $add('MANUAL_REVIEW', 'review', 'OCR confidence remained low after the automatic reading passes.');
        }

        $detectedAmount = $context['detected_amount'] ?? null;
        if ($detectedAmount !== null
            && (int) round((float) $detectedAmount * 100) !== (int) round((float) $context['entered_amount'] * 100)) {
            $add('AMOUNT_MISMATCH', 'review', 'The detected receipt amount differs from the entered amount. Finance must resolve the disagreement.');
        }

        $detectedMode = strtolower((string) ($context['detected_method'] ?? ''));
        $selectedMode = strtolower((string) ($context['payment_mode'] ?? ''));
        $modeMatches = $detectedMode === '' || $detectedMode === $selectedMode
            || ($detectedMode === 'bdo' && in_array($selectedMode, ['bdo_online', 'bdo_otc'], true));
        if (!$modeMatches) {
            $add('CHANNEL_MISMATCH', 'review', 'The detected payment mode differs from the selected mode. Finance must resolve the disagreement.');
        }

        $officialAccounts = collect(config('finance.payment_channels', []))
            ->flatMap(fn ($channel) => collect($channel['accounts'] ?? [])->pluck('number'))
            ->map(fn ($number) => preg_replace('/\D+/', '', (string) $number));
        $detectedAccount = preg_replace('/\D+/', '', (string) ($context['detected_account'] ?? ''));
        if ($detectedAccount !== '' && !$officialAccounts->contains($detectedAccount)) {
            $add('INVALID_RECIPIENT', 'review', 'The detected recipient account is not an official AMIS account. Finance must verify the beneficiary.');
        }

        if (!empty($context['billing_start']) && $transactionAt->lt($context['billing_start']->copy()->subDay())) {
            $add('DATE_PERIOD_MISMATCH', 'review', 'The payment date is earlier than the selected billing month.');
        }
        if (!empty($context['possible_crop'])) {
            $add('RECEIPT_CROPPED', 'review', 'Important receipt fields may be cropped or missing.');
        }
        if (!empty($context['possible_tampering'])) {
            $add('POSSIBLE_TAMPERING', 'review', 'The image metadata indicates possible editing.');
        }

        $status = collect($flags)->contains('severity', 'block')
            ? 'blocked'
            : (count($flags) ? 'manual_review' : 'clear');

        return compact('status', 'flags');
    }
}
