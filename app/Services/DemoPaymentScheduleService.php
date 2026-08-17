<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        $approvedSubmissions = collect();

        if ($userId) {
            try {
                if (Schema::hasTable('payment_submissions')) {
                    $approvedSubmissions = DB::table('payment_submissions')
                        ->where('user_id', $userId)
                        ->whereIn('status', ['approved', 'verified'])
                        ->orderBy('id')
                        ->get();
                }
            } catch (\Throwable $e) {
                $approvedSubmissions = collect();
            }
        }

        $rawSchedule = [];

        for ($installment = 1; $installment <= $installmentCount; $installment++) {
            $dueDate = $monthlyStart->copy()->addMonthsNoOverflow($installment - 1);
            $monthLabel = strtoupper($dueDate->format('F Y'));
            $groups[$installment] = $this->group($installment, $dueDate->format('F'), $dueDate, false);

            $childrenRows = [];
            $groupOriginalTotal = 0.0;
            $groupManualPaid = 0.0;

            foreach ($children as $child) {
                $childInstallments = max(1, (int) $child->installment_months);
                if ($installment > $childInstallments) {
                    continue;
                }

                $childName = (string) ($child->display_name ?: ($child->first_name ?? '').' '.($child->last_name ?? ''));
                $childId = (string) ($child->demo_student_number ?: $child->id);
                $adj = $this->findAdjustmentForChild($childId, $childName, $monthLabel);

                $originalAmount = $adj ? round((float) $adj['fee'], 2) : $this->installmentAmount($child, $installment, $childInstallments);
                $manualPaid = $adj ? round((float) $adj['paid'], 2) : 0.0;
                $rem = max(0.0, round($originalAmount - $manualPaid, 2));

                $groupOriginalTotal += $originalAmount;
                $groupManualPaid += $manualPaid;

                $childrenRows[] = [
                    'child_obj' => $child,
                    'student_id' => 'demo-'.$child->id,
                    'full_name' => mb_strtoupper((string) $child->display_name),
                    'grade_level' => (string) $child->grade_level,
                    'student_number' => (string) $child->demo_student_number,
                    'original' => $originalAmount,
                    'original_amount' => $originalAmount,
                    'verified' => $manualPaid,
                    'verified_paid' => $manualPaid,
                    'remaining' => $rem,
                    'remaining_amount' => $rem,
                    'amount_due' => $rem,
                    'allocated' => $manualPaid,
                    'is_paid' => $rem <= 0.01,
                    'status' => $rem <= 0.01 ? 'paid' : ($manualPaid > 0.01 ? 'partial' : 'unpaid'),
                ];
            }

            $rawSchedule[$installment] = [
                'installment' => $installment,
                'label' => $monthLabel,
                'month_label' => $monthLabel,
                'due_date' => $dueDate,
                'children' => $childrenRows,
                'total_due' => round($groupOriginalTotal, 2),
                'total_paid' => round($groupManualPaid, 2),
                'remaining' => max(0.0, round($groupOriginalTotal - $groupManualPaid, 2)),
                'total_remaining' => max(0.0, round($groupOriginalTotal - $groupManualPaid, 2)),
            ];
        }

        // Apply all approved payments sequentially through ₱100 round-robin allocator
        foreach ($approvedSubmissions as $sub) {
            $this->allocateScheduleRoundRobin($rawSchedule, (float) $sub->total_amount, $userId, false);
        }

        // Build final groups
        for ($installment = 1; $installment <= $installmentCount; $installment++) {
            if (! isset($rawSchedule[$installment])) {
                continue;
            }

            $sGroup = $rawSchedule[$installment];
            $dueDate = $sGroup['due_date'];

            foreach ($sGroup['children'] as $c) {
                $child = $c['child_obj'];
                $originalAmount = (float) $c['original'];
                $verifiedPaid = (float) $c['verified'];
                $remainingAmount = (float) $c['remaining'];
                $isPaid = $remainingAmount <= 0.01;

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
     * Clean ₱100 Round-Robin Allocation Algorithm.
     * Level 1: Oldest outstanding billing month first (FIFO).
     * Level 2: Inside month, Child 1 -> Child 2 -> ... -> Child N in ₱100 increments with persistent pointer.
     */
    public function allocateScheduleRoundRobin(
        array &$schedule,
        float $paymentAmount,
        int|string|null $familyId,
        bool $persistPointer = false
    ): array {
        $remainingPayment = round((float) $paymentAmount, 2);
        $allocations = [];

        foreach ($schedule as &$monthGroup) {
            if ($remainingPayment <= 0.001) {
                break;
            }

            $monthLabel = $monthGroup['label'] ?? ($monthGroup['month_label'] ?? 'MONTH');
            $childrenState = &$monthGroup['children'];
            $monthTotalRemaining = 0.0;
            foreach ($childrenState as $c) {
                $monthTotalRemaining += (float) ($c['remaining'] ?? ($c['remaining_amount'] ?? 0));
            }
            $monthTotalRemaining = round($monthTotalRemaining, 2);

            if ($monthTotalRemaining <= 0.001) {
                continue;
            }

            $numChildren = count($childrenState);
            if ($numChildren === 0) {
                continue;
            }

            $monthKey = "demo_rr_ptr_{$familyId}_" . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($monthLabel));
            $pointer = (int) Cache::get($monthKey, 0);
            if ($pointer < 0 || $pointer >= $numChildren) {
                $pointer = 0;
            }

            // Level 1: If remaining payment can fully cover all children in this month
            if ($remainingPayment >= ($monthTotalRemaining - 0.001)) {
                foreach ($childrenState as $idx => &$c) {
                    $cRem = (float) ($c['remaining'] ?? ($c['remaining_amount'] ?? 0));
                    if ($cRem > 0.001) {
                        $allocatedNow = $cRem;
                        $c['allocated'] = round(($c['allocated'] ?? 0) + $allocatedNow, 2);
                        $c['verified'] = round(($c['verified'] ?? ($c['verified_paid'] ?? 0)) + $allocatedNow, 2);
                        $c['verified_paid'] = $c['verified'];
                        $c['remaining'] = 0.0;
                        $c['remaining_amount'] = 0.0;
                        $c['amount_due'] = 0.0;
                        $c['is_paid'] = true;
                        $c['status'] = 'FULLY_PAID';

                        $remainingPayment = max(0.0, round($remainingPayment - $allocatedNow, 2));

                        $studentName = $c['full_name'] ?? 'Student';
                        $studentId = $c['student_id'] ?? '';
                        $gradeLevel = $c['grade_level'] ?? '';
                        $originalDue = (float) ($c['original'] ?? ($c['original_amount'] ?? $cRem));

                        $allocations[] = [
                            'sequence' => count($allocations) + 1,
                            'month' => $monthLabel,
                            'billing_month' => $monthLabel,
                            'student_name' => $studentName,
                            'student_id' => $studentId,
                            'grade_level' => $gradeLevel,
                            'original_due' => $originalDue,
                            'balance_before' => $cRem,
                            'allocated' => $allocatedNow,
                            'applied_amount' => $allocatedNow,
                            'remaining_due' => 0.0,
                            'remaining_after' => 0.0,
                            'status' => 'FULLY_PAID',
                        ];
                    }
                }
                unset($c);

                if ($persistPointer && $familyId) {
                    Cache::put($monthKey, 0, now()->addYear());
                }
            } else {
                // Level 2: ₱100 Round-robin allocation loop inside this month
                $monthTxAllocations = [];
                $safetyLimit = 50000;

                while ($remainingPayment > 0.001 && $safetyLimit-- > 0) {
                    $eligible = [];
                    for ($i = 0; $i < $numChildren; $i++) {
                        $remVal = (float) ($childrenState[$i]['remaining'] ?? ($childrenState[$i]['remaining_amount'] ?? 0));
                        if ($remVal > 0.001) {
                            $eligible[] = $i;
                        }
                    }

                    if (empty($eligible)) {
                        break;
                    }

                    $targetIndex = null;
                    for ($step = 0; $step < $numChildren; $step++) {
                        $check = ($pointer + $step) % $numChildren;
                        if (in_array($check, $eligible, true)) {
                            $targetIndex = $check;
                            break;
                        }
                    }

                    if ($targetIndex === null) {
                        break;
                    }

                    $c = &$childrenState[$targetIndex];
                    $cRem = (float) ($c['remaining'] ?? ($c['remaining_amount'] ?? 0));

                    if ($cRem < 100.0) {
                        $unit = min($cRem, $remainingPayment);
                    } elseif ($remainingPayment < 100.0) {
                        $unit = min($remainingPayment, $cRem);
                    } else {
                        $unit = min(100.0, $cRem, $remainingPayment);
                    }

                    $unit = round($unit, 2);
                    if ($unit <= 0.0001) {
                        break;
                    }

                    $c['allocated'] = round(($c['allocated'] ?? 0) + $unit, 2);
                    $c['verified'] = round(($c['verified'] ?? ($c['verified_paid'] ?? 0)) + $unit, 2);
                    $c['verified_paid'] = $c['verified'];
                    $c['remaining'] = max(0.0, round($cRem - $unit, 2));
                    $c['remaining_amount'] = $c['remaining'];
                    $c['amount_due'] = $c['remaining'];
                    $c['is_paid'] = $c['remaining'] <= 0.01;
                    $c['status'] = $c['remaining'] <= 0.01 ? 'FULLY_PAID' : 'PARTIALLY_PAID';

                    $monthTxAllocations[$targetIndex] = round(($monthTxAllocations[$targetIndex] ?? 0) + $unit, 2);
                    $remainingPayment = max(0.0, round($remainingPayment - $unit, 2));

                    $pointer = ($targetIndex + 1) % $numChildren;
                }
                unset($c);

                if ($persistPointer && $familyId) {
                    Cache::put($monthKey, $pointer, now()->addYear());
                }

                foreach ($monthTxAllocations as $idx => $allocatedNow) {
                    $c = $childrenState[$idx];
                    $studentName = $c['full_name'] ?? 'Student';
                    $studentId = $c['student_id'] ?? '';
                    $gradeLevel = $c['grade_level'] ?? '';
                    $cRem = (float) ($c['remaining'] ?? ($c['remaining_amount'] ?? 0));
                    $originalDue = (float) ($c['original'] ?? ($c['original_amount'] ?? round($cRem + $allocatedNow, 2)));

                    $allocations[] = [
                        'sequence' => count($allocations) + 1,
                        'month' => $monthLabel,
                        'billing_month' => $monthLabel,
                        'student_name' => $studentName,
                        'student_id' => $studentId,
                        'grade_level' => $gradeLevel,
                        'original_due' => round($cRem + $allocatedNow, 2),
                        'balance_before' => round($cRem + $allocatedNow, 2),
                        'allocated' => $allocatedNow,
                        'applied_amount' => $allocatedNow,
                        'remaining_due' => $cRem,
                        'remaining_after' => $cRem,
                        'status' => $cRem <= 0.01 ? 'FULLY_PAID' : 'PARTIALLY_PAID',
                    ];
                }
            }

            // Recalculate totals on month group
            $mTotDue = 0.0;
            $mTotPaid = 0.0;
            $mTotRem = 0.0;

            foreach ($childrenState as $c) {
                $mTotDue += (float) ($c['original'] ?? ($c['original_amount'] ?? 0));
                $mTotPaid += (float) ($c['verified'] ?? ($c['verified_paid'] ?? 0));
                $mTotRem += (float) ($c['remaining'] ?? ($c['remaining_amount'] ?? 0));
            }

            $monthGroup['total_due'] = round($mTotDue, 2);
            $monthGroup['total_paid'] = round($mTotPaid, 2);
            $monthGroup['remaining'] = round($mTotRem, 2);
            $monthGroup['total_remaining'] = round($mTotRem, 2);
        }
        unset($monthGroup);

        return [
            'allocations' => $allocations,
            'total_allocated' => round($paymentAmount - $remainingPayment, 2),
            'advance_credit' => $remainingPayment > 0 ? round($remainingPayment, 2) : 0.00,
        ];
    }

    /**
     * Return the same full tuition schedule used by the family month cards.
     * This is intentionally independent of enrollment date so late students
     * still receive a complete JULY-MARCH Statement of Account.
     */
    public function installmentsFor(object $child, ?Collection $allChildren = null, ?User $user = null): array
    {
        $userId = $user?->id ?? ($child->user_id ?? null);
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
        $groups = $this->build($allChildren, $user);
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
        $planBalance = round((float) ($child->total_balance ? max(0, (float) $child->total_balance - (float) ($child->enrollment_fee_paid ?? 0)) : ($child->remaining_balance ?? 0)), 2);
        $regularAmount = round((float) ($child->monthly_tuition ?? ($planBalance / max(1, $installmentCount))), 2);

        if ($planBalance < ($regularAmount * ($installmentCount - 1)) && (float) ($child->monthly_tuition ?? 0) > 0) {
            $planBalance = round($regularAmount * $installmentCount, 2);
        }

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

    public function getAdjustments(): array
    {
        $paths = [
            storage_path('app/demo_schedule_adjustments.json'),
            base_path('../amis_admin/storage/app/demo_schedule_adjustments.json'),
            base_path('../admin.amis.edu.ph/storage/app/demo_schedule_adjustments.json'),
        ];

        foreach ($paths as $p) {
            if (file_exists($p)) {
                try {
                    $content = file_get_contents($p);
                    $decoded = json_decode($content, true);
                    if (is_array($decoded) && ! empty($decoded)) {
                        return $decoded;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return [];
    }

    public function findAdjustmentForChild(string $studentId, string $childName, string $month): ?array
    {
        $adjustments = $this->getAdjustments();
        $monthClean = strtoupper(trim($month));

        foreach ($adjustments as $key => $adj) {
            if (strtoupper(trim($adj['month'] ?? '')) !== $monthClean) {
                continue;
            }
            $adjId = strtoupper(trim($adj['student_identifier'] ?? ''));
            $cleanStudentId = strtoupper(trim($studentId));
            $cleanChildName = strtoupper(trim($childName));

            if ($adjId === $cleanStudentId || $adjId === $cleanChildName) {
                return $adj;
            }

            preg_match('/(?:^|[^0-9])0*(1|2|3|4|5|6|7|8|9)(?:[^0-9]|$)/', preg_replace('/202[0-9]/', '', $adjId), $m1);
            preg_match('/(?:^|[^0-9])0*(1|2|3|4|5|6|7|8|9)(?:[^0-9]|$)/', preg_replace('/202[0-9]/', '', $cleanStudentId), $m2);
            if (($m1[1] ?? null) !== null && ($m1[1] ?? null) === ($m2[1] ?? null)) {
                return $adj;
            }

            if (str_contains($cleanChildName, $adjId) || str_contains($adjId, $cleanChildName)) {
                return $adj;
            }
        }

        return null;
    }
}
