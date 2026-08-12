<?php

namespace App\Services;

use App\Models\SoaMonthlyBilling;
use App\Models\Student;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentEligibilityService
{
    public function check(Student $student, SoaMonthlyBilling $billing): array
    {
        $account = $student->account;

        if (!$account || $billing->student_id !== $student->id || $billing->student_account_id !== $account->id) {
            throw new InvalidArgumentException('The billing period does not belong to this student account.');
        }

        $billings = $account->relationLoaded('monthlyBillings')
            ? $account->monthlyBillings
            : $account->monthlyBillings()->with('payments')->get();

        if ($billings->contains(fn (SoaMonthlyBilling $item) => !$item->relationLoaded('payments'))) {
            $billings->loadMissing('payments');
        }
        $currentBilling = $billings->firstWhere('id', $billing->id) ?? $billing->loadMissing('payments');
        $currentRemaining = $this->remainingBalance($currentBilling);
        $hasPendingPayment = $currentBilling->payments->contains('status', 'pending');

        $oldestOutstanding = $billings
            ->filter(fn (SoaMonthlyBilling $item) => $item->due_date->lt($currentBilling->due_date))
            ->sortBy('due_date')
            ->first(fn (SoaMonthlyBilling $item) => $this->remainingBalance($item) > 0);

        $oldestRemaining = $oldestOutstanding
            ? $this->remainingBalance($oldestOutstanding)
            : 0.0;

        $paymentAllowed = $currentRemaining > 0
            && !$hasPendingPayment
            && !$oldestOutstanding;

        $reason = null;
        if ($currentRemaining <= 0) {
            $reason = 'paid';
        } elseif ($oldestOutstanding) {
            $reason = 'previous_balance';
        } elseif ($hasPendingPayment) {
            $reason = 'pending_payment';
        }

        return [
            'payment_allowed' => $paymentAllowed,
            'reason' => $reason,
            'remaining_amount' => $currentRemaining,
            'has_pending_payment' => $hasPendingPayment,
            'oldest_outstanding_billing_id' => $oldestOutstanding?->id,
            'oldest_outstanding_month_number' => $oldestOutstanding?->month_number,
            'oldest_outstanding_month' => $oldestOutstanding?->due_date?->format('F Y'),
            'oldest_outstanding_amount' => $oldestRemaining,
        ];
    }

    public function remainingBalance(SoaMonthlyBilling $billing): float
    {
        if ($billing->status === 'paid') {
            return 0.0;
        }

        $payments = $billing->relationLoaded('payments')
            ? $billing->payments
            : $billing->payments()->get();
        $verifiedAmount = (float) $payments
            ->where('status', 'verified')
            ->sum(fn ($payment) => (float) $payment->amount);

        return max(0, round((float) $billing->amount_due - $verifiedAmount, 2));
    }
}
