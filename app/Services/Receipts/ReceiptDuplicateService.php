<?php

namespace App\Services\Receipts;

use App\Models\PaymentSubmission;
use App\Models\ReceiptSubmission;

class ReceiptDuplicateService
{
    public function check(ReceiptSubmission $receipt): array
    {
        $matches = [];
        $status = 'UNIQUE';
        $linkedPaymentId = $receipt->paymentSubmission()->value('id');

        $exactReceipt = ReceiptSubmission::query()
            ->whereHas('paymentSubmission')
            ->where('id', '!=', $receipt->id)
            ->where('receipt_hash', $receipt->receipt_hash)
            ->first();
        $legacyReceipt = PaymentSubmission::query()
            ->when($linkedPaymentId, fn ($query) => $query->whereKeyNot($linkedPaymentId))
            ->where('receipt_hash', $receipt->receipt_hash)
            ->first();
        if ($exactReceipt || $legacyReceipt) {
            $status = 'EXACT_DUPLICATE';
            $matches[] = ['type' => 'file_hash', 'receipt_submission_id' => $exactReceipt?->submission_id, 'payment_submission_id' => $legacyReceipt?->id];
        }

        if ($receipt->normalized_reference) {
            $referenceMatch = ReceiptSubmission::query()
                ->whereHas('paymentSubmission')
                ->where('id', '!=', $receipt->id)
                ->where('normalized_reference', $receipt->normalized_reference)
                ->when($receipt->provider, fn ($q) => $q->where('provider', $receipt->provider))
                ->first();
            $legacyReference = PaymentSubmission::query()
                ->when($linkedPaymentId, fn ($query) => $query->whereKeyNot($linkedPaymentId))
                ->where('reference_normalized', strtolower($receipt->normalized_reference))
                ->first();
            if ($referenceMatch || $legacyReference) {
                $status = 'EXACT_DUPLICATE';
                $matches[] = ['type' => 'normalized_reference', 'receipt_submission_id' => $referenceMatch?->submission_id, 'payment_submission_id' => $legacyReference?->id];
            }
        }

        if ($status === 'UNIQUE' && $receipt->amount && $receipt->transaction_date) {
            $possible = ReceiptSubmission::query()
                ->whereHas('paymentSubmission')
                ->where('id', '!=', $receipt->id)
                ->where('amount', $receipt->amount)
                ->whereDate('transaction_date', $receipt->transaction_date)
                ->when($receipt->currency, fn ($q) => $q->where('currency', $receipt->currency))
                ->first();
            if ($possible) {
                $status = 'POSSIBLE_DUPLICATE';
                $matches[] = ['type' => 'amount_currency_date', 'receipt_submission_id' => $possible->submission_id];
            }
        }

        return ['status' => $status, 'matches' => $matches];
    }

    public function checkRaw(?string $normalizedRef, ?string $fileHash = null, ?string $dHash = null, ?string $provider = null, ?float $amount = null, ?string $date = null): array
    {
        $matches = [];
        $status = 'UNIQUE';

        if ($fileHash) {
            $exactReceipt = ReceiptSubmission::query()
                ->whereHas('paymentSubmission')
                ->where('receipt_hash', $fileHash)
                ->first();
            $legacyReceipt = PaymentSubmission::query()
                ->where('receipt_hash', $fileHash)
                ->first();
            if ($exactReceipt || $legacyReceipt) {
                $status = 'EXACT_DUPLICATE';
                $matches[] = ['type' => 'file_hash', 'receipt_submission_id' => $exactReceipt?->submission_id, 'payment_submission_id' => $legacyReceipt?->id];
            }
        }

        if ($normalizedRef) {
            $referenceMatch = ReceiptSubmission::query()
                ->whereHas('paymentSubmission')
                ->where('normalized_reference', $normalizedRef)
                ->when($provider, fn ($q) => $q->where('provider', $provider))
                ->first();
            $legacyReference = PaymentSubmission::query()
                ->where('reference_normalized', strtolower($normalizedRef))
                ->first();
            if ($referenceMatch || $legacyReference) {
                $status = 'EXACT_DUPLICATE';
                $matches[] = ['type' => 'normalized_reference', 'receipt_submission_id' => $referenceMatch?->submission_id, 'payment_submission_id' => $legacyReference?->id];
            }
        }

        if ($status === 'UNIQUE' && $amount && $date) {
            $possible = ReceiptSubmission::query()
                ->whereHas('paymentSubmission')
                ->where('amount', $amount)
                ->whereDate('transaction_date', $date)
                ->first();
            if ($possible) {
                $status = 'POSSIBLE_DUPLICATE';
                $matches[] = ['type' => 'amount_currency_date', 'receipt_submission_id' => $possible->submission_id];
            }
        }

        return ['status' => $status, 'matches' => $matches];
    }
}
