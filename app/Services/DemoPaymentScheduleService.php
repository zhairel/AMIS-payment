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
        $userId = $user?->id ?? ($children->first()->user_id ?? null);
        $approvedPaymentsTotal = 0.0;

        if ($userId) {
            try {
                if (Schema::hasTable('payment_submissions')) {
                    $approvedPaymentsTotal = (float) DB::table('payment_submissions')
                        ->where('user_id', $userId)
                        ->whereIn('status', ['approved', 'verified'])
                        ->sum('total_amount');
                }
            } catch (\Throwable $e) {
                $approvedPaymentsTotal = 0.0;
            }
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
    public function installmentsFor(object $child, ?Collection $allChildren = null): array
    {
        $userId = $child->user_id ?? null;
        if (! $allChildren) {
            try {
                $allChildren = $userId && Schema::hasTable('payment_demo_children')
                    ? \App\Models\PaymentDemoChild::where('user_id', $userId)->orderBy('id')->get()
                    : collect([$child]);
            } catch (\Throwable $e) {
                $allChildren = collect([$child]);
            }
            if ($allChildren->isEmpty()) {
                $allChildren = collect([$child]);
            }
        }
        $groups = $this->build($allChildren);
        $childId = $child->id ?? null;

        $installments = [];
        foreach ($groups as $group) {
            if ($group['month_number'] === 0) {
                continue;
            }
            foreach ($group['children'] as $cRow) {
                $matchesChild = ($childId && ($cRow['student_id'] ?? null) === 'demo-'.$childId)
                    || (($cRow['full_name'] ?? null) === ($child->display_name ?? null))
                    || ($allChildren->count() === 1);

                if ($matchesChild) {
                    $dueDate = $group['due_date'];
                    $originalAmount = (float) ($cRow['original_amount'] ?? 0);
                    $verifiedPaid = (float) ($cRow['verified_paid'] ?? 0);
                    $remainingAmount = (float) ($cRow['remaining_amount'] ?? 0);
                    $status = $remainingAmount <= 0.01
                        ? 'Paid'
                        : ($verifiedPaid > 0.01 ? 'Partial' : ($dueDate->isCurrentMonth() ? 'Current' : ($dueDate->isPast() ? 'Overdue' : 'Upcoming')));

                    $installments[] = [
                        'month' => strtoupper($dueDate->format('F Y')),
                        'due_date' => strtoupper($dueDate->format('M d, Y')),
                        'original' => $originalAmount,
                        'verified' => $verifiedPaid,
                        'remaining' => $remainingAmount,
                        'status' => $status,
                    ];
                    break;
                }
            }
        }

        return $installments;
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
            'due_date_formatted' => strtoupper($dueDate->format('M d, Y')),
            'year' => $dueDate->year,
            'is_first_month' => $isFirstMonth,
            'is_overdue' => false,
            'total_due' => 0.0,
            'total_paid' => 0.0,
            'total_remaining' => 0.0,
            'paid_count' => 0,
            'unpaid_count' => 0,
            'pending_count' => 0,
            'children' => [],
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
            'status' => $isPaid ? 'paid' : ($verifiedPaid > 0 ? 'partial' : 'unpaid'),
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
