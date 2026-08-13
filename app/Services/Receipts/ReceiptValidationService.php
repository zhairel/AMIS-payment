<?php

namespace App\Services\Receipts;

use Carbon\Carbon;

class ReceiptValidationService
{
    public function validate(array $fields, array $uncertainFields = []): array
    {
        $warnings = [];
        $errors = [];
        $add = static function (array &$target, string $field, string $code, string $message): void {
            $target[] = compact('field', 'code', 'message');
        };

        if (empty($fields['normalized_reference'])) {
            $add($errors, 'reference_number', 'REFERENCE_MISSING', 'Transaction or reference number could not be read reliably.');
        }
        if (in_array('reference_number', $uncertainFields, true)) {
            $add($errors, 'reference_number', 'REFERENCE_UNCERTAIN', 'OCR engines disagree on the transaction/reference number.');
        }
        if (! is_numeric($fields['amount'] ?? null) || (float) $fields['amount'] <= 0) {
            $add($errors, 'amount', 'AMOUNT_INVALID', 'A valid amount greater than zero is required.');
        }
        if (in_array('amount', $uncertainFields, true)) {
            $add($errors, 'amount', 'AMOUNT_UNCERTAIN', 'OCR engines disagree on the amount.');
        }
        if (! in_array($fields['currency'] ?? null, ReceiptFieldNormalizer::CURRENCIES, true)) {
            $add($warnings, 'currency', 'CURRENCY_UNRECOGNIZED', 'Currency was not recognized.');
        }
        if (empty($fields['transaction_date'])) {
            $add($errors, 'transaction_date', 'DATE_MISSING', 'Transaction date could not be determined.');
        } else {
            try {
                $date = Carbon::parse($fields['transaction_date'], 'Asia/Manila');
                $today = Carbon::now('Asia/Manila');
                if ($date->year > $today->year) {
                    $add($errors, 'transaction_date', 'DATE_YEAR_IN_FUTURE', "Transaction year cannot be later than {$today->year}.");
                } elseif ($date->gt($today)) {
                    $add($warnings, 'transaction_date', 'DATE_LATER_CURRENT_YEAR', 'Transaction date is later in the current year and requires Finance confirmation.');
                }
                if ($date->lt(now()->subYears(3))) {
                    $add($warnings, 'transaction_date', 'DATE_OLD', 'Transaction date is unusually old.');
                }
            } catch (\Throwable) {
                $add($errors, 'transaction_date', 'DATE_INVALID', 'Transaction date is invalid.');
            }
        }
        if (preg_match('/failed|cancelled|canceled|reversed|declined|unsuccessful/i', (string) ($fields['transaction_status'] ?? ''))) {
            $add($errors, 'transaction_status', 'TRANSACTION_NOT_SUCCESSFUL', 'Receipt indicates an unsuccessful or reversed transaction.');
        }

        return [
            'valid' => count($errors) === 0,
            'requires_review' => count($errors) > 0 || count($warnings) > 0,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function needsFallback(array $fields, ?float $confidence, array $validation, array $context = []): bool
    {
        $provider = (string) ($fields['provider'] ?? '');
        $reference = (string) ($fields['reference_number'] ?? '');
        $rawText = trim((string) ($context['raw_text'] ?? ''));
        $threshold = function_exists('app') && app()->bound('config')
            ? (float) config('services.receipt_ocr.confidence_threshold', .72)
            : .72;

        return $provider === ''
            || $provider === 'Other / Unknown'
            || empty($fields['normalized_reference'])
            || $this->referenceLooksSuspicious($reference)
            || empty($fields['amount'])
            || empty($fields['transaction_date'])
            || empty($fields['transaction_status'])
            || $confidence === null
            || $confidence < $threshold
            || ! ($validation['valid'] ?? false)
            || $this->amountLooksSuspicious($fields, $rawText)
            || $this->textLooksNoisy($rawText)
            || in_array($context['blur_status'] ?? null, ['BLURRY', 'SEVERELY_BLURRY'], true)
            // Bright white receipt screenshots can look like glare to the
            // image heuristic. Only camera photos should trigger the slower
            // docTR fallback for glare; screenshots with complete, reliable
            // Tesseract fields stay on the fast path.
            || ((string) ($context['image_type'] ?? '') === 'CAMERA_PHOTO'
                && (bool) ($context['glare_detected'] ?? false));
    }

    private function referenceLooksSuspicious(string $reference): bool
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $reference));
        if (strlen($normalized) < 6 || strlen($normalized) > 36 || ! preg_match('/\d/', $normalized)) {
            return true;
        }

        return in_array($normalized, ReceiptFieldNormalizer::EXCLUDED_WORDS, true)
            || in_array($normalized, ['REQUESTED', 'FOLLOWING', 'OFFICIAL', 'RECEIPT', 'PAYMENT'], true);
    }

    private function amountLooksSuspicious(array $fields, string $rawText): bool
    {
        $label = strtolower((string) data_get($fields, 'fields.amount.matched_label', ''));
        if (preg_match('/fee|vat|tax|discount|balance|service charge|exchange rate/', $label)) {
            return true;
        }

        return in_array($label, ['currency pattern match', 'pre-extracted amount'], true)
            && preg_match('/\b(?:fee|vat|service\s+charge|exchange\s+rate)\b/i', $rawText);
    }

    private function textLooksNoisy(string $text): bool
    {
        if ($text === '') {
            return true;
        }
        if (mb_strlen($text) < 24) {
            return true;
        }

        $compact = preg_replace('/\s+/u', '', $text);
        if ($compact === '') {
            return true;
        }
        $semanticCharacters = preg_match_all('/[\pL\pN.,:₱$\-]/u', $compact);

        return ($semanticCharacters / max(1, mb_strlen($compact))) < .55;
    }
}
