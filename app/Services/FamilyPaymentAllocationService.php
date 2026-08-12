<?php

namespace App\Services;

use App\Models\FamilyAdvanceCredit;
use App\Models\PaymentSubmission;
use App\Models\SoaMonthlyBilling;
use App\Models\Student;
use App\Models\StudentAccountPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyPaymentAllocationService
{
    /**
     * Build a deterministic oldest-first allocation using integer centavos.
     * Each balance must contain an id and remaining amount and must already be
     * ordered by billing month.
     */
    public function plan(float $paymentAmount, array $balances): array
    {
        $unallocatedCents = max(0, (int) round($paymentAmount * 100));
        $allocations = [];

        foreach ($balances as $balance) {
            if ($unallocatedCents === 0) {
                break;
            }

            $balanceCents = max(0, (int) round(((float) ($balance['remaining'] ?? 0)) * 100));
            if ($balanceCents === 0) {
                continue;
            }

            $appliedCents = min($unallocatedCents, $balanceCents);
            $allocations[] = [
                'id' => $balance['id'],
                'amount' => (float) ($appliedCents / 100),
                'remaining_after' => (float) (($balanceCents - $appliedCents) / 100),
            ];
            $unallocatedCents -= $appliedCents;
        }

        return [
            'allocations' => $allocations,
            'advance_credit' => (float) ($unallocatedCents / 100),
        ];
    }

    /**
     * Post a verified family receipt to every outstanding balance, oldest
     * billing month first. A family row lock prevents concurrent approvals
     * from allocating against the same balance.
     */
    public function allocateVerifiedSubmission(PaymentSubmission $submission, User $verifier): array
    {
        return DB::transaction(function () use ($submission, $verifier) {
            $submission = PaymentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            User::query()->lockForUpdate()->findOrFail($submission->user_id);

            if ($submission->status === 'verified') {
                return [
                    'allocations' => $submission->payments()->orderBy('allocation_sequence')->get(),
                    'advance_credit' => (float) ($submission->advanceCredit?->remaining_amount ?? 0),
                    'idempotent' => true,
                ];
            }

            if ($submission->status === 'rejected') {
                throw ValidationException::withMessages([
                    'action' => 'A rejected payment submission cannot be allocated.',
                ]);
            }

            // Remove legacy pending allocation rows created by older versions.
            $submission->payments()->where('status', 'pending')->get()->each->delete();

            $studentIds = Student::query()
                ->whereHas('applicant', fn ($query) => $query->where('user_id', $submission->user_id))
                ->pluck('id');

            $billings = SoaMonthlyBilling::query()
                ->whereIn('student_id', $studentIds)
                ->with([
                    'payments' => fn ($query) => $query->where('status', 'verified'),
                    'studentAccount',
                ])
                ->orderBy('due_date')
                ->orderBy('month_number')
                ->orderBy('student_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $balances = $billings->map(function (SoaMonthlyBilling $billing) {
                $due = (float) $billing->amount_due;
                $paid = $billing->payments->sum(fn ($payment) => (float) $payment->amount);
                if ($billing->status === 'paid') {
                    $paid = $due;
                } elseif ((int) $billing->month_number === 0) {
                    $paid = max($paid, min($due, (float) $billing->studentAccount?->enrollment_fee_paid));
                }

                return [
                    'id' => $billing->id,
                    'remaining' => max(0, round($due - $paid, 2)),
                ];
            })->all();

            $plan = $this->plan((float) $submission->total_amount, $balances);
            $billingsById = $billings->keyBy('id');
            $created = collect();

            foreach ($plan['allocations'] as $index => $allocation) {
                $billing = $billingsById->get($allocation['id']);
                if (! $billing) {
                    continue;
                }

                $created->push(StudentAccountPayment::create([
                    'payment_submission_id' => $submission->id,
                    'allocation_sequence' => $index + 1,
                    'allocation_source' => 'automatic_oldest_first',
                    'student_account_id' => $billing->student_account_id,
                    'student_id' => $billing->student_id,
                    'soa_monthly_billing_id' => $billing->id,
                    'method' => $submission->method,
                    'payment_mode' => $submission->payment_mode,
                    'reference_no' => $submission->reference_no,
                    'transaction_date' => $submission->transaction_date,
                    'transaction_at' => $submission->transaction_at,
                    'checked_by' => $verifier->name,
                    'account_received' => $submission->account_received,
                    'amount' => $allocation['amount'],
                    'receipt_url' => $submission->receipt_url,
                    'status' => 'verified',
                    'remarks' => 'Automatically allocated to the oldest outstanding family balance.',
                    'paid_at' => $submission->transaction_at ?? now(),
                    'verified_at' => now(),
                    'ocr_status' => $submission->ocr_status,
                    'ocr_raw_text' => $submission->ocr_raw_text,
                    'ocr_scanned_ref' => $submission->ocr_scanned_ref,
                    'ocr_scanned_amount' => $submission->ocr_scanned_amount,
                ]));
            }

            $credit = null;
            if ($plan['advance_credit'] > 0) {
                $credit = FamilyAdvanceCredit::updateOrCreate(
                    ['payment_submission_id' => $submission->id],
                    [
                        'user_id' => $submission->user_id,
                        'original_amount' => $plan['advance_credit'],
                        'remaining_amount' => $plan['advance_credit'],
                        'status' => 'active',
                        'verified_by' => $verifier->id,
                    ]
                );
            }

            $submission->forceFill([
                'status' => 'verified',
                'remarks' => 'Finance verified. Automatically allocated oldest family balance first.'
                    .($credit ? ' Excess recorded as advance credit.' : ''),
            ])->save();

            return [
                'allocations' => $created,
                'advance_credit' => (float) ($credit?->remaining_amount ?? 0),
                'idempotent' => false,
            ];
        });
    }
}
