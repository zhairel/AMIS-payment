<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoPaymentScheduleService
{
    public function build(Collection $children, ?User $user = null): array
    {
        if ($children->isEmpty()) {
            return [];
        }

        $schoolYear = (string) ($children->first()->school_year ?: now()->format('Y').'-'.now()->addYear()->format('Y'));
        $startYear = (int) substr($schoolYear, 0, 4);
        $startYear = $startYear > 2000 ? $startYear : (int) now()->format('Y');
        $installmentCount = max(1, (int) $children->max('installment_months'));
        // The school's SOA is always the complete nine-month cycle. A late
        // enrollment does not move the first tuition installment to a later month.
        $monthlyStart = Carbon::create($startYear, 7, 15)->startOfDay();
        $enrollmentDate = Carbon::create($startYear, 6, 1)->startOfDay();

        $groups = [
            0 => $this->group(0, 'Enrollment / Initial Payment', $enrollmentDate, true),
        ];

        foreach ($children as $child) {
            $paidEnrollment = round((float) $child->enrollment_fee_paid, 2);
            $groups[0]['children'][] = $this->childRow(
                $child,
                null,
                $paidEnrollment,
                $paidEnrollment,
                0,
                true,
                'Enrollment / Initial Payment',
            );
            $groups[0]['total_due'] += $paidEnrollment;
            $groups[0]['total_paid'] += $paidEnrollment;
            $groups[0]['paid_count']++;
        }

        // Fetch cumulative approved payments for this user from shared payment_submissions
        $userId = $user?->id ?? ($children->first()->user_id ?? 2);
        $approvedPaymentsTotal = 0.0;

        if ($userId && Schema::hasTable('payment_submissions')) {
            $approvedPaymentsTotal = (float) DB::table('payment_submissions')
                ->where('user_id', $userId)
                ->whereIn('status', ['approved', 'verified'])
                ->sum('total_amount');
        }

        $remainingPaymentPool = $approvedPaymentsTotal;

        for ($installment = 1; $installment <= $installmentCount; $installment++) {
            $dueDate = $monthlyStart->copy()->addMonthsNoOverflow($installment - 1);
            $groups[$installment] = $this->group($installment, $dueDate->format('F'), $dueDate, false);

            foreach ($children as $child) {
                $childInstallments = max(1, (int) $child->installment_months);
                if ($installment > $childInstallments) {
                    continue;
                }

                $originalAmount = $this->installmentAmount($child, $installment, $childInstallments);

                $verifiedPaid = 0.0;
                if ($remainingPaymentPool > 0) {
                    $verifiedPaid = min($remainingPaymentPool, $originalAmount);
                    $remainingPaymentPool = max(0.0, round($remainingPaymentPool - $verifiedPaid, 2));
                }

                $remainingAmount = max(0.0, round($originalAmount - $verifiedPaid, 2));
                $isPaid = $remainingAmount <= 0;

                $groups[$installment]['children'][] = $this->childRow(
                    $child,
                    null,
                    $originalAmount,
                    $verifiedPaid,
                    $remainingAmount,
                    $isPaid,
                    strtoupper($dueDate->format('F Y')),
                );
                $groups[$installment]['total_due'] += $originalAmount;
                $groups[$installment]['total_paid'] += $verifiedPaid;
                $groups[$installment]['total_remaining'] += $remainingAmount;

                if ($isPaid) {
                    $groups[$installment]['paid_count']++;
                } else {
                    $groups[$installment]['unpaid_count']++;
                    $groups[$installment]['is_overdue'] = $groups[$installment]['is_overdue'] || $dueDate->isPast();
                }
            }
        }

        return collect($groups)
            ->map(function (array $group) {
                foreach (['total_due', 'total_paid', 'total_remaining'] as $key) {
                    $group[$key] = round((float) $group[$key], 2);
                }

                return $group;
            })
            ->all();
    }

    /**
     * Return the same full tuition schedule used by the family month cards.
     * This is intentionally independent of enrollment date so late students
     * still receive a complete JULY-MARCH Statement of Account.
     */
    public function installmentsFor(object $child): array
    {
        $schoolYear = (string) ($child->school_year ?: now()->format('Y').'-'.now()->addYear()->format('Y'));
        $startYear = (int) substr($schoolYear, 0, 4);
        $startYear = $startYear > 2000 ? $startYear : (int) now()->format('Y');
        $installmentCount = max(1, (int) $child->installment_months);
        $monthlyStart = Carbon::create($startYear, 7, 15)->startOfDay();

        $userId = $child->user_id ?? 2;
        $approvedPaymentsTotal = 0.0;
        if ($userId && Schema::hasTable('payment_submissions')) {
            $approvedPaymentsTotal = (float) DB::table('payment_submissions')
                ->where('user_id', $userId)
                ->whereIn('status', ['approved', 'verified'])
                ->sum('total_amount');
        }

        $remainingPaymentPool = $approvedPaymentsTotal;

        return collect(range(1, $installmentCount))
            ->map(function (int $installment) use ($child, $installmentCount, $monthlyStart, &$remainingPaymentPool) {
                $dueDate = $monthlyStart->copy()->addMonthsNoOverflow($installment - 1);
                $originalAmount = $this->installmentAmount($child, $installment, $installmentCount);

                $verifiedPaid = 0.0;
                if ($remainingPaymentPool > 0) {
                    $verifiedPaid = min($remainingPaymentPool, $originalAmount);
                    $remainingPaymentPool = max(0.0, round($remainingPaymentPool - $verifiedPaid, 2));
                }

                $remainingAmount = max(0.0, round($originalAmount - $verifiedPaid, 2));
                $status = $remainingAmount <= 0
                    ? 'Paid'
                    : ($dueDate->isCurrentMonth() ? 'Current' : ($dueDate->isPast() ? 'Overdue' : 'Upcoming'));

                return [
                    'month' => strtoupper($dueDate->format('F Y')),
                    'due_date' => strtoupper($dueDate->format('M d, Y')),
                    'original' => $originalAmount,
                    'verified' => $verifiedPaid,
                    'remaining' => $remainingAmount,
                    'status' => $status,
                ];
            })
            ->all();
    }

    private function installmentAmount(object $child, int $installment, int $installmentCount): float
    {
        $planBalance = round((float) $child->remaining_balance, 2);
        $regularAmount = round((float) $child->monthly_tuition, 2);

        return $installment === $installmentCount
            ? max(0, round($planBalance - ($regularAmount * ($installmentCount - 1)), 2))
            : $regularAmount;
    }

    private function group(int $monthNumber, string $monthName, Carbon $dueDate, bool $isFirstMonth): array
    {
        return [
            'month_number' => $monthNumber,
            'month_name' => $monthName,
            'month_label' => $monthNumber === 0 ? $monthName : strtoupper($dueDate->format('F Y')),
            'due_date' => $dueDate,
            'year' => $dueDate->year,
            'is_first_month' => $isFirstMonth,
            'children' => [],
            'total_due' => 0,
            'total_paid' => 0,
            'total_remaining' => 0,
            'unpaid_count' => 0,
            'paid_count' => 0,
            'pending_count' => 0,
            'is_overdue' => false,
        ];
    }

    private function childRow(
        object $child,
        ?int $billingId,
        float $originalAmount,
        float $verifiedPaid,
        float $remainingAmount,
        bool $isPaid,
        string $oldestMonth,
    ): array {
        return [
            'student_id' => 'demo-'.$child->id,
            'billing_id' => $billingId,
            'full_name' => mb_strtoupper((string) $child->display_name),
            'grade_level' => (string) $child->grade_level,
            'student_number' => (string) $child->demo_student_number,
            'amount_due' => $remainingAmount,
            'original_amount' => $originalAmount,
            'verified_paid' => $verifiedPaid,
            'remaining_amount' => $remainingAmount,
            'status' => $isPaid ? 'paid' : 'unpaid',
            'is_paid' => $isPaid,
            'is_overdue' => ! $isPaid,
            'paid_at' => null,
            'payment_allowed' => false,
            'lock_reason' => 'Demo schedule only. No official billing record is linked.',
            'has_pending_payment' => false,
            'oldest_outstanding_month' => $oldestMonth,
            'oldest_outstanding_month_number' => null,
            'oldest_outstanding_amount' => $remainingAmount,
        ];
    }
}
