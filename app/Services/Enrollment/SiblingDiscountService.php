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
        $verifiedPayments = (float) $account->payments()->where('status', 'verified')->sum('amount');
        $amountPaid = min($totalBalance, (float) $account->enrollment_fee_paid + $verifiedPayments);
        $remaining = max(0, round($totalBalance - $amountPaid, 2));

        $account->update([
            'sibling_order' => $order,
            'discount_type' => $percentage > 0 ? 'sibling' : null,
            'discount_percentage' => $percentage,
            'discount_amount' => $discountAmount,
            'gross_total' => $totalBalance,
            'total_balance' => $totalBalance,
            'amount_paid' => $amountPaid,
            'remaining_balance' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
        ]);

        $enrollmentBilling = $account->monthlyBillings->firstWhere('month_number', 0);
        if ($enrollmentBilling) {
            $enrollmentBilling->update([
                'amount_due' => (float) $account->enrollment_fee_paid,
                'status' => 'paid',
                'paid_at' => $enrollmentBilling->paid_at ?? $account->created_at,
            ]);
        }

        $unpaidBillings = $account->monthlyBillings
            ->where('month_number', '>', 0)
            ->where('status', '!=', 'paid')
            ->values();

        if ($unpaidBillings->isEmpty()) {
            return;
        }

        $installment = round($remaining / $unpaidBillings->count(), 2);
        $allocated = 0.0;

        foreach ($unpaidBillings as $index => $billing) {
            $amount = $index === $unpaidBillings->count() - 1
                ? round($remaining - $allocated, 2)
                : $installment;
            $billing->update(['amount_due' => $amount]);
            $allocated += $amount;
        }

        $account->update(['monthly_tuition' => $installment]);
    }
}
