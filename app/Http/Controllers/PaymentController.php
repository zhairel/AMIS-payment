<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentApplicant;
use App\Models\PaymentSubmission;
use App\Models\ReceiptScanLog;
use App\Models\ReceiptSubmission;
use App\Models\SoaMonthlyBilling;
use App\Models\Student;
use App\Models\StudentAccountPayment;
use App\Models\User;
use App\Services\AmisReceiptRiskService;
use App\Services\CurrentFamilyPaymentCoverageService;
use App\Services\DemoPaymentScheduleService;
use App\Services\Enrollment\SiblingDiscountService;
use App\Services\PaymentEligibilityService;
use App\Services\ReceiptClassificationService;
use App\Services\ReceiptFingerprintService;
use App\Services\Receipts\ReceiptDuplicateService;
use App\Services\Receipts\ReceiptProductionOcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Show the parent's payment dashboard listing all enrolled children with balances.
     */
    public function showDashboard(Request $request, PaymentEligibilityService $paymentEligibility)
    {
        $user = Auth::user();
        $demoChildren = $user->paymentDemoChildren()->orderBy('id')->get();

        // ── AUTO-SEED DEMO CHILDREN IF PARENT HAS NO STUDENTS ────────────────
        if ($user && $user->students()->count() === 0 && $demoChildren->isEmpty()) {
            try {
                app(\Database\Seeders\PaymentDemoChildrenSeeder::class)->seedForUser($user);
                $demoChildren = $user->paymentDemoChildren()->orderBy('id')->get();
            } catch (\Throwable $e) {
                Log::error('Auto-seeding demo children error: '.$e->getMessage());
            }
        }

        // ── AUTO-LINK STUDENTS BY PARENT EMAIL ──────────────────────────────
        if ($user && $user->email && $demoChildren->isEmpty()) {
            try {
                $userEmail = trim(strtolower($user->email));
                $matchingApplicants = EnrollmentApplicant::whereRaw('LOWER(TRIM(parent_email)) = ?', [$userEmail])->get();
                foreach ($matchingApplicants as $applicant) {
                    if ($applicant->user_id !== $user->id) {
                        $applicant->user_id = $user->id;
                        $applicant->save();
                        Log::info("Auto-linked applicant {$applicant->id} to parent user ID {$user->id} via email matching.");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Auto-linking student error: '.$e->getMessage());
            }
        }
        // ────────────────────────────────────────────────────────────────────

        // Fetch all students associated with the parent user
        $students = $user->students()
            ->with(['applicant', 'account.monthlyBillings.payments'])
            ->get();

        // ── BUILD MONTHLY GROUPS (Month → Children) ──────────────────────────
        $monthlyGroups = [];
        $familyTotalRemaining = 0;
        $familyTotalBalance = 0;

        foreach ($students as $student) {
            $account = $student->account;
            if ($account) {
                $familyTotalRemaining += (float) $account->remaining_balance;
                $familyTotalBalance += (float) $account->total_balance;
            }
        }

        if ($students->isEmpty() && $demoChildren->isNotEmpty()) {
            $familyTotalRemaining = round((float) $demoChildren->sum('remaining_balance'), 2);
            $familyTotalBalance = round((float) $demoChildren->sum('total_balance'), 2);
        }

        foreach ($students as $student) {
            $applicant = $student->applicant;
            $account = $student->account;
            if (! $account) {
                continue;
            }

            $fullName = $applicant
                ? mb_strtoupper(trim($applicant->first_name.' '.($applicant->middle_name ? $applicant->middle_name.' ' : '').$applicant->last_name.($applicant->suffix ? ' '.$applicant->suffix : '')))
                : ($student->user->name ?? 'Student');

            foreach ($account->monthlyBillings as $billing) {
                $key = $billing->month_number;

                if (! isset($monthlyGroups[$key])) {
                    $monthlyGroups[$key] = [
                        'month_number' => $billing->month_number,
                        'month_name' => $billing->month_name,
                        'month_label' => strtoupper($billing->month_name).' '.$billing->due_date->year,
                        'due_date' => $billing->due_date,
                        'year' => $billing->due_date->year,
                        'is_first_month' => $billing->month_number === 1,
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

                $eligibility = $paymentEligibility->check($student, $billing);
                $remainingAmount = $eligibility['remaining_amount'];
                $isPaid = $remainingAmount <= 0;
                $isOverdue = ! $isPaid && $billing->due_date->isPast();

                $monthlyGroups[$key]['children'][] = [
                    'student_id' => $student->id,
                    'billing_id' => $billing->id,
                    'full_name' => $fullName,
                    'grade_level' => $student->grade_level,
                    'student_number' => $student->student_number,
                    'amount_due' => $remainingAmount,
                    'original_amount' => (float) $billing->amount_due,
                    'verified_paid' => max(0, round((float) $billing->amount_due - $remainingAmount, 2)),
                    'remaining_amount' => $remainingAmount,
                    'status' => $billing->status,
                    'is_paid' => $isPaid,
                    'is_overdue' => $isOverdue,
                    'paid_at' => $billing->paid_at,
                    'payment_allowed' => $eligibility['payment_allowed'],
                    'lock_reason' => $eligibility['reason'],
                    'has_pending_payment' => $eligibility['has_pending_payment'],
                    'oldest_outstanding_month' => $eligibility['oldest_outstanding_month'],
                    'oldest_outstanding_month_number' => $eligibility['oldest_outstanding_month_number'],
                    'oldest_outstanding_amount' => $eligibility['oldest_outstanding_amount'],
                ];

                $monthlyGroups[$key]['total_due'] += (float) $billing->amount_due;
                $monthlyGroups[$key]['total_paid'] += max(0, (float) $billing->amount_due - $remainingAmount);

                if ($isPaid) {
                    $monthlyGroups[$key]['paid_count']++;
                } else {
                    $monthlyGroups[$key]['unpaid_count']++;
                    if ($eligibility['has_pending_payment']) {
                        $monthlyGroups[$key]['pending_count']++;
                    }
                    if ($isOverdue) {
                        $monthlyGroups[$key]['is_overdue'] = true;
                    }
                }
            }
        }

        if ($students->isEmpty() && $demoChildren->isNotEmpty()) {
            $monthlyGroups = app(DemoPaymentScheduleService::class)->build($demoChildren, $user);
        }

        ksort($monthlyGroups);

        foreach ($monthlyGroups as &$group) {
            $group['total_remaining'] = $group['total_due'] - $group['total_paid'];
        }
        unset($group);

        // First month that still has unpaid children (auto-expand this one)
        $firstUnpaidMonthKey = null;
        foreach ($monthlyGroups as $key => $group) {
            if ($group['unpaid_count'] > 0) {
                $firstUnpaidMonthKey = $key;
                break;
            }
        }

        // Fetch payment history for the family's students
        $studentIds = $students->pluck('id');
        $payments = StudentAccountPayment::whereIn('student_id', $studentIds)
            ->with(['student.applicant', 'monthlyBilling'])
            ->orderBy('created_at', 'desc')
            ->get();
        $legacyPayments = $payments
            ->whereNull('payment_submission_id')
            ->reject(fn (StudentAccountPayment $payment) => strtolower((string) $payment->status) === 'reversed')
            ->values();

        $paymentSubmissions = PaymentSubmission::query()
            ->where('user_id', $user->id)
            ->with(['payments.student.applicant', 'payments.monthlyBilling', 'advanceCredit', 'receiptSubmission'])
            ->latest('submitted_at')
            ->get()
            ->reject(fn (PaymentSubmission $submission) => strtolower((string) $submission->effective_status) === 'reversed')
            ->values();
        $officialReceiptNumbersBySubmission = collect();
        if (Schema::hasTable('finance_transactions') && Schema::hasTable('finance_official_receipts')) {
            $officialReceiptNumbersBySubmission = DB::table('finance_transactions as transactions')
                ->join('finance_official_receipts as official_receipts', 'official_receipts.finance_transaction_id', '=', 'transactions.id')
                ->whereIn('transactions.payment_submission_id', $paymentSubmissions->pluck('id'))
                ->pluck('official_receipts.official_receipt_number', 'transactions.payment_submission_id');
        }
        $familyAdvanceCredit = (float) $user->familyAdvanceCredits()
            ->where('status', 'active')
            ->sum('remaining_amount');

        $currentMonthStart = now(config('finance.timezone', 'Asia/Manila'))->startOfMonth();
        $currentMonthEnd = $currentMonthStart->copy()->endOfMonth();
        $currentMonthKey = collect($monthlyGroups)->search(
            fn ($group) => $group['due_date']->betweenIncluded($currentMonthStart, $currentMonthEnd)
        );
        $currentMonthKey = $currentMonthKey === false ? null : $currentMonthKey;

        foreach ($monthlyGroups as $monthKey => &$group) {
            $group['is_current'] = $monthKey === $currentMonthKey;
            $group['is_previous'] = $group['due_date']->lt($currentMonthStart);
        }
        unset($group);

        $activeReceiptStatuses = [
            ReceiptSubmission::UPLOADED,
            ReceiptSubmission::PROCESSING,
            ReceiptSubmission::OCR_COMPLETED,
            ReceiptSubmission::PENDING_VERIFICATION,
            ReceiptSubmission::NEEDS_REVIEW,
        ];
        $activePendingSubmissions = $paymentSubmissions->filter(
            fn ($submission) => $submission->status === 'pending'
                && (! $submission->receiptSubmission
                    || in_array($submission->receiptSubmission->status, $activeReceiptStatuses, true))
        );
        $previousOpenGroups = collect($monthlyGroups)
            ->where('is_previous', true)
            ->filter(fn ($group) => (float) $group['total_remaining'] > 0.01)
            ->values();
        $previousBalance = round((float) $previousOpenGroups->sum('total_remaining'), 2);
        $previousOriginalBalance = round((float) $previousOpenGroups->sum('total_due'), 2);
        $previousVerifiedPayments = round((float) $previousOpenGroups->sum('total_paid'), 2);
        $previousBalances = $previousOpenGroups
            ->map(fn ($group) => [
                'month_label' => $group['month_number'] === 0
                    ? 'Enrollment / Initial Payment'
                    : strtoupper($group['due_date']->format('F Y')),
                'original_amount' => round((float) $group['total_due'], 2),
                'verified_amount' => round((float) $group['total_paid'], 2),
                'remaining_amount' => round((float) $group['total_remaining'], 2),
            ])
            ->values();
        $currentGroup = $currentMonthKey !== null ? ($monthlyGroups[$currentMonthKey] ?? null) : null;
        $currentCharges = round((float) ($currentGroup['total_due'] ?? 0), 2);
        $currentVerifiedPayments = round((float) ($currentGroup['total_paid'] ?? 0), 2);
        $verifiedAppliedTotal = round($previousVerifiedPayments + $currentVerifiedPayments, 2);
        $grossPayable = round($previousOriginalBalance + $currentCharges, 2);

        $displayedBillingIds = $previousOpenGroups
            ->flatMap(fn ($group) => collect($group['children'])->pluck('billing_id'))
            ->merge(collect($currentGroup['children'] ?? [])->pluck('billing_id'))
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values();
        $verifiedPaymentLines = collect();
        $approvedFinanceTransactions = collect();
        $familyFinanceTransactions = collect();

        if (Schema::hasTable('finance_transactions')) {
            $approvedFinanceTransactions = DB::table('finance_transactions as transactions')
                ->leftJoin('payment_submissions as submissions', 'submissions.id', '=', 'transactions.payment_submission_id')
                ->leftJoin('finance_official_receipts as official_receipts', 'official_receipts.finance_transaction_id', '=', 'transactions.id')
                ->leftJoin('receipt_submissions as receipt_files', 'receipt_files.id', '=', 'transactions.receipt_submission_id')
                ->where('transactions.user_id', $user->id)
                ->where('transactions.status', 'APPROVED')
                ->orderBy('transactions.transaction_at')
                ->get([
                    'transactions.id',
                    'transactions.transaction_number',
                    'transactions.payment_submission_id',
                    'transactions.source',
                    'transactions.payment_method',
                    'transactions.reference_number',
                    'transactions.amount',
                    'transactions.advance_credit',
                    'transactions.family_balance_after',
                    'transactions.transaction_at',
                    'transactions.allocation_snapshot',
                    'transactions.remarks',
                    'submissions.submission_number',
                    'submissions.receipt_url as submission_receipt_url',
                    'receipt_files.submission_id as receipt_submission_number',
                    'official_receipts.official_receipt_number',
                ]);

            $financeBillingIds = $approvedFinanceTransactions
                ->flatMap(function ($transaction) {
                    $allocations = json_decode((string) $transaction->allocation_snapshot, true);

                    return collect(is_array($allocations) ? $allocations : [])->pluck('billing_id');
                })
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $billingMonthLabels = $financeBillingIds->isEmpty()
                ? collect()
                : SoaMonthlyBilling::query()
                    ->whereIn('id', $financeBillingIds)
                    ->get(['id', 'month_name', 'due_date'])
                    ->mapWithKeys(fn (SoaMonthlyBilling $billing) => [
                        (int) $billing->id => $billing->due_date
                            ? strtoupper($billing->due_date->format('F Y'))
                            : strtoupper((string) $billing->month_name),
                    ]);

            $familyFinanceTransactions = $approvedFinanceTransactions
                ->sortByDesc('transaction_at')
                ->map(function ($transaction) use ($user, $billingMonthLabels) {
                    $source = strtoupper((string) $transaction->source);
                    $method = strtoupper(str_replace([' ', '-'], '_', (string) $transaction->payment_method));
                    $methodLabel = match (true) {
                        $method === 'CASH' => 'Cash',
                        $method === 'GCASH' => 'GCash',
                        $method === 'MAYA' => 'Maya',
                        str_starts_with($method, 'BDO') => 'BDO',
                        in_array($method, ['BANK', 'BANK_TRANSFER', 'OTHER_BANK', 'INSTAPAY', 'PESONET'], true) => 'Bank Transfer',
                        $method === 'REMITTANCE' => 'Remittance',
                        default => filled($method) ? Str::headline(strtolower($method)) : 'Other',
                    };
                    $allocations = json_decode((string) $transaction->allocation_snapshot, true);
                    $receiptUrl = $transaction->receipt_submission_number
                        ? route('payment.receipts.original', $transaction->receipt_submission_number)
                        : ($transaction->submission_receipt_url
                            ? Storage::disk('public')->url($transaction->submission_receipt_url)
                            : null);
                    $transactionAt = $transaction->transaction_at ? Carbon::parse($transaction->transaction_at) : null;
                    $allocationRows = collect(is_array($allocations) ? $allocations : [])
                        ->map(function ($allocation) use ($billingMonthLabels) {
                            $billingId = (int) ($allocation['billing_id'] ?? 0);

                            return [
                                'billing_id' => $billingId,
                                'student' => mb_strtoupper((string) ($allocation['student_name'] ?? 'Student account')),
                                'month' => (string) ($billingMonthLabels->get($billingId) ?: ($allocation['billing_month'] ?? 'School payment')),
                                'balance_before' => (float) ($allocation['balance_before'] ?? 0),
                                'amount' => (float) ($allocation['applied_amount'] ?? 0),
                                'remaining' => (float) ($allocation['remaining_after'] ?? 0),
                            ];
                        })
                        ->values();
                    $coveredStudents = $allocationRows->pluck('student')->filter()->unique()->values();
                    $coveredMonths = $allocationRows->pluck('month')->filter()->unique()->values();
                    $itemizedChargesTotal = round((float) $allocationRows->sum('balance_before'), 2);
                    $appliedTotal = round((float) $allocationRows->sum('amount'), 2);
                    $itemizedRemainingTotal = round((float) $allocationRows->sum('remaining'), 2);

                    return [
                        'id' => (int) $transaction->id,
                        'transaction_number' => $transaction->transaction_number,
                        'official_receipt_number' => $transaction->official_receipt_number,
                        'submission_number' => $transaction->submission_number,
                        'source' => $source,
                        'source_label' => $source === 'ONLINE' ? 'Online Payment' : 'Onsite Payment',
                        'method' => $method,
                        'method_label' => $methodLabel,
                        'reference' => $transaction->reference_number,
                        'amount' => (float) $transaction->amount,
                        'advance_credit' => (float) $transaction->advance_credit,
                        'transaction_at' => $transactionAt,
                        'allocation_count' => $allocationRows->count(),
                        'allocations' => $allocationRows,
                        'covered_students' => $coveredStudents,
                        'covered_months' => $coveredMonths,
                        'family_balance_after' => (float) $transaction->family_balance_after,
                        'remarks' => $transaction->remarks,
                        'receipt_url' => $receiptUrl,
                        'modal_data' => [
                            'is_consolidated' => false,
                            'number' => $transaction->official_receipt_number ?: $transaction->transaction_number,
                            'official_receipt_number' => $transaction->official_receipt_number,
                            'submission_number' => $transaction->submission_number,
                            'date' => $transactionAt ? strtoupper($transactionAt->format('F j, Y · h:i A')) : null,
                            'receipt_date' => $transactionAt ? strtoupper($transactionAt->format('F j, Y')) : null,
                            'payer' => $user->name,
                            'source' => $source === 'ONLINE' ? 'Online Payment' : 'Onsite Payment',
                            'method' => $methodLabel,
                            'reference' => $transaction->reference_number,
                            'total' => (float) $transaction->amount,
                            'advance_credit' => (float) $transaction->advance_credit,
                            'balance_after' => (float) $transaction->family_balance_after,
                            'status' => 'verified',
                            'remarks' => $transaction->remarks,
                            'receipt' => $receiptUrl,
                            'allocation_count' => $allocationRows->count(),
                            'allocations' => $allocationRows,
                            'covered_students' => $coveredStudents,
                            'covered_months' => $coveredMonths,
                            'itemized_charges_total' => $itemizedChargesTotal,
                            'applied_total' => $appliedTotal,
                            'itemized_remaining_total' => $itemizedRemainingTotal,
                            'payments' => [],
                        ],
                    ];
                })
                ->values();
        }

        $consolidatedFinanceReceipt = null;
        if ($familyFinanceTransactions->isNotEmpty()) {
            $allocationHistory = $familyFinanceTransactions
                ->sortBy('transaction_at')
                ->flatMap(fn ($record) => $record['allocations'])
                ->values();
            $consolidatedAllocations = $allocationHistory
                ->groupBy(fn ($allocation) => ($allocation['billing_id'] ?: $allocation['student'].'|'.$allocation['month']))
                ->map(function ($entries) {
                    $entries = $entries->values();
                    $month = (string) $entries->first()['month'];
                    try {
                        $monthSort = Carbon::parse('1 '.$month)->format('Y-m');
                    } catch (\Throwable) {
                        $monthSort = $month;
                    }

                    return [
                        'billing_id' => (int) ($entries->first()['billing_id'] ?? 0),
                        'student' => $entries->first()['student'],
                        'month' => $month,
                        'month_sort' => $monthSort,
                        'balance_before' => round((float) $entries->first()['balance_before'], 2),
                        'amount' => round((float) $entries->sum('amount'), 2),
                        'remaining' => round((float) $entries->last()['remaining'], 2),
                    ];
                })
                ->sortBy(fn ($allocation) => $allocation['month_sort'].'|'.$allocation['student'])
                ->values();
            $orderedReceiptTransactions = $familyFinanceTransactions
                ->sortBy([
                    ['transaction_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
            $familyReceiptBillings = collect($monthlyGroups)
                ->flatMap(function ($group, $monthKey) {
                    $groupKey = $monthKey.'|'.$group['due_date']->format('Y-m-d');
                    $monthLabel = $group['month_number'] === 0
                        ? 'Enrollment / Initial Payment'
                        : strtoupper($group['due_date']->format('F Y'));

                    return collect($group['children'])->map(fn ($child) => [
                        'group_key' => $groupKey,
                        'billing_id' => (int) $child['billing_id'],
                        'name' => $child['full_name'],
                        'month' => $monthLabel,
                        'original_amount' => round((float) $child['original_amount'], 2),
                        'final_remaining' => round((float) $child['remaining_amount'], 2),
                    ]);
                })
                ->values();
            $billingGroupKeys = $familyReceiptBillings->pluck('group_key', 'billing_id');

            $verifiedPaymentHistory = $orderedReceiptTransactions
                ->map(function ($record, $recordIndex) use ($orderedReceiptTransactions, $familyReceiptBillings, $billingGroupKeys) {
                    $currentAllocations = collect($record['allocations']);
                    $currentAllocationsByBilling = $currentAllocations->keyBy('billing_id');
                    $touchedGroupKeys = $currentAllocations
                        ->pluck('billing_id')
                        ->map(fn ($billingId) => $billingGroupKeys->get((int) $billingId))
                        ->filter()
                        ->unique()
                        ->values();
                    $futureAppliedByBilling = $orderedReceiptTransactions
                        ->slice($recordIndex + 1)
                        ->flatMap(fn ($futureRecord) => collect($futureRecord['allocations']))
                        ->groupBy('billing_id')
                        ->map(fn ($allocations) => round((float) $allocations->sum('amount'), 2));

                    $children = $familyReceiptBillings
                        ->whereIn('group_key', $touchedGroupKeys)
                        ->map(function ($billing) use ($currentAllocationsByBilling, $futureAppliedByBilling) {
                            $allocation = $currentAllocationsByBilling->get($billing['billing_id']);
                            $applied = round((float) ($allocation['amount'] ?? 0), 2);
                            $dueBefore = $allocation
                                ? round((float) $allocation['balance_before'], 2)
                                : round((float) $billing['final_remaining'] + (float) $futureAppliedByBilling->get($billing['billing_id'], 0), 2);
                            $remaining = $allocation
                                ? max(0, round((float) $allocation['remaining'], 2))
                                : max(0, $dueBefore);
                            $totalPaidToMonth = max(0, round((float) $billing['original_amount'] - $remaining, 2));
                            $status = match (true) {
                                $remaining <= 0.01 => 'PAID',
                                $totalPaidToMonth > 0.01 => 'PARTIAL',
                                default => 'UNPAID',
                            };

                            return [
                                'name' => $billing['name'],
                                'month' => $billing['month'],
                                'due_before' => $dueBefore,
                                'applied' => $applied,
                                'remaining' => $remaining,
                                'status' => $status,
                                'status_label' => match ($status) {
                                    'PAID' => 'Fully Paid',
                                    'PARTIAL' => 'Partially Paid',
                                    default => 'Unpaid',
                                },
                            ];
                        })
                        ->values();

                    if ($children->isEmpty()) {
                        $children = $currentAllocations
                            ->map(function ($allocation) {
                                $dueBefore = round((float) $allocation['balance_before'], 2);
                                $applied = round((float) $allocation['amount'], 2);
                                $remaining = max(0, round((float) $allocation['remaining'], 2));
                                $status = $remaining <= 0.01 ? 'PAID' : ($applied > 0.01 ? 'PARTIAL' : 'UNPAID');

                                return [
                                    'name' => $allocation['student'],
                                    'month' => $allocation['month'],
                                    'due_before' => $dueBefore,
                                    'applied' => $applied,
                                    'remaining' => $remaining,
                                    'status' => $status,
                                    'status_label' => $status === 'PAID' ? 'Fully Paid' : ($status === 'PARTIAL' ? 'Partially Paid' : 'Unpaid'),
                                ];
                            })
                            ->values();
                    }
                    $paymentMonths = $children->pluck('month')->filter()->unique()->values();
                    $receiptTitle = match (true) {
                        $paymentMonths->count() === 1 => mb_strtoupper($paymentMonths->first()).' — RECEIPT',
                        $paymentMonths->count() > 1 => mb_strtoupper($paymentMonths->first().' TO '.$paymentMonths->last()).' — RECEIPT',
                        default => 'FAMILY PAYMENT — RECEIPT',
                    };
                    $appliedTotal = round((float) collect($record['allocations'])->sum('amount'), 2);

                    return [
                        'receipt_title' => $receiptTitle,
                        'official_receipt_number' => $record['official_receipt_number'],
                        'date' => $record['transaction_at'] ? strtoupper($record['transaction_at']->format('M d, Y')) : null,
                        'time' => $record['transaction_at']?->format('h:i A'),
                        'source' => $record['source_label'],
                        'method' => $record['method_label'],
                        'reference' => $record['reference'],
                        'amount' => $record['amount'],
                        'applied_total' => $appliedTotal,
                        'total_monthly_due' => round((float) $children->sum('due_before'), 2),
                        'remaining_for_covered_months' => round((float) $children->sum('remaining'), 2),
                        'advance_credit' => $record['advance_credit'],
                        'family_balance_before' => round((float) $record['family_balance_after'] + $appliedTotal, 2),
                        'family_balance_after' => $record['family_balance_after'],
                        'children' => $children,
                    ];
                })
                ->values();
            $coveredMonthLabels = $consolidatedAllocations->pluck('month')->filter()->unique()->values();
            $currentReceiptMonth = now()->startOfMonth();
            $receiptMonthsThroughCurrent = $consolidatedAllocations
                ->pluck('month_sort')
                ->filter(fn ($monthSort) => $monthSort <= $currentReceiptMonth->format('Y-m'))
                ->unique()
                ->values();
            $receiptPeriodStart = $receiptMonthsThroughCurrent->first();
            if ($receiptPeriodStart) {
                $periodStart = Carbon::createFromFormat('Y-m', $receiptPeriodStart)->startOfMonth();
                $receiptPeriodLabel = strtoupper($periodStart->isSameMonth($currentReceiptMonth)
                    ? $currentReceiptMonth->format('F Y')
                    : $periodStart->format('F').' – '.$currentReceiptMonth->format('F Y'));
            } else {
                $receiptPeriodLabel = strtoupper($currentReceiptMonth->format('F Y'));
            }
            $hasFutureAllocation = $consolidatedAllocations
                ->contains(fn ($allocation) => $allocation['month_sort'] > $currentReceiptMonth->format('Y-m'));
            $latestReceiptDate = $familyFinanceTransactions->max('transaction_at');
            $sourceLabels = $familyFinanceTransactions->pluck('source_label')->unique()->values();
            $methodLabels = $familyFinanceTransactions->pluck('method_label')->unique()->values();

            $consolidatedFinanceReceipt = [
                'is_consolidated' => true,
                'number' => 'Family Payment Receipt',
                'official_receipt_number' => null,
                'receipt_count' => $familyFinanceTransactions->count(),
                'period_label' => $receiptPeriodLabel,
                'submission_number' => null,
                'date' => $latestReceiptDate ? strtoupper($latestReceiptDate->format('F j, Y')) : null,
                'receipt_date' => $latestReceiptDate ? strtoupper($latestReceiptDate->format('F j, Y')) : null,
                'payer' => $user->name,
                'source' => $sourceLabels->count() > 1 ? 'Online and Onsite Payments' : ($sourceLabels->first() ?: 'Verified Payments'),
                'method' => $methodLabels->join(', '),
                'reference' => 'See verified payment history below',
                'total' => round((float) $familyFinanceTransactions->sum('amount'), 2),
                'advance_credit' => round((float) $familyFinanceTransactions->sum('advance_credit'), 2),
                'balance_after' => round((float) $familyTotalRemaining, 2),
                'status' => 'verified',
                'remarks' => 'Consolidated family receipt for '.$receiptPeriodLabel.'. Every approved payment remains linked to its own permanent Official Receipt number.'.($hasFutureAllocation ? ' Any excess was automatically carried forward and is itemized under the applicable future billing month.' : ''),
                'receipt' => null,
                'allocation_count' => $consolidatedAllocations->count(),
                'allocations' => $consolidatedAllocations,
                'covered_students' => $consolidatedAllocations->pluck('student')->filter()->unique()->values(),
                'covered_months' => $coveredMonthLabels,
                'itemized_charges_total' => round((float) $consolidatedAllocations->sum('balance_before'), 2),
                'applied_total' => round((float) $consolidatedAllocations->sum('amount'), 2),
                'itemized_remaining_total' => round((float) $consolidatedAllocations->sum('remaining'), 2),
                'payments' => $verifiedPaymentHistory,
            ];
        }

        // Approved payments are displayed from the family-level Finance transaction.
        // Keep only unposted submissions here so an approved online payment is not shown twice.
        $unpostedPaymentSubmissions = $paymentSubmissions
            ->reject(fn (PaymentSubmission $submission) => $submission->effective_status === 'verified')
            ->values();

        if ($displayedBillingIds->isNotEmpty()) {
            $verifiedPaymentLines = $approvedFinanceTransactions
                ->map(function ($transaction) use ($displayedBillingIds) {
                    $allocations = json_decode((string) $transaction->allocation_snapshot, true);
                    $appliedAmount = collect(is_array($allocations) ? $allocations : [])
                        ->filter(fn ($allocation) => $displayedBillingIds->contains((int) ($allocation['billing_id'] ?? 0)))
                        ->sum(fn ($allocation) => (float) ($allocation['applied_amount'] ?? 0));

                    if ($appliedAmount <= 0.01) {
                        return null;
                    }

                    $source = strtoupper((string) $transaction->source);
                    $method = strtoupper((string) $transaction->payment_method);

                    return [
                        'label' => $transaction->official_receipt_number
                            ? 'OR No. '.$transaction->official_receipt_number
                            : 'Approved payment · '.$transaction->transaction_number,
                        'description' => $source === 'ONLINE'
                            ? 'Approved online payment'
                            : 'Recorded by AMIS Finance',
                        'amount' => round($appliedAmount, 2),
                        'date' => $transaction->transaction_at
                            ? strtoupper(Carbon::parse($transaction->transaction_at)->format('M d, Y'))
                            : null,
                    ];
                })
                ->filter()
                ->values();
        }

        $unlistedVerifiedAmount = max(0, round($verifiedAppliedTotal - $verifiedPaymentLines->sum('amount'), 2));
        if ($unlistedVerifiedAmount > 0.01) {
            $verifiedPaymentLines->push([
                'label' => 'Other verified payment',
                'description' => 'Automatically applied by AMIS',
                'amount' => $unlistedVerifiedAmount,
                'date' => null,
            ]);
        }

        foreach ($monthlyGroups as &$group) {
            $groupBillingIds = collect($group['children'])
                ->pluck('billing_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            $groupPaymentLines = $approvedFinanceTransactions
                ->map(function ($transaction) use ($groupBillingIds) {
                    $allocations = json_decode((string) $transaction->allocation_snapshot, true);
                    $appliedAmount = collect(is_array($allocations) ? $allocations : [])
                        ->filter(fn ($allocation) => $groupBillingIds->contains((int) ($allocation['billing_id'] ?? 0)))
                        ->sum(fn ($allocation) => (float) ($allocation['applied_amount'] ?? 0));

                    if ($appliedAmount <= 0.01) {
                        return null;
                    }

                    $source = strtoupper((string) $transaction->source);
                    $method = strtoupper(str_replace('_', ' ', (string) $transaction->payment_method));

                    return [
                        'label' => $transaction->official_receipt_number
                            ? 'OR No. '.$transaction->official_receipt_number
                            : 'Approved payment · '.$transaction->transaction_number,
                        'description' => $source === 'ONLINE'
                            ? 'Approved online receipt'
                            : 'Recorded by AMIS Finance',
                        'amount' => round($appliedAmount, 2),
                        'date' => $transaction->transaction_at
                            ? strtoupper(Carbon::parse($transaction->transaction_at)->format('M d, Y'))
                            : null,
                    ];
                })
                ->filter()
                ->values();

            $unlistedGroupPaid = max(0, round((float) $group['total_paid'] - $groupPaymentLines->sum('amount'), 2));
            if ($unlistedGroupPaid > 0.01) {
                $groupPaymentLines->push([
                    'label' => 'Other verified payment',
                    'description' => 'Approved school payment record',
                    'amount' => $unlistedGroupPaid,
                    'date' => null,
                ]);
            }

            $group['payment_lines'] = $groupPaymentLines;
        }
        unset($group);

        $activePendingPayments = round($activePendingSubmissions->sum(fn ($submission) => (float) $submission->total_amount), 2);
        $coverage = app(CurrentFamilyPaymentCoverageService::class)->calculate(
            $previousBalance,
            $currentCharges,
            $currentVerifiedPayments,
            $activePendingPayments
        );
        $totalPayable = $coverage['total_payable'];
        $remainingToSubmit = $coverage['remaining_to_submit'];
        $currentPaymentProofs = $activePendingSubmissions
            ->unique('id')
            ->sortByDesc('submitted_at')
            ->map(function ($submission) {
                $receiptStatus = $submission->receiptSubmission?->status;
                $status = match ($receiptStatus) {
                    ReceiptSubmission::UPLOADED => 'Uploaded',
                    ReceiptSubmission::PROCESSING, ReceiptSubmission::OCR_COMPLETED => 'Processing',
                    ReceiptSubmission::NEEDS_REVIEW => 'Needs Finance Review',
                    default => 'Pending Verification',
                };
                $method = strtoupper(str_replace([' ', '-'], '_', (string) ($submission->payment_mode ?: $submission->method)));
                $methodLabel = match (true) {
                    $method === 'GCASH' => 'GCash',
                    $method === 'MAYA' => 'Maya',
                    str_starts_with($method, 'BDO') => 'BDO',
                    in_array($method, ['BANK', 'BANK_TRANSFER', 'OTHER_BANK', 'INSTAPAY', 'PESONET'], true) => 'Bank Transfer',
                    $method === 'REMITTANCE' => 'Remittance',
                    default => 'Other',
                };

                return [
                    'number' => $submission->submission_number,
                    'number_label' => 'Submission No.',
                    'filename' => $submission->receiptSubmission?->original_filename ?: 'Payment receipt',
                    'amount' => (float) $submission->total_amount,
                    'status' => $status,
                    'source_label' => 'Online Payment',
                    'method_label' => $methodLabel,
                    'date' => $submission->submitted_at,
                    'view_url' => $submission->receiptSubmission
                        ? route('payment.receipts.original', $submission->receiptSubmission)
                        : Storage::disk('public')->url($submission->receipt_url),
                ];
            })->values();
        $latestSubmission = $paymentSubmissions->sortByDesc('submitted_at')->first();
        $rejectedPaymentProof = null;
        if ($latestSubmission
            && $latestSubmission->effective_status === 'rejected'
            && $latestSubmission->receiptSubmission
            && in_array($latestSubmission->receiptSubmission->status, [ReceiptSubmission::REJECTED, ReceiptSubmission::REUPLOAD_REQUIRED], true)) {
            $rejectedReceipt = $latestSubmission->receiptSubmission;
            $rejectedPaymentProof = [
                'number' => $latestSubmission->submission_number,
                'filename' => $rejectedReceipt->original_filename ?: 'Payment receipt',
                'amount' => (float) $latestSubmission->total_amount,
                'status' => $rejectedReceipt->status === ReceiptSubmission::REUPLOAD_REQUIRED ? 'Re-upload required' : 'Rejected',
                'reason' => $rejectedReceipt->review_reason ?: $latestSubmission->remarks ?: 'Finance could not approve this receipt. Review the details or upload a clearer payment proof.',
                'view_url' => route('payment.receipts.original', $rejectedReceipt),
                'edit_url' => route('payment.checkout', ['retry' => $latestSubmission->submission_number, 'action' => 'edit']),
                'reupload_url' => route('payment.checkout', ['retry' => $latestSubmission->submission_number, 'action' => 'reupload']),
            ];
        }
        $currentPaymentSummary = [
            'month_key' => $currentMonthKey,
            'previous_balance' => $previousBalance,
            'previous_original_balance' => $previousOriginalBalance,
            'previous_balances' => $previousBalances,
            'current_charges' => $currentCharges,
            'verified_payments' => $currentVerifiedPayments,
            'verified_applied_total' => $verifiedAppliedTotal,
            'verified_payment_lines' => $verifiedPaymentLines,
            'gross_payable' => $grossPayable,
            'total_payable' => $totalPayable,
            'active_pending_payments' => $activePendingPayments,
            'remaining_to_submit' => $remainingToSubmit,
            'awaiting_verification' => $coverage['awaiting_verification'],
            'proofs' => $currentPaymentProofs,
            'rejected_proof' => $rejectedPaymentProof,
            'payment_records' => $familyFinanceTransactions,
        ];

        if ($currentMonthKey !== null && ($remainingToSubmit > 0.01 || $activePendingPayments > 0)) {
            $firstUnpaidMonthKey = $currentMonthKey;
        }

        $nextPayableByStudent = [];
        foreach ($monthlyGroups as $monthKey => $group) {
            foreach ($group['children'] as $child) {
                if ($child['payment_allowed'] && ! isset($nextPayableByStudent[$child['student_id']])) {
                    $nextPayableByStudent[$child['student_id']] = [
                        'month_key' => $monthKey,
                        'month_label' => $group['month_number'] === 0
                            ? 'Enrollment / Initial Payment'
                            : mb_strtoupper($group['month_label']),
                        'amount' => $child['amount_due'],
                    ];
                }
            }
        }

        $paymentNotifications = collect();

        foreach ($monthlyGroups as $group) {
            if ($group['is_overdue'] && $group['unpaid_count'] > 0) {
                $paymentNotifications->push([
                    'type' => 'overdue',
                    'title' => mb_strtoupper($group['month_name']).' '.$group['year'].' balance is overdue',
                    'message' => 'The remaining balance has been carried into your current family total.',
                    'amount' => $group['total_remaining'],
                    'date' => $group['due_date'],
                    'target_tab' => 'monthly',
                    'month_key' => $group['month_number'],
                    'show_logo' => false,
                ]);
            }
        }

        foreach ($paymentSubmissions->filter(fn ($submission) => $submission->effective_status === 'pending')->take(3) as $submission) {
            $paymentNotifications->push([
                'type' => 'pending',
                'title' => 'Payment receipt received',
                'message' => $submission->submission_number.' is awaiting Finance verification. No need to submit it again.',
                'amount' => (float) $submission->total_amount,
                'date' => $submission->submitted_at,
                'target_tab' => 'transactions',
                'month_key' => null,
                'show_logo' => true,
            ]);
        }

        foreach ($paymentSubmissions->filter(fn ($submission) => $submission->effective_status === 'verified')->take(3) as $submission) {
            $officialReceiptNumber = $officialReceiptNumbersBySubmission->get($submission->id);
            $paymentNotifications->push([
                'type' => 'success',
                'title' => 'PAYMENT VERIFIED',
                'message' => ($officialReceiptNumber ? 'Official Receipt No. '.$officialReceiptNumber : 'Submission '.$submission->submission_number).' was successfully posted to '.$submission->payments->count().' '.str('student account')->plural($submission->payments->count()).'.',
                'amount' => (float) $submission->total_amount,
                'date' => $submission->submitted_at,
                'target_tab' => 'transactions',
                'month_key' => null,
                'show_logo' => true,
            ]);
        }

        foreach ($legacyPayments->where('status', 'pending')->take(max(0, 3 - $paymentSubmissions->count())) as $payment) {
            $paymentNotifications->push([
                'type' => 'pending',
                'title' => 'Receipt awaiting verification',
                'message' => mb_strtoupper($payment->student?->applicant?->full_name ?? 'Student payment'),
                'amount' => (float) $payment->amount,
                'date' => $payment->created_at,
                'target_tab' => 'transactions',
                'month_key' => null,
                'show_logo' => false,
            ]);
        }

        $upcomingGroup = collect($monthlyGroups)->first(
            fn ($group) => $group['unpaid_count'] > 0
                && ! $group['is_overdue']
                && $group['due_date']->isFuture()
        );

        if ($upcomingGroup) {
            // Keep the next payable month above historical receipt updates so
            // parents see the current payment reminder first.
            $paymentNotifications->prepend([
                'type' => 'upcoming',
                'title' => mb_strtoupper($upcomingGroup['month_name']).' '.$upcomingGroup['year'].' payment is coming up',
                'message' => $upcomingGroup['unpaid_count'].' '.str('student')->plural($upcomingGroup['unpaid_count']).' included in this monthly payment.',
                'amount' => $upcomingGroup['total_remaining'],
                'date' => $upcomingGroup['due_date'],
                'target_tab' => 'monthly',
                'month_key' => $upcomingGroup['month_number'],
                'show_logo' => false,
            ]);
        }

        return view('payment.dashboard', compact(
            'user', 'students', 'demoChildren',
            'monthlyGroups', 'familyTotalRemaining', 'familyTotalBalance',
            'firstUnpaidMonthKey', 'payments', 'legacyPayments', 'paymentSubmissions',
            'paymentNotifications', 'nextPayableByStudent', 'familyAdvanceCredit', 'currentPaymentSummary',
            'officialReceiptNumbersBySubmission', 'familyFinanceTransactions', 'unpostedPaymentSubmissions', 'consolidatedFinanceReceipt'
        ));
    }

    /**
     * Show the family-level receipt checkout. Parents never choose a child,
     * billing month, balance, or allocation target.
     */
    public function showCheckout(Request $request, PaymentEligibilityService $paymentEligibility)
    {
        $user = Auth::user();
        $demoChildren = $user->paymentDemoChildren()->orderBy('id')->get();
        $students = $user->students()
            ->with(['applicant', 'account.monthlyBillings.payments'])
            ->get();

        $billings = $students->flatMap(fn ($student) => $student->account?->monthlyBillings ?? collect())
            ->sortBy([['due_date', 'asc'], ['student_id', 'asc'], ['id', 'asc']]);
        $outstanding = $billings->map(function ($billing) use ($paymentEligibility) {
            return [
                'billing' => $billing,
                'remaining' => $paymentEligibility->remainingBalance($billing),
            ];
        })->filter(fn ($item) => $item['remaining'] > 0.01)->values();

        $familyOutstandingBalance = $this->currentAmountLeftToSubmit($user, $paymentEligibility);
        if ($familyOutstandingBalance <= 0.01) {
            return redirect()->route('payment.dashboard')
                ->with('success', 'The full current payable amount is already covered. Wait for Finance verification before uploading another proof.');
        }
        $oldestOutstanding = $outstanding->first();
        $oldestOutstandingMonth = $oldestOutstanding
            ? ($oldestOutstanding['billing']->due_date
                ? strtoupper($oldestOutstanding['billing']->due_date->format('F Y'))
                : null)
            : null;
        if (! $oldestOutstandingMonth && $students->isEmpty() && $demoChildren->isNotEmpty()) {
            $currentMonthEnd = now(config('finance.timezone', 'Asia/Manila'))->endOfMonth();
            $oldestOutstandingMonth = collect(app(DemoPaymentScheduleService::class)->build($demoChildren))
                ->filter(fn ($group) => $group['month_number'] > 0
                    && $group['due_date']->lte($currentMonthEnd)
                    && (float) $group['total_remaining'] > 0.01)
                ->sortBy('due_date')
                ->value('month_label');
        }
        $familyAdvanceCredit = (float) $user->familyAdvanceCredits()
            ->where('status', 'active')
            ->sum('remaining_amount');
        $officialPaymentChannels = config('finance.payment_channels', []);
        $retryPayment = null;
        if ($request->filled('retry')) {
            $retrySubmission = PaymentSubmission::query()
                ->with('receiptSubmission')
                ->where('user_id', $user->id)
                ->where('submission_number', $request->string('retry')->value())
                ->firstOrFail();
            abort_unless($retrySubmission->status === 'rejected', 422, 'Only a rejected payment can be reviewed or re-uploaded.');

            $retryPayment = [
                'id' => $retrySubmission->id,
                'number' => $retrySubmission->submission_number,
                'method' => $retrySubmission->method,
                'payment_mode' => $retrySubmission->payment_mode,
                'account_received' => $retrySubmission->account_received,
                'reference' => $retrySubmission->reference_no,
                'transaction_date' => $retrySubmission->transaction_date?->format('Y-m-d'),
                'transaction_time' => $retrySubmission->transaction_at?->format('H:i'),
                'amount' => (float) $retrySubmission->total_amount,
                'reason' => $retrySubmission->receiptSubmission?->review_reason ?: $retrySubmission->remarks,
                'action' => $request->string('action')->value() === 'edit' ? 'edit' : 'reupload',
            ];
        }

        return view('payment.checkout', compact(
            'user', 'students', 'familyOutstandingBalance', 'oldestOutstandingMonth',
            'familyAdvanceCredit', 'officialPaymentChannels', 'retryPayment'
        ));
    }

    /**
     * Link an existing student to the parent's user account.
     */
    public function linkStudent(Request $request, SiblingDiscountService $discounts)
    {
        $user = Auth::user();
        if ($user->paymentDemoChildren()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This AFPS demo account is isolated from official AMIS student records. Official student linking is disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'student_number' => 'required|string|max:50',
        ]);

        $studentNumber = trim($validated['student_number']);

        // Find student by student number (handling optionally formatted numbers)
        $student = Student::where('student_number', $studentNumber)
            ->orWhere('student_number', str_replace('-', '', $studentNumber))
            ->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'No student record found with the given student number.',
            ], 404);
        }

        $applicant = $student->applicant;
        if (! $applicant) {
            return response()->json([
                'success' => false,
                'message' => 'No application details found for this student.',
            ], 404);
        }

        if ($applicant->user_id === $user->id) {
            $changes = $discounts->syncFamily($user);
            $percentage = (float) (collect($changes)->max('discount_percentage') ?? 0);

            return response()->json([
                'success' => true,
                'message' => $this->familyDiscountMessage('This child is already linked to your account.', $changes, $percentage),
                'eligible_children' => count($changes),
                'discount_percentage' => $percentage,
            ]);
        }

        // Never transfer a child between parent accounts from the parent UI.
        if ($applicant->user_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This student is already connected to another family account. Please contact AMIS Admin for assistance.',
            ], 409);
        }

        $parentEmail = mb_strtolower(trim((string) $user->email));
        $recordedParentEmail = mb_strtolower(trim((string) $applicant->parent_email));
        if ($recordedParentEmail === '' || ! hash_equals($recordedParentEmail, $parentEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'The student ID could not be matched to your parent account. Please contact AMIS Admin to verify the enrollment record.',
            ], 422);
        }

        if (! $student->account) {
            return response()->json([
                'success' => false,
                'message' => 'This student payment account is not ready yet. Please contact AMIS Admin or Finance.',
            ], 422);
        }

        $changes = DB::transaction(function () use ($applicant, $user, $discounts) {
            // student.user_id belongs to the student's own credentials. The
            // applicant user_id identifies the parent family account.
            $applicant->forceFill(['user_id' => $user->id])->save();

            return $discounts->syncFamily($user);
        });
        $percentage = (float) (collect($changes)->max('discount_percentage') ?? 0);

        return response()->json([
            'success' => true,
            'message' => $this->familyDiscountMessage('Child linked successfully!', $changes, $percentage),
            'eligible_children' => count($changes),
            'discount_percentage' => $percentage,
        ]);
    }

    private function familyDiscountMessage(string $prefix, array $changes, float $percentage): string
    {
        $childCount = count($changes);
        if ($childCount === 0 || $percentage <= 0) {
            return $prefix.' Family balances were refreshed.';
        }

        return $prefix.' The '.number_format($percentage, 0).'% sibling discount was applied to all '
            .$childCount.' eligible '.str('child')->plural($childCount).'.';
    }

    /**
     * Live OCR scan endpoint: scan an uploaded receipt image and return
     * detected reference, amount, and date for client-side auto-fill.
     */
    public function ocrScan(
        Request $request,
        ReceiptClassificationService $receiptClassifier,
        ReceiptProductionOcrService $productionOcr,
        ReceiptFingerprintService $fingerprints
    ) {
        $request->validate([
            'receipt' => 'required|file|image|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:10240',
            'receipt_submission_id' => 'nullable|uuid|exists:receipt_submissions,submission_id',
        ]);

        try {
            $file = $request->file('receipt');
            $tmpPath = $file->getRealPath();
            $perceptualHash = $fingerprints->differenceHash($tmpPath);

            $analysis = $productionOcr->analyze($tmpPath);
            $fields = $analysis['fields'];
            $rawText = collect($analysis['attempts'])->pluck('raw_text')->filter()->implode("\n");
            $ocr = [
                'success' => $analysis['ocr_status'] !== 'OCR_FAILED',
                'status' => $analysis['ocr_status'] === 'OCR_FAILED' ? 'failed' : 'processed',
                'raw_text' => $rawText,
                'detected_ref' => $fields['reference_number'] ?? null,
                'detected_amount' => $fields['amount'] ?? null,
                'detected_datetime' => $fields['transaction_date'] ?? null,
                'detected_sender' => $fields['sender_name'] ?? null,
                'detected_receiver' => $fields['receiver_name'] ?? null,
                'detected_method' => $fields['provider'] ?? null,
                'detected_account' => $fields['receiving_bank'] ?? null,
            ];
            $classification = $receiptClassifier->classify($ocr);

            $detectedDate = $ocr['detected_datetime'];
            if (! $detectedDate && $ocr['raw_text']) {
                $patterns = [
                    '/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2},?\s+\d{4}/i',
                    '/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}\b/',
                    '/\b\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\b/',
                ];
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $ocr['raw_text'], $m)) {
                        $detectedDate = $m[0];
                        break;
                    }
                }
            }

            return response()->json([
                'success' => $ocr['success'],
                'detected_ref' => $ocr['detected_ref'],
                'detected_amount' => $ocr['detected_amount'],
                'detected_date' => $detectedDate,
                'detected_time' => $fields['transaction_time'] ?? null,
                'detected_sender' => $ocr['detected_sender'] ?? null,
                'detected_receiver' => $ocr['detected_receiver'] ?? null,
                'detected_merchant' => $ocr['detected_merchant'] ?? null,
                'detected_method' => $ocr['detected_method'] ?? null,
                'detected_account' => $ocr['detected_account'] ?? null,
                'has_qr' => $ocr['has_qr'] ?? false,
                'perceptual_hash' => $perceptualHash,
                'document_type' => $classification['type'],
                'document_message' => $classification['message'],
            ]);
        } catch (\Throwable $e) {
            Log::error('OCR pre-scan error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'OCR scan failed.'], 500);
        }
    }

    /**
     * Keep an auditable metadata record after OCR finishes. Identity and
     * student names are resolved on the server instead of trusted from JS.
     */
    public function recordReceiptScan(Request $request)
    {
        $validated = $request->validate([
            'scan_token' => 'required|uuid',
            'billing_ids' => 'sometimes|array|max:20',
            'billing_ids.*' => 'required|integer|distinct|exists:soa_monthly_billings,id',
            'receiving_channel' => 'nullable|string|in:gcash,maya,bdo',
            'receiving_account' => 'nullable|string|max:100',
            'payment_mode' => 'required|string|in:gcash,maya,bdo_online,bdo_otc,bank_transfer,remittance',
            'reference_no' => 'required|string|max:100',
            'transaction_date' => 'required|date_format:Y-m-d',
            'transaction_time' => 'nullable|date_format:H:i',
            'detected_amount' => 'required|numeric|min:1|max:999999',
            'expected_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'ocr_engine' => 'nullable|string|max:100',
            'ocr_passes' => 'required|integer|min:0|max:20',
            'ocr_confidence' => 'nullable|numeric|min:0|max:1',
            'document_status' => 'required|string|in:waiting,pass,warning,fail',
            'image_quality_status' => 'required|string|in:waiting,pass,warning,fail',
            'amount_status' => 'required|string|in:waiting,pass,warning,fail',
            'date_status' => 'required|string|in:waiting,pass,warning,fail',
            'duplicate_status' => 'required|string|in:waiting,pass,warning,fail',
            'scan_status' => 'required|string|in:complete,manual_review,rejected',
            'risk_codes' => 'nullable|array|max:30',
            'risk_codes.*' => 'string|max:60',
            'receipt_hash' => ['nullable', 'regex:/^[a-f0-9]{64}$/'],
            'perceptual_hash' => ['nullable', 'regex:/^[a-f0-9]{16}$/'],
        ]);

        $user = Auth::user();
        $billingIds = collect($validated['billing_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $students = $user->students()->with('applicant')->get();
        $ownedBillingIds = SoaMonthlyBilling::query()
            ->whereIn('id', $billingIds)
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        if ($ownedBillingIds->all() !== $billingIds->sort()->values()->all()) {
            return response()->json(['message' => 'A receipt scan billing record does not belong to your account.'], 403);
        }

        $studentNames = $students->map(function ($student) {
            $applicant = $student->applicant;

            return $applicant
                ? mb_strtoupper($applicant->full_name)
                : 'STUDENT #'.$student->id;
        })->sort()->values()->all();

        $scanValues = $validated;
        unset($scanValues['transaction_time']);

        $scan = ReceiptScanLog::updateOrCreate(
            ['scan_token' => $validated['scan_token'], 'user_id' => $user->id],
            array_merge($scanValues, [
                'parent_full_name' => mb_strtoupper((string) $user->name),
                'student_names' => $studentNames,
                'billing_ids' => $ownedBillingIds->all(),
                'reference_no' => filled($validated['reference_no'] ?? null)
                    ? mb_strtoupper(trim($validated['reference_no']))
                    : null,
                'transaction_at' => filled($validated['transaction_date'] ?? null)
                    ? Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $validated['transaction_date'].' '.($validated['transaction_time'] ?? '12:00'),
                        config('finance.timezone', 'Asia/Manila')
                    )
                    : null,
                'scanned_at' => now(config('finance.timezone', 'Asia/Manila')),
            ])
        );

        return response()->json(['success' => true, 'scan_id' => $scan->id]);
    }

    /**
     * Give the parent an early duplicate warning. Submission performs the
     * authoritative duplicate check again inside the locked transaction.
     */
    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'reference_no' => 'nullable|string|max:100|required_without:receipt_hash',
            'receipt_hash' => 'nullable|string|size:64|required_without:reference_no',
            'perceptual_hash' => ['nullable', 'regex:/^[a-f0-9]{16}$/'],
            'retry_submission_id' => 'nullable|integer|exists:payment_submissions,id',
        ]);

        $retrySubmission = filled($validated['retry_submission_id'] ?? null)
            ? PaymentSubmission::query()
                ->whereKey($validated['retry_submission_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'rejected')
                ->firstOrFail()
            : null;

        $reference = Str::of($validated['reference_no'] ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();

        $query = PaymentSubmission::query()
            ->when($retrySubmission, fn ($query) => $query->where('id', '!=', $retrySubmission->id));
        $duplicate = (clone $query)
            ->where(function ($query) use ($validated, $reference) {
                if ($reference !== '') {
                    $query->where('reference_normalized', $reference);
                }
                if (filled($validated['receipt_hash'] ?? null)) {
                    $reference !== ''
                        ? $query->orWhere('receipt_hash', $validated['receipt_hash'])
                        : $query->where('receipt_hash', $validated['receipt_hash']);
                }
            })
            ->exists();

        if (! $duplicate && $reference !== '') {
            $duplicate = $this->financeReferenceExists($reference);
        }
        if (! $duplicate && filled($validated['receipt_hash'] ?? null)) {
            $duplicate = $this->financeReceiptHashExists($validated['receipt_hash']);
        }

        $possibleReuse = false;
        if (! $duplicate && filled($validated['perceptual_hash'] ?? null)) {
            $fingerprints = app(ReceiptFingerprintService::class);
            $possibleReuse = (clone $query)->whereNotNull('perceptual_hash')->pluck('perceptual_hash')
                ->contains(fn ($hash) => $fingerprints->hammingDistance($validated['perceptual_hash'], $hash) <= 7);
        }

        return response()->json([
            'duplicate' => $duplicate,
            'possible_reuse' => $possibleReuse,
            'code' => $duplicate ? 'DUPLICATE_REFERENCE_OR_RECEIPT' : ($possibleReuse ? 'POSSIBLE_REUSED_RECEIPT' : null),
            'message' => $duplicate
                ? 'This reference or receipt was already submitted. Please check Transactions.'
                : ($possibleReuse ? 'A visually similar receipt needs Finance review.' : 'No duplicate payment found.'),
        ]);
    }

    /** Submit one unallocated family receipt for Finance verification. */
    public function submitPayment(
        Request $request,
        ReceiptClassificationService $receiptClassifier,
        ReceiptFingerprintService $fingerprints,
        AmisReceiptRiskService $riskEngine,
        ReceiptProductionOcrService $productionOcr,
    ) {
        $validated = $request->validate([
            'client_token' => 'required|uuid',
            'method' => 'required|string|in:gcash,maya,bdo',
            'payment_mode' => 'required|string|in:gcash,maya,bdo_online,bdo_otc,bank_transfer,remittance',
            'account_received' => 'required|string|max:100',
            'reference_no' => 'required|string|max:100',
            'transaction_date' => 'required|date_format:Y-m-d',
            'transaction_time' => 'nullable|date_format:H:i',
            'receipt_amount' => 'required|numeric|min:1|max:99999999.99',
            'local_ocr_text' => 'nullable|string|max:50000',
            'local_ocr_confidence' => 'nullable|numeric|min:0|max:1',
            'local_detected_method' => 'nullable|string|max:40',
            'local_detected_account' => 'nullable|string|max:100',
            'local_detected_receiver' => 'nullable|string|max:150',
            'receipt' => 'required|file|image|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:10240',
            'receipt_submission_id' => 'nullable|uuid|exists:receipt_submissions,submission_id',
            'retry_submission_id' => 'nullable|integer|exists:payment_submissions,id',
        ]);

        $user = Auth::user();
        $retrySubmission = filled($validated['retry_submission_id'] ?? null)
            ? PaymentSubmission::query()
                ->whereKey($validated['retry_submission_id'])
                ->where('user_id', $user->id)
                ->where('status', 'rejected')
                ->firstOrFail()
            : null;
        $pipelineReceipt = filled($validated['receipt_submission_id'] ?? null)
            ? ReceiptSubmission::query()
                ->where('submission_id', $validated['receipt_submission_id'])
                ->where('user_id', $user->id)
                ->firstOrFail()
            : null;
        if ($pipelineReceipt?->status === ReceiptSubmission::REUPLOAD_REQUIRED) {
            throw ValidationException::withMessages(['receipt' => $pipelineReceipt->review_reason ?: 'Please upload a clearer original receipt.']);
        }
        $financeTimezone = config('finance.timezone', 'Asia/Manila');
        $transactionAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['transaction_date'].' '.($validated['transaction_time'] ?? '12:00'),
            $financeTimezone
        );
        $transactionDate = $transactionAt->copy()->startOfDay();
        $today = now($financeTimezone);

        if ($transactionAt->year > $today->year) {
            throw ValidationException::withMessages([
                'transaction_date' => "The transaction year cannot be later than {$today->year}. Please check the receipt date.",
            ]);
        }

        $allowedAccounts = collect(config("finance.payment_channels.{$validated['method']}.accounts", []))
            ->pluck('number')
            ->map(fn ($number) => preg_replace('/\s+/', '', $number));
        $submittedAccount = preg_replace('/\s+/', '', $validated['account_received']);

        if (! $allowedAccounts->contains($submittedAccount)) {
            throw ValidationException::withMessages([
                'account_received' => 'The selected receiving account is not on the official AMIS account list.',
            ]);
        }

        $existingSubmission = PaymentSubmission::query()
            ->where('user_id', $user->id)
            ->where('client_token', $validated['client_token'])
            ->first();

        if ($existingSubmission) {
            return $this->successfulSubmissionResponse($existingSubmission, true);
        }

        if ($this->currentAmountLeftToSubmit($user) <= 0.01) {
            throw ValidationException::withMessages([
                'receipt' => 'The full current payable amount is already covered by verified or active pending payments.',
            ]);
        }

        $referenceNormalized = Str::of($validated['reference_no'])
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();

        if ($referenceNormalized === '') {
            throw ValidationException::withMessages([
                'reference_no' => 'Please enter the reference or transaction number shown on the receipt.',
            ]);
        }

        $duplicateReference = PaymentSubmission::query()
            ->where('reference_normalized', $referenceNormalized)
            ->when($retrySubmission, fn ($query) => $query->where('id', '!=', $retrySubmission->id))
            ->exists()
            || $this->financeReferenceExists($referenceNormalized);

        if ($duplicateReference) {
            throw ValidationException::withMessages([
                'reference_no' => 'This transaction/reference number has already been submitted. Check Transactions for its status.',
            ]);
        }

        // The receipt amount is stored at family level. Nothing is allocated
        // until Finance approves the receipt.
        $totalAmount = round((float) $validated['receipt_amount'], 2);

        $file = $request->file('receipt');
        $receiptHash = hash_file('sha256', $file->getRealPath());
        if (! $pipelineReceipt) {
            // Recover the asynchronous upload when an older/cached checkout
            // script omitted receipt_submission_id. The exact file hash and
            // owner make this link deterministic without trusting the client.
            $pipelineReceipt = ReceiptSubmission::query()
                ->where('user_id', $user->id)
                ->where('receipt_hash', $receiptHash)
                ->whereDoesntHave('paymentSubmission')
                ->latest('id')
                ->first();
        }
        if ($pipelineReceipt?->status === ReceiptSubmission::REUPLOAD_REQUIRED) {
            throw ValidationException::withMessages(['receipt' => $pipelineReceipt->review_reason ?: 'Please upload a clearer original receipt.']);
        }
        if ($pipelineReceipt && ! hash_equals($pipelineReceipt->receipt_hash, $receiptHash)) {
            throw ValidationException::withMessages(['receipt' => 'The submitted file does not match the receipt that was verified. Please scan the receipt again.']);
        }
        $perceptualHash = $pipelineReceipt?->perceptual_hash
            ?? $fingerprints->differenceHash($file->getRealPath());
        if (PaymentSubmission::query()
            ->where('receipt_hash', $receiptHash)
            ->when($retrySubmission, fn ($query) => $query->where('id', '!=', $retrySubmission->id))
            ->exists() || $this->financeReceiptHashExists($receiptHash)) {
            throw ValidationException::withMessages([
                'receipt' => 'This receipt image has already been submitted. Check Transactions before trying again.',
            ]);
        }

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = 'family_payment_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8)).'.'.$ext;
        $path = $file->storeAs('receipts', $filename, 'public');

        if (! $path) {
            return response()->json(['success' => false, 'message' => 'Failed to upload proof of payment receipt.'], 500);
        }

        // Reuse the auditable asynchronous pipeline result when available.
        // Legacy callers still receive a server OCR pass for compatibility.
        $ocrStatus = 'skipped';
        $ocrRawText = null;
        $ocrScannedRef = null;
        $ocrScannedAmount = null;
        $ocrConfidence = null;
        $detectedMethod = $validated['local_detected_method'] ?? null;
        $detectedAccount = $validated['local_detected_account'] ?? null;
        $detectedReceiver = $validated['local_detected_receiver'] ?? null;
        $ocrDetectedDate = null;
        $absolutePath = Storage::disk('public')->path($path);
        $ocr = ['success' => false, 'status' => 'unavailable', 'raw_text' => null, 'detected_datetime' => null];

        try {
            if ($pipelineReceipt && ! in_array($pipelineReceipt->status, [ReceiptSubmission::UPLOADED, ReceiptSubmission::PROCESSING], true)) {
                $pipelineReceipt->loadMissing('ocrResults');
                $ocr = [
                    'success' => filled($pipelineReceipt->reference_number) || $pipelineReceipt->amount !== null,
                    'status' => 'processed',
                    'raw_text' => $pipelineReceipt->ocrResults->pluck('raw_text')->filter()->implode("\n"),
                    'detected_ref' => $pipelineReceipt->reference_number,
                    'detected_amount' => $pipelineReceipt->amount !== null ? (float) $pipelineReceipt->amount : null,
                    'detected_datetime' => $pipelineReceipt->transaction_date?->format('Y-m-d'),
                    'detected_method' => $pipelineReceipt->provider,
                    'detected_account' => null,
                    'detected_receiver' => $pipelineReceipt->receiver_name,
                    'confidence' => $pipelineReceipt->ocr_confidence !== null ? (float) $pipelineReceipt->ocr_confidence : null,
                ];
                $ocrStatus = strtolower($pipelineReceipt->status);
            } elseif (! $pipelineReceipt) {
                $analysis = $productionOcr->analyze($absolutePath);
                $fields = $analysis['fields'];
                $ocr = [
                    'success' => $analysis['ocr_status'] !== 'OCR_FAILED',
                    'status' => $analysis['ocr_status'] === 'OCR_FAILED' ? 'failed' : 'processed',
                    'raw_text' => collect($analysis['attempts'])->pluck('raw_text')->filter()->implode("\n"),
                    'detected_ref' => $fields['reference_number'] ?? null,
                    'detected_amount' => $fields['amount'] ?? null,
                    'detected_datetime' => $fields['transaction_date'] ?? null,
                    'detected_method' => $fields['provider'] ?? null,
                    'detected_account' => $fields['receiving_bank'] ?? null,
                    'detected_receiver' => $fields['receiver_name'] ?? null,
                    'confidence' => $analysis['confidence'],
                ];
            } else {
                // OCR is advisory and may still be running. Keep the original
                // receipt linked and let Finance review it without blocking the
                // parent's completed submission.
                $ocr['status'] = 'processing';
            }

            $ocrStatus = $ocr['status'];
            $ocrRawText = $ocr['raw_text'];
            $ocrScannedRef = $ocr['detected_ref'];
            $ocrScannedAmount = $ocr['detected_amount'];
            $ocrConfidence = $ocr['confidence'] ?? ($validated['local_ocr_confidence'] ?? null);
            $detectedMethod = $ocr['detected_method'] ?? $detectedMethod;
            $detectedAccount = $ocr['detected_account'] ?? $detectedAccount;
            $detectedReceiver = $ocr['detected_receiver'] ?? $detectedReceiver;

            if (! $ocr['success'] && filled($validated['local_ocr_text'] ?? null)) {
                $ocrStatus = 'client_ocr_unverified';
                $ocrRawText = $validated['local_ocr_text'];
                // Use the browser OCR text for the same server-side document
                // classification. The client result is never trusted alone.
                $ocr['raw_text'] = $validated['local_ocr_text'];
            }

            $classification = $receiptClassifier->classify($ocr);
            if ($classification['type'] === 'not_receipt') {
                Storage::disk('public')->delete($path);
                throw ValidationException::withMessages([
                    'receipt' => 'Please upload the actual payment receipt. Statements of account and fee schedules are not accepted as proof of payment.',
                ]);
            }

            $ocrTransactionDate = $this->parseReceiptDate($ocr['detected_datetime'] ?? null);
            $ocrDetectedDate = $ocrTransactionDate;

            // Compare scanned reference & amount vs what parent typed
            if ($ocr['success'] && $ocr['status'] === 'processed') {
                $submittedRef = strtolower(preg_replace('/\s+/', '', $validated['reference_no'] ?? ''));
                $scannedRef = strtolower(preg_replace('/\s+/', '', $ocrScannedRef ?? ''));
                $amountMatches = $ocrScannedAmount !== null
                    && (int) round($ocrScannedAmount * 100) === (int) round($totalAmount * 100);
                $refMatches = $submittedRef && $scannedRef && str_contains($scannedRef, $submittedRef);

                $referenceDisagrees = $submittedRef && $scannedRef
                    && preg_replace('/[^a-z0-9]/', '', $submittedRef) !== preg_replace('/[^a-z0-9]/', '', $scannedRef);

                if ($referenceDisagrees) {
                    $ocrStatus = 'manual_review';
                } elseif ($amountMatches && $refMatches) {
                    $ocrStatus = 'matched';
                } elseif ($amountMatches || $refMatches) {
                    $ocrStatus = 'partial_match';
                } else {
                    $ocrStatus = 'mismatch';
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            Log::error('OCR integration error: '.$e->getMessage());
            $ocrStatus = 'failed';
        }
        // ─────────────────────────────────────────────────────────────────────

        $familyStudentIds = $user->students()->pluck('students.id');
        $billingStart = SoaMonthlyBilling::query()
            ->whereIn('student_id', $familyStudentIds)
            ->orderBy('due_date')
            ->value('due_date');
        $billingStart = $billingStart ? Carbon::parse($billingStart)->startOfMonth() : null;
        $risk = $riskEngine->assess([
            'user_id' => $user->id,
            'reference' => $validated['reference_no'],
            'receipt_hash' => $receiptHash,
            'perceptual_hash' => $perceptualHash,
            'transaction_at' => $transactionAt,
            'transaction_date' => $validated['transaction_date'],
            'now' => $today,
            'entered_amount' => $totalAmount,
            'detected_amount' => $ocrScannedAmount,
            'payment_mode' => $validated['payment_mode'],
            'detected_method' => $detectedMethod,
            'detected_account' => $detectedAccount,
            'detected_receiver' => $detectedReceiver,
            'ocr_confidence' => $ocrConfidence,
            'billing_start' => $billingStart,
            'possible_crop' => ($ocr['success'] ?? false) && (! $ocrScannedRef || $ocrScannedAmount === null || ! $ocrDetectedDate),
            'possible_tampering' => $fingerprints->hasSuspiciousEditorMetadata($absolutePath),
            'ignore_submission_id' => $retrySubmission?->id,
        ]);
        if ($risk['status'] === 'blocked') {
            Storage::disk('public')->delete($path);
            $blocked = collect($risk['flags'])->firstWhere('severity', 'block');
            throw ValidationException::withMessages([
                'receipt' => ($blocked['code'] ?? 'RECEIPT_BLOCKED').': '.($blocked['message'] ?? 'This receipt cannot be submitted.'),
            ]);
        }
        if ($risk['status'] === 'manual_review') {
            $ocrStatus = 'manual_review';
        }

        try {
            $submission = DB::transaction(function () use (
                $user,
                $validated,
                $submittedAccount,
                $referenceNormalized,
                $receiptHash,
                $totalAmount,
                $transactionDate,
                $transactionAt,
                $path,
                $ocrStatus,
                $ocrRawText,
                $ocrScannedRef,
                $ocrScannedAmount, $ocrConfidence,
                $perceptualHash,
                $risk,
                $pipelineReceipt,
                $retrySubmission
            ) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                $duplicate = PaymentSubmission::query()
                    ->where(function ($query) use ($validated, $referenceNormalized, $receiptHash) {
                        $query->where('client_token', $validated['client_token'])
                            ->orWhere('reference_normalized', $referenceNormalized)
                            ->orWhere('receipt_hash', $receiptHash);
                    })
                    ->when($retrySubmission, fn ($query) => $query->where('id', '!=', $retrySubmission->id))
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    if ($duplicate->client_token === $validated['client_token']) {
                        return $duplicate;
                    }
                    throw ValidationException::withMessages([
                        'reference_no' => 'This payment or receipt has already been submitted. Check Transactions for its status.',
                    ]);
                }

                if ($this->currentAmountLeftToSubmit($user) <= 0.01) {
                    throw ValidationException::withMessages([
                        'receipt' => 'The full current payable amount became covered by another active payment proof. Refresh the payment page to review it.',
                    ]);
                }

                $submissionValues = [
                    'user_id' => $user->id,
                    'receipt_submission_id' => $pipelineReceipt?->id,
                    'client_token' => $validated['client_token'],
                    'method' => $validated['method'],
                    'payment_mode' => $validated['payment_mode'],
                    'account_received' => $submittedAccount,
                    'reference_no' => $validated['reference_no'],
                    'reference_normalized' => $referenceNormalized,
                    'transaction_date' => $transactionDate,
                    'transaction_at' => $transactionAt,
                    'receipt_hash' => $receiptHash,
                    'perceptual_hash' => $perceptualHash,
                    'total_amount' => $totalAmount,
                    'receipt_url' => $path,
                    'status' => 'pending',
                    'ocr_status' => $ocrStatus,
                    'ocr_confidence' => $ocrConfidence,
                    'risk_status' => $risk['status'],
                    'risk_flags' => $risk['flags'],
                    'risk_checked_at' => now(),
                    'ocr_raw_text' => $ocrRawText,
                    'ocr_scanned_ref' => $ocrScannedRef,
                    'ocr_scanned_amount' => $ocrScannedAmount,
                    'submitted_at' => now(),
                    'remarks' => null,
                ];

                if ($retrySubmission) {
                    $retrySubmission->payments()->where('status', 'rejected')->delete();
                    $retrySubmission->forceFill($submissionValues)->save();
                    $submission = $retrySubmission;
                } else {
                    do {
                        $submissionNumber = 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
                    } while (PaymentSubmission::where('submission_number', $submissionNumber)->exists());
                    $submission = PaymentSubmission::create(['submission_number' => $submissionNumber] + $submissionValues);
                }

                return $submission->load('payments');
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        if ($pipelineReceipt) {
            // A rejected submission may be replaced with the same proof. Once
            // the replacement is linked to that same payment, recalculate the
            // duplicate result so the rejected original cannot leave a stale
            // EXACT_DUPLICATE badge on its own retry.
            $duplicateAssessment = app(ReceiptDuplicateService::class)
                ->check($pipelineReceipt->fresh());
            $pipelineReceipt->forceFill([
                'duplicate_status' => $duplicateAssessment['status'],
                'duplicate_results' => $duplicateAssessment,
            ])->save();

            if (filled($validated['local_ocr_text'] ?? null)) {
                $pipelineReceipt->ocrResults()->create([
                    'engine' => 'Browser Tesseract',
                    'attempt_number' => ((int) $pipelineReceipt->ocrResults()->max('attempt_number')) + 1,
                    'source_variant' => 'client_processed',
                    'status' => 'client_unverified',
                    'raw_text' => $validated['local_ocr_text'],
                    'structured_json' => [
                        'reference_number' => $validated['reference_no'],
                        'amount' => (float) $validated['receipt_amount'],
                        'transaction_date' => $validated['transaction_date'],
                        'payment_mode' => $validated['local_detected_method'] ?? null,
                        'receiver_name' => $validated['local_detected_receiver'] ?? null,
                    ],
                    'confidence' => $validated['local_ocr_confidence'] ?? null,
                    'warnings' => ['CLIENT_RESULT_NOT_AUTHORITATIVE'],
                ]);
            }
            $pipelineReceipt->auditLogs()->create([
                'user_id' => $user->id,
                'event' => 'payment_submission_linked',
                'from_status' => $pipelineReceipt->status,
                'to_status' => $pipelineReceipt->status,
                'changes' => ['payment_submission_id' => $submission->id, 'submission_number' => $submission->submission_number],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $this->successfulSubmissionResponse($submission);
    }

    private function successfulSubmissionResponse(PaymentSubmission $submission, bool $idempotent = false)
    {
        $ocrNote = match ($submission->ocr_status) {
            'matched' => ' Receipt details matched our automatic check.',
            'partial_match' => ' Some receipt details need a Finance double-check.',
            'mismatch' => ' Finance will manually review the receipt details.',
            default => '',
        };

        return response()->json([
            'success' => true,
            'idempotent' => $idempotent,
            'submission_number' => $submission->submission_number,
            'total_amount' => (float) $submission->total_amount,
            'status' => $submission->status,
            'message' => ($idempotent ? 'This payment was already received. ' : 'Payment submitted successfully. ')
                ."Reference {$submission->submission_number} is pending Finance verification."
                .$ocrNote,
        ]);
    }

    private function currentAmountLeftToSubmit(User $user, ?PaymentEligibilityService $paymentEligibility = null): float
    {
        $paymentEligibility ??= app(PaymentEligibilityService::class);
        $currentMonthStart = now(config('finance.timezone', 'Asia/Manila'))->startOfMonth();
        $currentMonthEnd = $currentMonthStart->copy()->endOfMonth();
        $studentIds = $user->students()->pluck('students.id');

        if ($studentIds->isEmpty()) {
            $demoChildren = $user->paymentDemoChildren()->orderBy('id')->get();
            if ($demoChildren->isNotEmpty()) {
                $outstandingThroughCurrentMonth = collect(app(DemoPaymentScheduleService::class)->build($demoChildren))
                    ->filter(fn ($group) => $group['month_number'] > 0 && $group['due_date']->lte($currentMonthEnd))
                    ->sum(fn ($group) => (float) $group['total_remaining']);

                return $this->remainingAfterActivePendingPayments($user, (float) $outstandingThroughCurrentMonth);
            }
        }

        $hasCurrentBilling = SoaMonthlyBilling::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('due_date', [$currentMonthStart, $currentMonthEnd])
            ->exists();
        if (! $hasCurrentBilling) {
            return 0.0;
        }

        $outstandingThroughCurrentMonth = SoaMonthlyBilling::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('due_date', '<=', $currentMonthEnd)
            ->with('payments')
            ->get()
            ->sum(fn ($billing) => $paymentEligibility->remainingBalance($billing));

        return $this->remainingAfterActivePendingPayments($user, (float) $outstandingThroughCurrentMonth);
    }

    private function remainingAfterActivePendingPayments(User $user, float $outstandingThroughCurrentMonth): float
    {
        $activeStatuses = [
            ReceiptSubmission::UPLOADED,
            ReceiptSubmission::PROCESSING,
            ReceiptSubmission::OCR_COMPLETED,
            ReceiptSubmission::PENDING_VERIFICATION,
            ReceiptSubmission::NEEDS_REVIEW,
        ];
        $activePending = PaymentSubmission::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function ($query) use ($activeStatuses) {
                $query->whereDoesntHave('receiptSubmission')
                    ->orWhereHas('receiptSubmission', fn ($receiptQuery) => $receiptQuery->whereIn('status', $activeStatuses));
            })
            ->sum('total_amount');

        return app(CurrentFamilyPaymentCoverageService::class)->calculate(
            $outstandingThroughCurrentMonth,
            0,
            0,
            (float) $activePending
        )['remaining_to_submit'];
    }

    private function financeReferenceExists(string $normalizedReference): bool
    {
        if (! Schema::hasTable('finance_transactions') || ! Schema::hasColumn('finance_transactions', 'reference_number')) {
            return false;
        }

        $cleanRef = Str::lower(preg_replace('/[^A-Za-z0-9]/', '', $normalizedReference));

        return DB::table('finance_transactions')
            ->whereNotNull('reference_number')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(reference_number, '-', ''), ' ', ''), '_', ''), '/', ''), '.', '')) = ?", [$cleanRef])
            ->exists();
    }

    private function financeReceiptHashExists(string $receiptHash): bool
    {
        if (! Schema::hasTable('finance_transactions')
            || ! Schema::hasColumn('finance_transactions', 'receipt_submission_id')
            || ! Schema::hasTable('receipt_submissions')
            || ! Schema::hasColumn('receipt_submissions', 'receipt_hash')) {
            return false;
        }

        return DB::table('finance_transactions as transaction')
            ->join('receipt_submissions as receipt', 'receipt.id', '=', 'transaction.receipt_submission_id')
            ->whereRaw('LOWER(receipt.receipt_hash) = ?', [Str::lower($receiptHash)])
            ->exists();
    }

    private function parseReceiptDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/\b(20\d{2})[\/-](\d{1,2})[\/-](\d{1,2})\b/', $value, $match)) {
            return Carbon::create((int) $match[1], (int) $match[2], (int) $match[3], 0, 0, 0, config('finance.timezone', 'Asia/Manila'));
        }

        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})\b/', $value, $match)) {
            $first = (int) $match[1];
            $second = (int) $match[2];
            $day = $first > 12 ? $first : ($second > 12 ? $second : $first);
            $month = $first > 12 ? $second : ($second > 12 ? $first : $second);

            if (checkdate($month, $day, (int) $match[3])) {
                return Carbon::create((int) $match[3], $month, $day, 0, 0, 0, config('finance.timezone', 'Asia/Manila'));
            }
        }

        try {
            return Carbon::parse($value, config('finance.timezone', 'Asia/Manila'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function paymentEligibilityMessage(array $eligibility): string
    {
        return match ($eligibility['reason']) {
            'previous_balance' => "Payment cannot continue. Please settle the student's {$eligibility['oldest_outstanding_month']} outstanding balance first.",
            'pending_payment' => 'Payment cannot continue. A payment for this billing month is already pending verification.',
            'paid' => 'Payment cannot continue because this billing month is already paid.',
            default => 'Payment cannot continue for this billing month.',
        };
    }
}
