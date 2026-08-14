<?php

namespace App\Services\Enrollment;

use App\Models\DiscountSetting;
use App\Models\EnrollmentApplicant;
use App\Models\SchoolFee;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SiblingDiscountService
{
    public const ELIGIBLE_STATUSES = [
        'ready_for_submission',
        'pending',
        'submitted',
        'under_review',
        'approved',
    ];

    public function apply(User $user, EnrollmentApplicant $applicant): void
    {
        if (in_array($applicant->status, self::ELIGIBLE_STATUSES, true)) {
            $this->syncFamily($user);

            return;
        }

        $siblingOrder = $this->siblingOrderFor($user, $applicant);
        $setting = DiscountSetting::current();
        $percentage = $setting->siblingPercentageForFamilySize($siblingOrder);

        $this->applyToApplicant($applicant, $siblingOrder, $percentage);
    }

    public function siblingOrderFor(User $user, ?EnrollmentApplicant $applicant = null): int
    {
        $applicants = $user->enrollmentApplicants()
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id']);

        if ($applicant?->id && ($position = $applicants->search(fn ($item) => $item->id === $applicant->id)) !== false) {
            return $position + 1;
        }

        return $applicants->count() + 1;
    }

    /** Apply total children x per-child rate equally to every child in the family. */
    public function syncFamily(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $setting = DiscountSetting::current();
            $applicants = $user->enrollmentApplicants()
                ->whereIn('status', self::ELIGIBLE_STATUSES)
                ->with('student.account.monthlyBillings')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
            $familyPercentage = $setting->siblingPercentageForFamilySize($applicants->count());
            $changes = [];

            foreach ($applicants as $index => $applicant) {
                $order = $index + 1;
                $discountAmount = $this->applyToApplicant($applicant, $order, $familyPercentage);

                $changes[] = [
                    'applicant_id' => $applicant->id,
                    'student_id' => $applicant->student?->id,
                    'sibling_order' => $order,
                    'discount_percentage' => $familyPercentage,
                    'discount_amount' => $discountAmount,
                ];
            }

            return $changes;
        });
    }

    private function applyToApplicant(EnrollmentApplicant $applicant, int $order, float $percentage): float
    {
        $tuition = 0.0;
        $account = $applicant->student?->account;

        if ($account) {
            $tuition = (float) $account->tuition_fee;
        } elseif ($applicant->grade_level && $applicant->school_year) {
            $tuition = (float) (SchoolFee::forGrade($applicant->grade_level, $applicant->school_year)?->tuition_fee ?? 0);
        }

        $discountAmount = round($tuition * ($percentage / 100), 2);
        $applicant->update([
            'sibling_order' => $order,
            'discount_type' => $percentage > 0 ? 'sibling' : null,
            'discount_percentage' => $percentage,
            'discount_amount' => $discountAmount,
        ]);

        if ($account) {
            $this->syncAccount($account, $order, $percentage, $discountAmount);
        }

        return $discountAmount;
    }

    private function syncAccount(StudentAccount $account, int $order, float $percentage, float $discountAmount): void
    {
        $totalBalance = round(
            (float) $account->tuition_fee - $discountAmount
            + (float) $account->miscellaneous_fee
            + (float) $account->books_fee,
            2
        );
        $billings = $account->monthlyBillings()->with('payments')->get();
        $enrollmentBilling = $billings->firstWhere('month_number', 0);
        $installmentBillings = $billings->where('month_number', '>', 0)->values();
        $enrollmentCharge = min($totalBalance, (float) $account->enrollment_fee_paid);
        $installmentTotal = max(0, round($totalBalance - $enrollmentCharge, 2));

        $account->update([
            'sibling_order' => $order,
            'discount_type' => $percentage > 0 ? 'sibling' : null,
            'discount_percentage' => $percentage,
            'discount_amount' => $discountAmount,
            'gross_total' => $totalBalance,
            'total_balance' => $totalBalance,
            'monthly_tuition' => $installmentBillings->isNotEmpty()
                ? round($installmentTotal / $installmentBillings->count(), 2)
                : 0,
        ]);

        if ($enrollmentBilling) {
            $enrollmentBilling->update([
                'amount_due' => $enrollmentCharge,
                'status' => 'paid',
                'paid_at' => $enrollmentBilling->paid_at ?? $account->created_at,
            ]);
        }

        if ($installmentBillings->isEmpty()) {
            $account->recalculate();

            return;
        }

        $verifiedByBilling = $installmentBillings->mapWithKeys(fn ($billing) => [
            $billing->id => round((float) $billing->payments
                ->where('status', 'verified')
                ->sum(fn ($payment) => (float) $payment->amount), 2),
        ]);
        $verifiedTotal = round((float) $verifiedByBilling->sum(), 2);
        $remainingToSchedule = max(0, round($installmentTotal - $verifiedTotal, 2));
        $openBillings = $installmentBillings
            ->filter(fn ($billing) => $billing->status !== 'paid'
                || (float) $verifiedByBilling->get($billing->id, 0) + .01 < (float) $billing->amount_due)
            ->values();

        // A later policy change may add a balance back to a completed plan.
        if ($openBillings->isEmpty() && $remainingToSchedule > 0) {
            $openBillings = $installmentBillings;
        }

        $baseRemaining = $openBillings->isNotEmpty()
            ? round($remainingToSchedule / $openBillings->count(), 2)
            : 0;
        $allocatedRemaining = 0.0;

        foreach ($installmentBillings as $billing) {
            $verified = (float) $verifiedByBilling->get($billing->id, 0);
            $openIndex = $openBillings->search(fn ($openBilling) => $openBilling->id === $billing->id);
            $remainingForBilling = 0.0;

            if ($openIndex !== false) {
                $remainingForBilling = $openIndex === $openBillings->count() - 1
                    ? round($remainingToSchedule - $allocatedRemaining, 2)
                    : $baseRemaining;
                $allocatedRemaining += $remainingForBilling;
            }

            $amountDue = round($verified + $remainingForBilling, 2);
            $remaining = max(0, round($amountDue - $verified, 2));
            $status = $remaining <= .01
                ? 'paid'
                : ($billing->due_date->isPast() ? 'overdue' : 'unpaid');

            $billing->update([
                'amount_due' => $amountDue,
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($billing->paid_at ?? now()) : null,
            ]);
        }

        // Avoid double-counting enrollment or previously verified payments.
        $account->recalculate();
    }
}
