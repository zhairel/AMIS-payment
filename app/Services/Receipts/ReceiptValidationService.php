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
                $date = Carbon::parse($fields['transaction_date']);
                if ($date->isFuture()) {
                    $add($errors, 'transaction_date', 'DATE_IN_FUTURE', 'Transaction date cannot be in the future.');
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

    public function needsFallback(array $fields, ?float $confidence, array $validation): bool
    {
        return empty($fields['normalized_reference'])
            || empty($fields['amount'])
            || empty($fields['transaction_date'])
            || $confidence === null
            || $confidence < .72
            || ! ($validation['valid'] ?? false)
            || preg_match('/\b[A-Z0-9]*[OISB][A-Z0-9]*\b/', (string) ($fields['reference_number'] ?? ''));
    }
}
