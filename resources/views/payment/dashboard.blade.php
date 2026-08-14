<x-app-layout>
    @php
        $demoChildren = $demoChildren ?? collect();
    @endphp
    <x-slot name="title">Family Payments</x-slot>

    <div
        class="payment-dashboard-page"
        x-data="paymentDashboard()"
        @keydown.escape.window="closeTopModal()"
    >
        <div class="payment-shell">
            <header class="payment-page-header">
                <div>
                    <span class="payment-page-eyebrow">School Year {{ $students->first()?->account?->school_year ?? $demoChildren->first()?->school_year ?? '2026–2027' }}</span>
                    <h1 class="payment-page-title">Family Payments</h1>
                    <p class="payment-page-subtitle">See your family balance, review each student's account, and submit payment receipts in one place.</p>
                </div>
            </header>

            @if($students->isEmpty() && $demoChildren->isEmpty())
                <section class="payment-empty-state" aria-labelledby="empty-students-title">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M18 18.72a9.1 9.1 0 003.74-.48 3 3 0 00-4.68-2.72m.94 3.2v-.01c0-1.09-.28-2.11-.77-3M18 18.72v.78c0 .41-.34.75-.75.75H6.75A.75.75 0 016 19.5v-.78m12 0a9.72 9.72 0 00-6-1.97 9.72 9.72 0 00-6 1.97m0 0a5.98 5.98 0 00-.77-3m0 0a3 3 0 00-4.68 2.72 9.1 9.1 0 003.74.48m.94-3a5.97 5.97 0 0113.54 0M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                        </svg>
                    </div>
                    <h2 id="empty-students-title" class="text-xl font-extrabold text-slate-900">No students linked yet</h2>
                    <p class="mx-auto mb-5 mt-2 max-w-md text-sm leading-6 text-slate-600">Link an existing student using their school ID. AMIS securely matches the parent email recorded in enrollment.</p>
                    <button type="button" class="inline-flex min-h-11 items-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800" @click="showAddStudent = true">
                        Link student account
                    </button>
                </section>
            @else
                @php
                    $pastDueNow = (float) $currentPaymentSummary['previous_balance'];
                    $currentMonthDueNow = max(0, round((float) $currentPaymentSummary['current_charges'] - (float) $currentPaymentSummary['verified_payments'], 2));
                    $familyAmountDueNow = round($pastDueNow + $currentMonthDueNow, 2);
                    $familyRemainingBalance = max(0, round((float) $familyTotalRemaining, 2));
                    $futureScheduledBalance = max(0, round($familyRemainingBalance - $familyAmountDueNow, 2));
                    $currentPaymentMonth = $currentPaymentSummary['month_key'] !== null
                        ? ($monthlyGroups[$currentPaymentSummary['month_key']]['month_name'] ?? null)
                        : null;
                    $currentPaymentMonth = mb_strtoupper($currentPaymentMonth ?: now(config('finance.timezone', 'Asia/Manila'))->format('F'));
                @endphp
                <section class="payment-summary-card" aria-labelledby="family-balance-title">
                    <div class="payment-summary-primary">
                        <span class="payment-section-kicker">Family account overview</span>
                        <span id="family-balance-title" class="payment-summary-label">{{ $demoChildren->isNotEmpty() && $students->isEmpty() ? 'Demo Remaining Balance' : 'Total Remaining Balance' }}</span>
                        <strong class="payment-summary-amount">₱{{ number_format($familyRemainingBalance, 2) }}</strong>
                        <p class="payment-summary-help">{{ $demoChildren->isNotEmpty() && $students->isEmpty() ? 'AFPS-only sample balances. These children are not connected to official AMIS student or enrollment records.' : 'Combined remaining school balance for all enrolled children and all unpaid monthly installments.' }}</p>
                        <p class="payment-summary-due-now"><span>{{ $demoChildren->isNotEmpty() && $students->isEmpty() ? $currentPaymentMonth.' — Demo monthly tuition due' : $currentPaymentMonth.' — Amount requiring payment now' }}</span><strong>₱{{ number_format($familyAmountDueNow, 2) }}</strong></p>
                        @if($demoChildren->isNotEmpty() && $students->isEmpty())
                            <p class="payment-summary-help"><strong>Schedule preview only.</strong> Payment posting stays disabled because these children are not linked to official AMIS records.</p>
                        @endif
                        @if($familyAdvanceCredit > 0)
                            <p class="payment-summary-help"><strong>Advance credit: ₱{{ number_format($familyAdvanceCredit, 2) }}</strong></p>
                        @endif
                    </div>
                    <div class="payment-summary-stats">
                        <div class="payment-summary-stat is-overdue">
                            <span>Past due</span>
                            <strong>₱{{ number_format($pastDueNow, 2) }}</strong>
                        </div>
                        <div class="payment-summary-stat is-current">
                            <span>Current month</span>
                            <strong>₱{{ number_format($currentMonthDueNow, 2) }}</strong>
                        </div>
                        <div class="payment-summary-stat is-future">
                            <span>Future scheduled</span>
                            <strong>₱{{ number_format($futureScheduledBalance, 2) }}</strong>
                        </div>
                        <div class="payment-summary-stat">
                            <span>Students</span>
                            <strong>{{ $students->count() + $demoChildren->count() }} {{ $demoChildren->isNotEmpty() && $students->isEmpty() ? 'demo' : 'enrolled' }}</strong>
                        </div>
                    </div>
                </section>

                @if($demoChildren->isNotEmpty())
                    <aside class="payment-demo-notice" role="status">
                        <strong>AFPS DEMO CHILDREN</strong>
                        <span>Payment-system display data only. No official student, enrollment, or admin.amis.edu.ph record is linked or changed.</span>
                    </aside>
                @endif

                <section class="payment-section" aria-labelledby="students-heading">
                    <div class="payment-section-heading">
                        <div>
                            <span class="payment-section-kicker">Student accounts</span>
                            <h2 id="students-heading" class="payment-section-title">Your linked students / children</h2>
                            <p class="payment-section-description">Open a student account to see its fee breakdown.</p>
                        </div>
                        @if($demoChildren->isEmpty())
                        <button type="button" class="payment-link-child" @click="showAddStudent = true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            Link student account
                        </button>
                        @endif
                    </div>

                    <div class="payment-student-grid">
                        @foreach($students as $student)
                            @php
                                $account = $student->account;
                                $applicant = $student->applicant;
                                $studentName = mb_strtoupper($applicant?->full_name ?? '');
                                $studentName = $studentName ?: 'STUDENT';
                                $hasUploadedPhoto = filled($applicant?->photo_2x2_url);
                                $avatarUrl = $hasUploadedPhoto
                                    ? Storage::disk('public')->url($applicant->photo_2x2_url)
                                    : asset(($applicant?->gender === 'Female')
                                        ? 'images/avatars/student-female-avatar.png'
                                        : 'images/avatars/student-male-avatar.png');
                                $installmentBreakdown = $account?->monthlyBillings
                                    ?->filter(fn ($billing) => (int) $billing->month_number > 0)
                                    ->sortBy(fn ($billing) => $billing->due_date?->timestamp ?? $billing->month_number)
                                    ->map(function ($billing) {
                                        $originalAmount = (float) $billing->amount_due;
                                        $verifiedPaid = $billing->status === 'paid'
                                            ? $originalAmount
                                            : min($originalAmount, (float) $billing->payments->where('status', 'verified')->sum('amount'));
                                        $remainingAmount = max(0, round($originalAmount - $verifiedPaid, 2));
                                        $dueDate = $billing->due_date;
                                        $status = $remainingAmount <= 0.01
                                            ? 'Paid'
                                            : ($dueDate?->isPast()
                                                ? 'Overdue'
                                                : ($dueDate?->isCurrentMonth() ? 'Current' : 'Upcoming'));

                                        return [
                                            'month' => $dueDate ? strtoupper($dueDate->format('F Y')) : strtoupper((string) $billing->month_name),
                                            'due_date' => $dueDate ? strtoupper($dueDate->format('M d, Y')) : null,
                                            'original' => $originalAmount,
                                            'verified' => $verifiedPaid,
                                            'remaining' => $remainingAmount,
                                            'status' => $status,
                                        ];
                                    })
                                    ->values()
                                    ->all() ?? [];
                                $installmentSchedule = collect($installmentBreakdown);
                                $installmentPlanTotal = round((float) $installmentSchedule->sum('original'), 2);
                                $installmentVerified = round((float) $installmentSchedule->sum('verified'), 2);
                                $installmentRemaining = round((float) $installmentSchedule->sum('remaining'), 2);
                                $finalInstallment = (float) ($installmentSchedule->last()['original'] ?? ($account?->monthly_tuition ?? 0));
                                $sId = (string) ($student->student_number ?: $student->id);
                                $studentManualList = (isset($manualSoas) && $manualSoas->has($sId)) ? $manualSoas->get($sId) : collect();
                                if ($studentManualList->isEmpty() && isset($manualSoas)) {
                                    $studentManualList = $manualSoas->get((string) $student->id) ?? collect();
                                }
                                $latestManualSoa = $studentManualList->firstWhere('is_current', true) ?? $studentManualList->first();

                                $breakdown = [
                                    'name' => $studentName,
                                    'avatar' => $avatarUrl,
                                    'avatar_is_fallback' => !$hasUploadedPhoto,
                                    'tuition' => (float) ($account?->tuition_fee ?? 0),
                                    'misc' => (float) ($account?->miscellaneous_fee ?? 0),
                                    'books' => (float) ($account?->books_fee ?? 0),
                                    'discount' => (float) ($account?->discount_amount ?? 0),
                                    'discount_percentage' => (float) ($account?->discount_percentage ?? 0),
                                    'sibling_order' => (int) ($account?->sibling_order ?? 0),
                                    'total' => (float) ($account?->total_balance ?? 0),
                                    'enrollment' => (float) ($account?->enrollment_fee_paid ?? 0),
                                    'remaining' => (float) ($account?->remaining_balance ?? 0),
                                    'installments' => (int) ($account?->installment_months ?? 9),
                                    'monthly' => (float) ($account?->monthly_tuition ?? 0),
                                    'final_installment' => $finalInstallment,
                                    'installment_plan_total' => $installmentPlanTotal,
                                    'installment_verified' => $installmentVerified,
                                    'installment_remaining' => $installmentRemaining,
                                    'installment_breakdown' => $installmentBreakdown,
                                    'next_payment' => $nextPayableByStudent[$student->id] ?? null,
                                    'manual_soa_latest' => $latestManualSoa ? [
                                        'id' => $latestManualSoa->id,
                                        'billing_month' => $latestManualSoa->billing_month,
                                        'version' => $latestManualSoa->version,
                                        'filename' => $latestManualSoa->original_filename,
                                        'size' => $latestManualSoa->formatted_file_size,
                                        'uploaded_at' => $latestManualSoa->created_at->format('M d, Y h:i A'),
                                        'uploaded_by' => $latestManualSoa->uploaded_by,
                                        'remarks' => $latestManualSoa->remarks,
                                        'is_pdf' => $latestManualSoa->is_pdf,
                                        'view_url' => route('payment.manual-soa.view', $latestManualSoa),
                                        'download_url' => route('payment.manual-soa.download', $latestManualSoa),
                                    ] : null,
                                    'manual_soa_history' => $studentManualList->map(fn ($soa) => [
                                        'id' => $soa->id,
                                        'billing_month' => $soa->billing_month,
                                        'version' => $soa->version,
                                        'is_current' => (bool) $soa->is_current,
                                        'filename' => $soa->original_filename,
                                        'size' => $soa->formatted_file_size,
                                        'uploaded_at' => $soa->created_at->format('M d, Y h:i A'),
                                        'uploaded_by' => $soa->uploaded_by,
                                        'remarks' => $soa->remarks,
                                        'is_pdf' => $soa->is_pdf,
                                        'view_url' => route('payment.manual-soa.view', $soa),
                                        'download_url' => route('payment.manual-soa.download', $soa),
                                    ])->values()->all(),
                                ];
                            @endphp

                            <button
                                type="button"
                                class="payment-student-card"
                                aria-label="View account details for {{ $studentName }}"
                                @click="openBreakdown({{ Js::from($breakdown) }})"
                            >
                                <span class="payment-student-avatar">
                                    <img
                                        src="{{ $avatarUrl }}"
                                        alt="Profile avatar of {{ $studentName }}"
                                        class="{{ $hasUploadedPhoto ? '' : 'payment-student-avatar-placeholder' }}"
                                    >
                                </span>
                                <span class="payment-student-info">
                                    <span class="payment-student-name" title="{{ $studentName }}">{{ $studentName }}</span>
                                    <span class="payment-student-meta">{{ $student->grade_level }} · ID {{ $student->student_number }}</span>
                                    @if(($account?->discount_percentage ?? 0) > 0)
                                        <span class="payment-student-discount">{{ number_format((float) $account->discount_percentage, 0) }}% SIBLINGS DISCOUNT</span>
                                    @endif
                                </span>
                                <span class="payment-student-balance">
                                    <span>Balance</span>
                                    <strong>₱{{ number_format($account?->remaining_balance ?? 0, 2) }}</strong>
                                </span>
                            </button>
                        @endforeach
                        @foreach($demoChildren as $demoChild)
                            @php
                                $demoAvatarUrl = asset($demoChild->gender === 'Female'
                                    ? 'images/avatars/student-female-avatar.png'
                                    : 'images/avatars/student-male-avatar.png');
                                $demoInstallmentBreakdown = app(\App\Services\DemoPaymentScheduleService::class)->installmentsFor($demoChild, $demoChildren);
                                $demoRemainingBalance = (float) collect($demoInstallmentBreakdown)->sum('remaining');
                                $demoVerifiedPaid = (float) collect($demoInstallmentBreakdown)->sum('verified');
                                $demoPlanTotal = (float) collect($demoInstallmentBreakdown)->sum('original');
                                $demoFinalInstallment = (float) (collect($demoInstallmentBreakdown)->last()['original'] ?? $demoChild->monthly_tuition);
                                $demoNextPayment = collect($demoInstallmentBreakdown)
                                    ->first(fn ($installment) => (float) $installment['remaining'] > 0.01);
                                $demoId = (string) $demoChild->demo_student_number;
                                $demoManualList = (isset($manualSoas) && $manualSoas->has($demoId)) ? $manualSoas->get($demoId) : collect();
                                if ($demoManualList->isEmpty() && isset($manualSoas)) {
                                    $demoManualList = $manualSoas->get((string) $demoChild->id) ?? collect();
                                }
                                $demoLatestManualSoa = $demoManualList->firstWhere('is_current', true) ?? $demoManualList->first();

                                $demoBreakdown = [
                                    'name' => mb_strtoupper($demoChild->display_name),
                                    'avatar' => $demoAvatarUrl,
                                    'avatar_is_fallback' => true,
                                    'tuition' => (float) $demoChild->tuition_fee,
                                    'misc' => (float) $demoChild->miscellaneous_fee,
                                    'books' => (float) $demoChild->books_fee,
                                    'discount' => (float) $demoChild->discount_amount,
                                    'discount_percentage' => (float) $demoChild->discount_percentage,
                                    'sibling_order' => $loop->iteration,
                                    'total' => (float) $demoChild->total_balance,
                                    'enrollment' => (float) $demoChild->enrollment_fee_paid,
                                    'remaining' => $demoRemainingBalance,
                                    'installments' => (int) $demoChild->installment_months,
                                    'monthly' => (float) $demoChild->monthly_tuition,
                                    'final_installment' => $demoFinalInstallment,
                                    'installment_plan_total' => $demoPlanTotal,
                                    'installment_verified' => $demoVerifiedPaid,
                                    'installment_remaining' => $demoRemainingBalance,
                                    'installment_breakdown' => $demoInstallmentBreakdown,
                                    'next_payment' => $demoNextPayment,
                                    'manual_soa_latest' => $demoLatestManualSoa ? [
                                        'id' => $demoLatestManualSoa->id,
                                        'billing_month' => $demoLatestManualSoa->billing_month,
                                        'version' => $demoLatestManualSoa->version,
                                        'filename' => $demoLatestManualSoa->original_filename,
                                        'size' => $demoLatestManualSoa->formatted_file_size,
                                        'uploaded_at' => $demoLatestManualSoa->created_at->format('M d, Y h:i A'),
                                        'uploaded_by' => $demoLatestManualSoa->uploaded_by,
                                        'remarks' => $demoLatestManualSoa->remarks,
                                        'is_pdf' => $demoLatestManualSoa->is_pdf,
                                        'view_url' => route('payment.manual-soa.view', $demoLatestManualSoa),
                                        'download_url' => route('payment.manual-soa.download', $demoLatestManualSoa),
                                    ] : null,
                                    'manual_soa_history' => $demoManualList->map(fn ($soa) => [
                                        'id' => $soa->id,
                                        'billing_month' => $soa->billing_month,
                                        'version' => $soa->version,
                                        'is_current' => (bool) $soa->is_current,
                                        'filename' => $soa->original_filename,
                                        'size' => $soa->formatted_file_size,
                                        'uploaded_at' => $soa->created_at->format('M d, Y h:i A'),
                                        'uploaded_by' => $soa->uploaded_by,
                                        'remarks' => $soa->remarks,
                                        'is_pdf' => $soa->is_pdf,
                                        'view_url' => route('payment.manual-soa.view', $soa),
                                        'download_url' => route('payment.manual-soa.download', $soa),
                                    ])->values()->all(),
                                ];
                            @endphp
                            <button
                                type="button"
                                class="payment-student-card is-demo"
                                aria-label="View demo account details for {{ $demoChild->display_name }}"
                                @click="openBreakdown({{ Js::from($demoBreakdown) }})"
                            >
                                <span class="payment-student-avatar">
                                    <img src="{{ $demoAvatarUrl }}" alt="Demo avatar of {{ $demoChild->display_name }}" class="payment-student-avatar-placeholder">
                                </span>
                                <span class="payment-student-info">
                                    <span class="payment-student-name" title="{{ $demoChild->display_name }}">{{ mb_strtoupper($demoChild->display_name) }}</span>
                                    <span class="payment-student-meta">{{ $demoChild->grade_level }} · ID {{ $demoChild->demo_student_number }}</span>
                                    <span class="payment-student-demo-badge">AFPS DEMO · NOT AN OFFICIAL RECORD</span>
                                    @if((float) $demoChild->discount_percentage > 0)
                                         <span class="payment-student-discount">{{ number_format((float) $demoChild->discount_percentage, 0) }}% SIBLINGS DISCOUNT</span>
                                    @endif
                                </span>
                                <span class="payment-student-balance">
                                    <span>Demo Balance</span>
                                    <strong>₱{{ number_format($demoRemainingBalance, 2) }}</strong>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <nav class="payment-view-tabs" role="tablist" aria-label="Payment views">
                    <button
                        type="button"
                        role="tab"
                        class="payment-view-tab"
                        :class="activeTab === 'notifications' ? 'is-active' : ''"
                        :aria-selected="(activeTab === 'notifications').toString()"
                        @click="activeTab = 'notifications'"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.85 23.85 0 005.454-1.31A8.97 8.97 0 0118 9.75V9A6 6 0 006 9v.75a8.97 8.97 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.26 24.26 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                        <span><strong>Notifications</strong><small>{{ $paymentNotifications->count() }} important {{ Str::plural('update', $paymentNotifications->count()) }}</small></span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="payment-view-tab"
                        :class="activeTab === 'monthly' ? 'is-active' : ''"
                        :aria-selected="(activeTab === 'monthly').toString()"
                        @click="activeTab = 'monthly'"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 001.5-1.5V6.75a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z"/>
                        </svg>
                        <span><strong>Monthly Payments</strong><small>Balances and payment schedule</small></span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="payment-view-tab"
                        :class="activeTab === 'transactions' ? 'is-active' : ''"
                        :aria-selected="(activeTab === 'transactions').toString()"
                        @click="activeTab = 'transactions'"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14.25l2.25 2.25L15 12.75M6.75 3.75h10.5A2.25 2.25 0 0119.5 6v12A2.25 2.25 0 0117.25 20.25H6.75A2.25 2.25 0 014.5 18V6a2.25 2.25 0 012.25-2.25z"/>
                        </svg>
                        @php
                            $familyTransactionCount = $familyFinanceTransactions->count() + $unpostedPaymentSubmissions->count();
                        @endphp
                        <span><strong>Transactions</strong><small>{{ $familyTransactionCount }} payment {{ Str::plural('record', $familyTransactionCount) }}</small></span>
                    </button>
                </nav>

                <section x-show="activeTab === 'notifications'" x-cloak class="payment-tab-panel payment-section" role="tabpanel" aria-labelledby="notifications-heading">
                    <div class="payment-section-heading">
                        <div>
                            <span class="payment-section-kicker">Account updates</span>
                            <h2 id="notifications-heading" class="payment-section-title">Notifications</h2>
                            <p class="payment-section-description">Important reminders about balances, due months, and receipt verification.</p>
                        </div>
                    </div>

                    @if($paymentNotifications->isNotEmpty())
                        <div class="payment-notification-list">
                            @foreach($paymentNotifications as $notification)
                                @php
                                    $notificationLabel = match($notification['type']) {
                                        'overdue', 'previous' => 'Action needed',
                                        'pending' => 'Under review',
                                        'success' => 'Payment update',
                                        default => 'Upcoming',
                                    };
                                    $notificationAmountLabel = match($notification['type']) {
                                        'overdue', 'previous' => 'Balance due',
                                        'pending' => 'Submitted',
                                        'success' => 'Verified amount',
                                        default => 'Monthly charge',
                                    };
                                @endphp
                                <article
                                    class="payment-notification-card is-{{ $notification['type'] }} w-full text-left"
                                >
                                    <span class="payment-notification-icon" aria-hidden="true">
                                        @if($notification['type'] === 'overdue' || $notification['type'] === 'previous')
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-1.5a9 9 0 11-18 0 9 9 0 0118 0zM12 15.75h.008v.008H12v-.008z"/></svg>
                                        @elseif($notification['type'] === 'pending')
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @elseif($notification['type'] === 'success')
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @else
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 001.5-1.5V6.75a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z"/></svg>
                                        @endif
                                    </span>
                                    <span class="payment-notification-copy">
                                        <span class="payment-notification-meta"><em>{{ $notificationLabel }}</em><small>{{ $notification['date'] ? strtoupper($notification['date']->format('M d, Y')) : '' }}</small></span>
                                        <strong>{{ $notification['title'] }}</strong>
                                        <span>{{ $notification['message'] }}</span>
                                    </span>
                                    <span class="payment-notification-amount"><small>{{ $notificationAmountLabel }}</small><strong>₱{{ number_format($notification['amount'], 2) }}</strong></span>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="payment-empty-state">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-800">You’re all caught up</h3>
                            <p class="mt-2 text-sm text-slate-600">New payment reminders and receipt updates will appear here.</p>
                        </div>
                    @endif
                </section>

                <section x-show="activeTab === 'monthly'" x-cloak class="payment-tab-panel payment-section" role="tabpanel" aria-labelledby="schedule-heading">
                    <div class="payment-section-heading">
                        <div class="payment-section-heading-with-icon">
                            <span class="payment-section-kicker">Payment schedule</span>
                            <h2 id="schedule-heading" class="payment-section-title"><span class="payment-section-heading-icon" aria-hidden="true">₱</span>Monthly payments</h2>
                            <p class="payment-section-description">View the family schedule, then upload one receipt. AMIS allocates verified payments automatically.</p>
                        </div>
                    </div>

                    <aside class="payment-fee-support-banner" aria-label="Tuition fee support">
                        <span class="payment-fee-support-icon" aria-hidden="true">
                            <svg class="h-5 w-5 flex-shrink-0" style="width:22px; height:22px; min-width:22px; min-height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                            </svg>
                        </span>
                        <span class="payment-fee-support-copy">
                            <strong>Does your tuition fee or balance look incorrect?</strong>
                            <small>Contact IT Support and include the student's full name, student ID, affected month, and a screenshot so we can check it quickly.</small>
                        </span>
                        <a href="mailto:zhairel.lingasa@gmail.com?subject=AMIS%20Tuition%20Fee%20or%20Balance%20Concern" class="payment-fee-support-link">
                            <span>Email IT Support</span>
                            <strong>zhairel.lingasa@gmail.com</strong>
                        </a>
                    </aside>

                    @if(empty($monthlyGroups))
                        <div class="payment-empty-state">
                            <h3 class="text-base font-bold text-slate-800">No payment schedule yet</h3>
                            <p class="mt-2 text-sm text-slate-600">The Statement of Account may not have been generated. Please check again later or contact the Finance Office.</p>
                        </div>
                    @else
                        @php
                            $familyAwaitingVerification = $currentPaymentSummary['awaiting_verification'];
                            $orderedMonthlyGroups = collect($monthlyGroups)->sortBy(function ($group) {
                                if ($group['is_overdue'] && $group['unpaid_count'] > 0) return 0;
                                if ($group['is_current']) return 1;
                                if ($group['unpaid_count'] > 0) return 2;
                                return 3;
                            });
                            $automaticAllocationMonths = collect($monthlyGroups)
                                ->filter(fn ($group) => (float) $group['total_remaining'] > 0.01 && ($group['is_overdue'] || $group['is_current']))
                                ->sortBy('due_date')
                                ->map(fn ($group) => $group['month_number'] === 0
                                    ? 'Enrollment / Initial Payment'
                                    : strtoupper($group['due_date']->format('F Y')))
                                ->values();
                            $monthFilterCounts = [
                                'current' => $orderedMonthlyGroups->filter(fn ($group) => $group['unpaid_count'] > 0 && ($group['is_current'] || $group['is_overdue']))->count(),
                                'upcoming' => $orderedMonthlyGroups->filter(fn ($group) => $group['unpaid_count'] > 0 && ! $group['is_current'] && ! $group['is_overdue'])->count(),
                                'paid' => $orderedMonthlyGroups->filter(fn ($group) => $group['unpaid_count'] === 0)->count(),
                            ];
                        @endphp

                        <div class="payment-month-filter" role="tablist" aria-label="Filter monthly payments">
                            @foreach(['current' => 'CURRENT', 'upcoming' => 'UPCOMING', 'paid' => 'PAID'] as $filterValue => $filterLabel)
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="(monthFilter === '{{ $filterValue }}').toString()"
                                    :class="{ 'is-active': monthFilter === '{{ $filterValue }}' }"
                                    @click="monthFilter = '{{ $filterValue }}'; openMonth = null"
                                >
                                    <span>{{ $filterLabel }}</span>
                                    <small>{{ $monthFilterCounts[$filterValue] }}</small>
                                </button>
                            @endforeach
                        </div>

                        @php
                            $currentPaidGroup = $orderedMonthlyGroups->first(
                                fn ($group) => $group['is_current'] && $group['unpaid_count'] === 0
                            );
                            $currentPaidMonthLabel = $currentPaidGroup
                                ? ($currentPaidGroup['month_number'] === 0
                                    ? 'Enrollment / Initial Payment'
                                    : mb_strtoupper($currentPaidGroup['month_label']))
                                : null;
                        @endphp

                        @if($currentPaidGroup)
                            <section x-show="monthFilter === 'paid'" x-cloak class="payment-full-paid-note" role="status" aria-label="{{ $currentPaidMonthLabel }} paid in full">
                                <span class="payment-full-paid-icon" aria-hidden="true">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12.75l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </span>
                                <span class="payment-full-paid-copy">
                                    <strong>Assalamu alaikum! Shukran for completing your {{ $currentPaidMonthLabel }} family payment.</strong>
                                    <small>Finance has verified the full amount, and this month is now paid in full.</small>
                                </span>
                                <button type="button" @click="activeTab = 'transactions'; window.scrollTo({ top: 0, behavior: 'smooth' })">View transactions <span aria-hidden="true">→</span></button>
                            </section>
                        @endif

                        <div class="payment-month-list">
                            @foreach($orderedMonthlyGroups as $monthKey => $group)
                                @php
                                    $isCurrent = $group['is_current'];
                                    $allPaid = $group['unpaid_count'] === 0;
                                    $isOverdue = !$isCurrent && $group['is_overdue'];
                                    $hasAdvanceApplied = ! $allPaid && ! $isCurrent && ! $isOverdue && (float) $group['total_paid'] > 0.01;
                                    $showPaymentStats = $isOverdue || $isCurrent || $hasAdvanceApplied;
                                    $mainAmountLabel = $allPaid
                                        ? 'Paid in full'
                                        : ($isOverdue ? 'Past due' : ($isCurrent ? 'Current balance' : ($hasAdvanceApplied ? 'Remaining scheduled' : 'Scheduled charges')));
                                    $mainAmount = $allPaid
                                        ? 0
                                        : $group['total_remaining'];
                                    $monthLabel = $group['month_number'] === 0 ? 'Enrollment / Initial Payment' : mb_strtoupper($group['month_label']);
                                @endphp

                                <article
                                    class="payment-month-card {{ $allPaid ? 'is-paid' : ($isOverdue ? 'is-overdue' : ($isCurrent ? 'is-current' : 'is-upcoming')) }}"
                                    :class="{ 'is-open': openMonth === {{ Js::from($monthKey) }} }"
                                    x-show="monthFilter === '{{ $allPaid ? 'paid' : (($isCurrent || $isOverdue) ? 'current' : 'upcoming') }}'"
                                    x-cloak
                                >
                                    <button
                                        type="button"
                                        class="payment-month-toggle"
                                        @click="openMonth = openMonth === {{ Js::from($monthKey) }} ? null : {{ Js::from($monthKey) }}"
                                        :aria-expanded="(openMonth === {{ Js::from($monthKey) }}).toString()"
                                        aria-controls="payment-month-panel-{{ $monthKey }}"
                                    >
                                        <span class="payment-month-copy">
                                            <span class="payment-month-title-row">
                                                <span class="payment-month-name">{{ $monthLabel }}</span>
                                                @if($allPaid)
                                                    <span class="payment-status payment-status-paid">Paid</span>
                                                @elseif($isOverdue)
                                                    <span class="payment-status payment-status-overdue">Overdue</span>
                                                @elseif($isCurrent)
                                                    <span class="payment-status payment-status-current">Current</span>
                                                @else
                                                    <span class="payment-status payment-status-upcoming">Upcoming</span>
                                                @endif
                                            </span>
                                            <span class="payment-month-meta">Due {{ strtoupper($group['due_date']->format('F Y')) }}</span>
                                        </span>

                                        <span class="payment-month-balance">
                                            <small>{{ $mainAmountLabel }}</small>
                                            <strong>₱{{ number_format($mainAmount, 2) }}</strong>
                                        </span>

                                        @if($showPaymentStats)
                                            <span class="payment-month-quick-stats">
                                                <span><small>{{ $hasAdvanceApplied ? 'Original scheduled' : ($isOverdue ? 'Original charges' : 'Monthly charges') }}</small><strong>₱{{ number_format($group['total_due'], 2) }}</strong></span>
                                                <span class="is-paid"><small>{{ $hasAdvanceApplied ? 'Advance applied' : 'Paid' }}</small><strong>₱{{ number_format($group['total_paid'], 2) }}</strong></span>
                                            </span>
                                        @endif

                                        <span class="payment-month-breakdown-prompt {{ $showPaymentStats ? '' : 'is-compact' }}">
                                            <strong>{{ count($group['children']) }} {{ Str::plural('Student', count($group['children'])) }}</strong>
                                            <small x-text="openMonth === {{ Js::from($monthKey) }} ? 'Hide breakdown' : 'View breakdown'"></small>
                                        </span>

                                        <span class="payment-month-chevron" aria-hidden="true">
                                            <svg :class="openMonth === {{ Js::from($monthKey) }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </span>
                                    </button>

                                    <div
                                        id="payment-month-panel-{{ $monthKey }}"
                                        x-show="openMonth === {{ Js::from($monthKey) }}"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 -translate-y-1"
                                        class="payment-month-body"
                                    >
                                        <div class="payment-month-student-list">
                                            <div class="payment-billing-breakdown" aria-label="{{ $monthLabel }} student fee breakdown">
                                                @foreach($group['children'] as $child)
                                                    @php
                                                        $childPaymentStatus = $child['is_paid']
                                                            ? 'PAID'
                                                            : ((float) $child['verified_paid'] > 0.01 ? 'PARTIAL' : 'UNPAID');
                                                    @endphp
                                                    <div class="payment-billing-row payment-child-fee-card is-child-{{ ($loop->index % 4) + 1 }}">
                                                        <span class="payment-child-identity">
                                                            <span class="payment-child-sequence" aria-label="Child {{ $loop->iteration }}">{{ $loop->iteration }}</span>
                                                            <span class="payment-child-identity-copy">
                                                                <strong>{{ mb_strtoupper($child['full_name']) }}</strong>
                                                                <small>{{ $child['grade_level'] }} · ID {{ $child['student_number'] }}</small>
                                                            </span>
                                                        </span>
                                                        <span class="payment-billing-figures">
                                                            <span class="payment-billing-amount">
                                                                <small>Remaining Balance</small>
                                                                <strong>₱{{ number_format($child['remaining_amount'], 2) }}</strong>
                                                            </span>
                                                            <span class="payment-billing-paid-column">
                                                                <small>Total Payment</small>
                                                                <strong>₱{{ number_format($child['verified_paid'], 2) }}</strong>
                                                            </span>
                                                            <span class="payment-billing-status-column">
                                                                <small>Payment Status</small>
                                                                <em class="payment-child-status is-{{ strtolower($childPaymentStatus) }}">{{ $childPaymentStatus }}</em>
                                                            </span>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                    </div>
                                </article>

                        @if($isCurrent && ! $allPaid && $currentPaymentSummary['month_key'] !== null)
                            <section x-show="monthFilter === 'current'" x-cloak class="payment-proof-section is-family-payment" aria-label="Submit a family payment">
                                @if($automaticAllocationMonths->isNotEmpty())
                                    <div class="payment-upload-allocation-info" role="note">
                                        <span class="payment-upload-allocation-copy">
                                            <strong>Automatic payment allocation</strong>
                                            <p>
                                                After Finance approval, AMIS applies the payment to <b>{{ $automaticAllocationMonths->first() }}</b>
                                                @if($automaticAllocationMonths->count() > 1)
                                                    first, then to <b>{{ $automaticAllocationMonths->get(1) }}</b>
                                                @endif.
                                                No student or month selection is needed.
                                            </p>
                                        </span>
                                    </div>
                                @endif

                                @if($currentPaymentSummary['proofs']->isNotEmpty())
                                    <div class="payment-pending-proof-list" aria-label="Payments awaiting Finance review">
                                        @foreach($currentPaymentSummary['proofs'] as $proof)
                                            <article class="payment-pending-proof-card">
                                                <a href="{{ $proof['view_url'] }}" target="_blank" rel="noopener" class="payment-pending-proof-image" aria-label="Open uploaded payment proof">
                                                    <img src="{{ $proof['view_url'] }}" alt="Uploaded payment proof {{ $proof['number'] }}">
                                                </a>
                                                <span class="payment-pending-proof-copy">
                                                    <em>Pending Finance Review</em>
                                                    <strong>{{ $proof['filename'] }}</strong>
                                                    <small>{{ $proof['number'] }} · {{ $proof['method_label'] }}</small>
                                                    <small>Your payment has not been deducted yet.</small>
                                                </span>
                                                <span class="payment-pending-proof-amount"><small>Submitted amount</small><strong>₱{{ number_format($proof['amount'], 2) }}</strong><a href="{{ $proof['view_url'] }}" target="_blank" rel="noopener">View uploaded proof</a></span>
                                            </article>
                                        @endforeach
                                    </div>
                                @elseif($currentPaymentSummary['rejected_proof'])
                                    @php
                                        $rejectedProof = $currentPaymentSummary['rejected_proof'];
                                    @endphp
                                    <article class="payment-rejected-proof-card" role="alert">
                                        <a href="{{ $rejectedProof['view_url'] }}" target="_blank" rel="noopener" class="payment-pending-proof-image" aria-label="Open rejected payment proof">
                                            <img src="{{ $rejectedProof['view_url'] }}" alt="Payment proof requiring attention">
                                        </a>
                                        <span class="payment-pending-proof-copy">
                                            <em>{{ $rejectedProof['status'] }}</em>
                                            <strong>{{ $rejectedProof['filename'] }}</strong>
                                            <small>{{ $rejectedProof['reason'] }}</small>
                                        </span>
                                        <span class="payment-rejected-proof-actions">
                                            <a href="{{ $rejectedProof['edit_url'] }}">Review details</a>
                                            <a href="{{ $rejectedProof['reupload_url'] }}" class="is-primary">Re-upload receipt</a>
                                        </span>
                                    </article>
                                @endif

                                <div class="payment-proof-submit-block">
                                    <div class="payment-proof-amount-left"><span>Total Amount Due</span><strong>₱{{ number_format($familyAmountDueNow, 2) }}</strong></div>

                                    @if($currentPaymentSummary['active_pending_payments'] > 0.01)
                                        <p class="payment-proof-upload-locked"><span><strong>Receipt awaiting Finance review</strong>Upload is unavailable while Finance reviews this receipt. It will return if the receipt is rejected.</span></p>
                                    @elseif($currentPaymentSummary['rejected_proof'])
                                        <p class="payment-proof-upload-locked is-rejected"><span><strong>Action needed</strong>Review the details or upload a replacement receipt to continue.</span></p>
                                    @elseif($familyAwaitingVerification)
                                        <p class="payment-proof-covered"><span aria-hidden="true">✓</span><span><strong>Awaiting verification</strong>Your submitted payments currently cover the amount due and are awaiting Finance verification.</span></p>
                                    @elseif($currentPaymentSummary['remaining_to_submit'] > 0.01)
                                        <div class="payment-proof-action">
                                            <a href="{{ route('payment.checkout') }}"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16.5V4.5m0 0L7.5 9M12 4.5L16.5 9M4.5 15.75v2.625A1.125 1.125 0 005.625 19.5h12.75a1.125 1.125 0 001.125-1.125V15.75"/></svg>Upload Payment Proof</a>
                                        </div>
                                    @endif
                                </div>
                            </section>
                        @endif
                            @endforeach

                            @foreach(['current' => 'No balance currently requires payment.', 'upcoming' => 'No upcoming monthly payments.', 'paid' => 'No fully paid months yet.'] as $filterValue => $emptyMessage)
                                @if($monthFilterCounts[$filterValue] === 0)
                                    <div x-show="monthFilter === '{{ $filterValue }}'" x-cloak class="payment-month-filter-empty">{{ $emptyMessage }}</div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>

                <section x-show="activeTab === 'transactions'" x-cloak class="payment-tab-panel payment-section" role="tabpanel" aria-labelledby="history-heading">
                        <div class="payment-section-heading">
                            <div>
                                <span class="payment-section-kicker">Payment history</span>
                                <h2 id="history-heading" class="payment-section-title">Transactions</h2>
                                <p class="payment-section-description">All approved online and onsite payments remain listed under their permanent OR numbers.</p>
                            </div>
                        </div>
                    @if($unpostedPaymentSubmissions->isNotEmpty() || $familyFinanceTransactions->isNotEmpty())
                        <div class="mb-4 flex flex-wrap gap-2" aria-label="Filter transactions">
                            @foreach(['all' => 'All', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $filterValue => $filterLabel)
                                <button type="button" class="rounded-full border px-4 py-2 text-sm font-bold" :class="transactionFilter === '{{ $filterValue }}' ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-600'" @click="transactionFilter = '{{ $filterValue }}'">{{ $filterLabel }}</button>
                            @endforeach
                        </div>
                        <div class="space-y-3">
                            @foreach($unpostedPaymentSubmissions as $submission)
                                @php
                                    $effectiveStatus = $submission->effective_status;
                                    $displayPaymentNumber = $submission->submission_number;
                                    $historyStatus = mb_strtoupper(str_replace('_', ' ', $effectiveStatus));
                                    $historyStatusClass = match($effectiveStatus) {
                                        'rejected' => 'bg-rose-50 text-rose-700',
                                        default => 'bg-amber-50 text-amber-700',
                                    };
                                    $pendingMethod = strtoupper(str_replace([' ', '-'], '_', (string) ($submission->payment_mode ?: $submission->method)));
                                    $pendingMethodLabel = match(true) {
                                        $pendingMethod === 'GCASH' => 'GCash',
                                        $pendingMethod === 'MAYA' => 'Maya',
                                        str_starts_with($pendingMethod, 'BDO') => 'BDO',
                                        in_array($pendingMethod, ['BANK', 'BANK_TRANSFER', 'OTHER_BANK', 'INSTAPAY', 'PESONET'], true) => 'Bank Transfer',
                                        $pendingMethod === 'REMITTANCE' => 'Remittance',
                                        default => 'Other',
                                    };
                                    $transactionData = [
                                        'is_consolidated' => false,
                                        'number' => $displayPaymentNumber,
                                        'official_receipt_number' => null,
                                        'submission_number' => $submission->submission_number,
                                        'date' => $submission->submitted_at ? strtoupper($submission->submitted_at->format('F j, Y · h:i A')) : null,
                                        'receipt_date' => $submission->submitted_at ? strtoupper($submission->submitted_at->format('F j, Y')) : null,
                                        'payer' => $user->name,
                                        'source' => 'Online Payment',
                                        'method' => $pendingMethodLabel,
                                        'transaction_date' => $submission->transaction_date ? strtoupper($submission->transaction_date->format('F j, Y')) : null,
                                        'account' => $submission->account_received,
                                        'reference' => $submission->reference_no,
                                        'total' => (float) $submission->total_amount,
                                        'advance_credit' => (float) ($submission->advanceCredit?->remaining_amount ?? 0),
                                        'status' => $effectiveStatus,
                                        'remarks' => $submission->remarks,
                                        'receipt' => Storage::disk('public')->url($submission->receipt_url),
                                        'allocation_count' => 0,
                                        'allocations' => [],
                                        'covered_students' => [],
                                        'covered_months' => [],
                                        'balance_after' => null,
                                        'itemized_charges_total' => 0,
                                        'applied_total' => 0,
                                        'itemized_remaining_total' => 0,
                                        'payments' => [],
                                    ];
                                @endphp
                                <button
                                    type="button"
                                    x-show="transactionFilter === 'all' || transactionFilter === '{{ $effectiveStatus }}'"
                                    class="payment-transaction-card flex w-full flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-left shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                                    @click="openTransaction({{ Js::from($transactionData) }})"
                                >
                                    <span class="payment-transaction-copy min-w-0">
                                        <span class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Submission No.</span>
                                        <strong class="mt-0.5 block text-sm text-slate-900">{{ $displayPaymentNumber }}</strong>
                                        <span class="mt-1 flex flex-wrap gap-1.5"><span class="payment-source-badge is-online">ONLINE PAYMENT</span><span class="payment-method-badge">{{ mb_strtoupper($pendingMethodLabel) }}</span></span>
                                        <span class="mt-1 block text-xs text-slate-500">Submitted {{ $submission->submitted_at ? strtoupper($submission->submitted_at->format('M d, Y')) : '' }} · Ref {{ $submission->reference_no ?: 'Not recorded' }}</span>
                                    </span>
                                    <span class="payment-transaction-summary flex flex-shrink-0 items-center gap-4 text-right">
                                        <span><strong class="block text-base text-slate-900">₱{{ number_format($submission->total_amount, 2) }}</strong><span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $historyStatusClass }}">{{ $historyStatus }}</span></span>
                                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </span>
                                </button>
                            @endforeach
                            @foreach($familyFinanceTransactions as $record)
                                <article x-show="transactionFilter === 'all' || transactionFilter === 'verified'" class="payment-transaction-card flex w-full flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-left shadow-sm">
                                    <span class="payment-transaction-copy min-w-0">
                                        <span class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Official Receipt No.</span>
                                        <strong class="mt-0.5 block text-sm text-slate-900">{{ $record['official_receipt_number'] }}</strong>
                                        <span class="mt-1 flex flex-wrap gap-1.5"><span class="payment-source-badge {{ $record['source'] === 'ONLINE' ? 'is-online' : 'is-onsite' }}">{{ mb_strtoupper($record['source_label']) }}</span><span class="payment-method-badge">{{ mb_strtoupper($record['method_label']) }}</span></span>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $record['source'] === 'ONLINE' ? 'Approved online receipt' : 'Recorded by AMIS Finance' }}{{ $record['transaction_at'] ? ' · '.strtoupper($record['transaction_at']->format('M d, Y')) : '' }}</span>
                                    </span>
                                    <span class="payment-transaction-summary payment-history-actions flex flex-shrink-0 flex-wrap items-center justify-end gap-3 text-right">
                                        <span><strong class="block text-base text-slate-900">₱{{ number_format($record['amount'], 2) }}</strong><span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">VERIFIED</span></span>
                                    </span>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="payment-empty-state">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14.25l2.25 2.25L15 12.75M6.75 3.75h10.5A2.25 2.25 0 0119.5 6v12A2.25 2.25 0 0117.25 20.25H6.75A2.25 2.25 0 014.5 18V6a2.25 2.25 0 012.25-2.25z"/></svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-800">No payment receipts yet</h3>
                            <p class="mt-2 text-sm text-slate-600">Submitted receipts and their verification status will appear here.</p>
                        </div>
                    @endif
                </section>
            @endif
        </div>

        {{-- Add student modal --}}
        <div x-show="showAddStudent" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="!linkLoading && (showAddStudent = false)">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="add-student-title">
                <div class="h-1.5 bg-gradient-to-r from-emerald-700 to-teal-500"></div>
                <div class="p-6">
                    <div class="mb-3 flex items-start justify-between gap-4">
                        <div>
                            <span class="payment-section-kicker">Student account</span>
                            <h2 id="add-student-title" class="text-xl font-extrabold text-slate-900">Link student account</h2>
                        </div>
                        <button type="button" aria-label="Close" class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" @click="showAddStudent = false" :disabled="linkLoading">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="mb-5 text-sm leading-6 text-slate-600">Enter the student's school ID. AMIS will verify the parent email from the approved enrollment before connecting the payment account.</p>
                    <form class="space-y-4" @submit.prevent="linkStudent()">
                        <div>
                            <label for="student-number" class="mb-1.5 block text-sm font-bold text-slate-700">Student number / ID</label>
                            <input id="student-number" type="text" x-model.trim="studentNumber" class="w-full rounded-xl border-slate-300 px-4 py-3 text-base focus:border-emerald-600 focus:ring-emerald-600" placeholder="Example: 260001" required :disabled="linkLoading">
                        </div>
                        <p x-show="linkError" x-text="linkError" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800"></p>
                        <p x-show="linkSuccess" x-text="linkSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"></p>
                        <div class="flex gap-3 border-t border-slate-100 pt-4">
                            <button type="button" class="min-h-11 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="showAddStudent = false" :disabled="linkLoading">Cancel</button>
                            <button type="submit" class="min-h-11 flex-1 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-60" :disabled="linkLoading">
                                <span x-text="linkLoading ? 'Linking…' : 'Link account'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Student account breakdown modal --}}
        <div x-show="showBreakdown" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="showBreakdown = false">
            <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="breakdown-title">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 border-emerald-100 bg-emerald-50">
                            <img :src="breakdown?.avatar" :alt="'Profile avatar of ' + (breakdown?.name || 'student')" class="h-full w-full" :class="breakdown?.avatar_is_fallback ? 'object-contain' : 'object-cover'">
                        </span>
                        <div class="min-w-0">
                            <span class="payment-section-kicker">Statement of Account</span>
                            <h2 id="breakdown-title" class="truncate text-xl font-extrabold text-slate-900" x-text="breakdown?.name"></h2>
                        </div>
                    </div>
                    <button type="button" aria-label="Close" class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" @click="showBreakdown = false">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="breakdown">
                    <div class="space-y-4">
                        {{-- OPTION 1: FINANCE-UPLOADED SOA --}}
                        <section class="rounded-2xl border-2 border-blue-200 bg-blue-50/40 p-5 shadow-xs">
                            <div class="flex items-center justify-between gap-2 border-b border-blue-200/80 pb-3">
                                <div>
                                    <span class="rounded-md bg-blue-700 px-2 py-0.5 text-[10px] font-black uppercase text-white tracking-wider">Option 1</span>
                                    <h3 class="mt-1 font-black text-blue-950 text-sm">FINANCE-UPLOADED SOA</h3>
                                </div>
                                <template x-if="breakdown.manual_soa_latest">
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-black text-emerald-800" x-text="'Latest: ' + breakdown.manual_soa_latest.billing_month"></span>
                                </template>
                            </div>

                            <p class="mt-2 text-xs text-blue-900 font-medium">
                                Uploaded by AMIS Finance. This document is shown exactly as provided by the Finance Office.
                            </p>

                            <template x-if="breakdown.manual_soa_latest">
                                <div class="mt-3.5 rounded-xl border border-blue-200 bg-white p-4 shadow-2xs">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <strong class="text-sm text-slate-900" x-text="breakdown.manual_soa_latest.billing_month"></strong>
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600" x-text="'Version ' + breakdown.manual_soa_latest.version + ' (Current)'"></span>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500 font-mono" x-text="'📄 ' + breakdown.manual_soa_latest.filename + ' (' + breakdown.manual_soa_latest.size + ')'"></p>
                                            <p class="text-[11px] text-slate-400 mt-0.5" x-text="'Uploaded ' + breakdown.manual_soa_latest.uploaded_at + ' by ' + breakdown.manual_soa_latest.uploaded_by"></p>
                                            <p x-show="breakdown.manual_soa_latest.remarks" class="mt-1 text-xs text-slate-600 italic" x-text="'Remarks: ' + breakdown.manual_soa_latest.remarks"></p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" @click="openManualSoaPreview(breakdown.manual_soa_latest.view_url, breakdown.name + ' · ' + breakdown.manual_soa_latest.billing_month + ' SOA', breakdown.manual_soa_latest.is_pdf)" class="rounded-xl bg-blue-700 px-3.5 py-2 text-xs font-extrabold text-white hover:bg-blue-800 shadow-sm transition">
                                                View SOA
                                            </button>
                                            <a :href="breakdown.manual_soa_latest.download_url" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                                Download
                                            </a>
                                        </div>
                                    </div>

                                    {{-- Statement History if multiple exist --}}
                                    <template x-if="breakdown.manual_soa_history && breakdown.manual_soa_history.length > 1">
                                        <div class="mt-4 border-t border-slate-100 pt-3">
                                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2">Previous Statement History</span>
                                            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                                <template x-for="historyItem in breakdown.manual_soa_history" :key="historyItem.id">
                                                    <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-xs">
                                                        <span class="truncate">
                                                            <strong x-text="historyItem.billing_month"></strong>
                                                            <small class="text-slate-500" x-text="' (v' + historyItem.version + ')'"></small>
                                                            <span x-show="historyItem.is_current" class="ml-1 text-[10px] font-bold text-emerald-700">· Latest</span>
                                                        </span>
                                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                                            <button type="button" @click="openManualSoaPreview(historyItem.view_url, breakdown.name + ' · ' + historyItem.billing_month + ' SOA', historyItem.is_pdf)" class="text-blue-700 font-bold hover:underline px-1.5 py-0.5">
                                                                View
                                                            </button>
                                                            <a :href="historyItem.download_url" class="text-slate-600 font-bold hover:underline px-1.5 py-0.5">
                                                                Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!breakdown.manual_soa_latest">
                                <div class="mt-3 rounded-xl border border-dashed border-blue-200 bg-white p-4 text-center">
                                    <p class="text-xs font-semibold text-slate-700">No manual Statement of Account has been uploaded yet for this student.</p>
                                    <p class="mt-1 text-[11px] text-slate-500">Please check again later or contact the Finance Office.</p>
                                </div>
                            </template>
                        </section>

                        {{-- OPTION 2: SYSTEM-COMPUTED SOA — BETA --}}
                        <section class="rounded-2xl border-2 border-slate-200 bg-white p-5 shadow-xs">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                <div>
                                    <span class="rounded-md bg-slate-700 px-2 py-0.5 text-[10px] font-black uppercase text-white tracking-wider">Option 2</span>
                                    <h3 class="mt-1 font-black text-slate-900 text-sm">SYSTEM-COMPUTED SOA — BETA</h3>
                                </div>
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-900">BETA / FOR TESTING</span>
                            </div>

                            <p class="mt-2 text-xs text-slate-500">
                                Automatically calculated by the AMIS payment system for testing and verification.
                            </p>

                            <div class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between gap-4"><span class="text-slate-600">Tuition Fee</span><strong x-text="money(breakdown.tuition)"></strong></div>
                                <div class="flex justify-between gap-4"><span class="text-slate-600">Miscellaneous Fee</span><strong x-text="money(breakdown.misc)"></strong></div>
                                <div class="flex justify-between gap-4"><span class="text-slate-600">Books and LMS</span><strong x-text="money(breakdown.books)"></strong></div>
                                <div x-show="breakdown.discount > 0" class="flex justify-between gap-4 text-emerald-700"><span x-text="'Sibling Discount (' + Number(breakdown.discount_percentage).toFixed(0) + '%)'"></span><strong x-text="'-' + money(breakdown.discount)"></strong></div>
                                
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="mb-3 text-xs font-extrabold uppercase tracking-wide text-slate-500">Payment Plan Calculation</p>
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-600">Total School Fees</span>
                                        <strong x-text="money(breakdown.total)"></strong>
                                    </div>
                                    <div class="mt-2 flex justify-between gap-4">
                                        <span class="text-slate-600">Less: Enrollment / Initial Payment <small class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 font-bold text-emerald-700">Paid</small></span>
                                        <strong class="text-emerald-700" x-text="'-' + money(breakdown.enrollment)"></strong>
                                    </div>
                                    <div class="mt-3 flex justify-between gap-4 border-t border-slate-200 pt-3">
                                        <span><strong class="block text-slate-800">Monthly Tuition Plan</strong><small class="text-slate-500" x-text="breakdown.installments + ' Scheduled Installments'"></small></span>
                                        <strong class="text-lg text-slate-900" x-text="money(breakdown.installment_plan_total)"></strong>
                                    </div>
                                </div>

                                <div class="overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50">
                                    <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="installmentsOpen = !installmentsOpen" :aria-expanded="installmentsOpen.toString()">
                                        <span>
                                            <span class="block font-bold text-emerald-950" x-text="breakdown.installments + '-Month Installment Breakdown'"></span>
                                            <span class="mt-0.5 block text-xs text-emerald-700" x-text="installmentsOpen ? 'Hide Monthly Breakdown' : 'View Monthly Breakdown'"></span>
                                        </span>
                                        <span class="flex flex-shrink-0 items-center gap-3">
                                            <span class="text-right"><strong class="block text-emerald-950" x-text="money(breakdown.monthly) + ' Regular'"></strong><small x-show="Math.abs(breakdown.final_installment - breakdown.monthly) > 0.001" class="text-emerald-700" x-text="money(breakdown.final_installment) + ' Final Month'"></small></span>
                                            <svg class="h-4 w-4 text-emerald-700 transition" :class="installmentsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </span>
                                    </button>

                                    <div x-show="installmentsOpen" class="border-t border-emerald-200 bg-white">
                                        <template x-if="breakdown.installment_breakdown.length === 0">
                                            <p class="px-4 py-5 text-center text-sm text-slate-500">No monthly installment schedule is available yet.</p>
                                        </template>
                                        <template x-for="installment in breakdown.installment_breakdown" :key="installment.month">
                                            <div class="border-b border-slate-100 px-4 py-3 last:border-b-0">
                                                <div class="flex items-start justify-between gap-4">
                                                    <span class="min-w-0">
                                                        <strong class="block text-sm text-slate-900" x-text="installment.month"></strong>
                                                        <small class="mt-0.5 block text-slate-500" x-text="'Due ' + (installment.due_date || 'Date Not Set')"></small>
                                                    </span>
                                                    <span class="text-right">
                                                        <strong class="block text-sm text-slate-900" x-text="money(installment.original)"></strong>
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </template>
                <div class="mt-6 flex gap-3">
                    <button type="button" class="min-h-11 flex-1 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="showBreakdown = false">Close</button>
                    <button x-show="breakdown?.next_payment" type="button" class="min-h-11 flex-[1.6] rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800" @click="showBreakdown = false; activeTab = 'monthly'">
                        View Family Payment Schedule
                    </button>
                </div>
            </div>
        </div>

        {{-- MANUAL SOA DOCUMENT PREVIEW MODAL --}}
        <div x-show="showManualSoaPreview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="showManualSoaPreview = false">
            <div class="w-full max-w-4xl h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden" role="dialog" aria-modal="true">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3">
                    <div class="min-w-0">
                        <span class="text-[10px] font-black uppercase text-blue-700 tracking-wider">Finance-Uploaded Document</span>
                        <h3 class="text-sm font-black text-slate-900 truncate" x-text="manualSoaPreviewTitle"></h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="manualSoaPreviewUrl" target="_blank" class="rounded-lg bg-white border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Open in New Tab
                        </a>
                        <button type="button" @click="showManualSoaPreview = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-100 overflow-auto flex items-center justify-center p-2">
                    <template x-if="manualSoaPreviewIsPdf">
                        <iframe :src="manualSoaPreviewUrl" class="w-full h-full rounded-lg border border-slate-300 shadow-sm" frameborder="0"></iframe>
                    </template>
                    <template x-if="!manualSoaPreviewIsPdf">
                        <img :src="manualSoaPreviewUrl" alt="SOA Preview" class="max-w-full max-h-full object-contain rounded-lg shadow-md">
                    </template>
                </div>
            </div>
        </div>

        {{-- Grouped transaction details modal --}}
        <div x-show="showTransaction" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="showTransaction = false">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="transaction-title">
                <div class="h-1.5 bg-gradient-to-r from-emerald-700 via-teal-500 to-sky-500"></div>
                <div class="p-6">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <span class="payment-section-kicker" x-text="transaction?.status === 'verified' ? 'Official payment receipt' : 'Payment submission'"></span>
                            <h2 id="transaction-title" class="text-xl font-extrabold text-slate-900" x-text="transaction?.number"></h2>
                            <p class="mt-1 text-sm text-slate-500" x-text="transaction?.date"></p>
                        </div>
                        <button type="button" aria-label="Close transaction details" class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" @click="showTransaction = false">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <template x-if="transaction">
                        <div class="space-y-4">
                            <section x-show="transaction.status === 'verified'" class="payment-long-receipt">
                                <header class="payment-long-receipt-header">
                                    <div><span class="payment-long-receipt-mark">AMIS</span><strong>Al Munawwara Islamic School</strong><small>Family Finance Office</small></div>
                                    <div><h3>RECEIPT</h3><span><small>Official Receipt No.</small><strong x-text="transaction.official_receipt_number"></strong></span><span><small>Receipt date</small><strong x-text="transaction.receipt_date || 'Not recorded'"></strong></span></div>
                                </header>

                                <section class="payment-long-receipt-party">
                                    <div><small>Billed to</small><strong x-text="transaction.payer || 'Family account'"></strong><span>Students: <b x-text="transaction.covered_students.length ? transaction.covered_students.join(', ') : 'Advance credit'"></b></span></div>
                                    <div><span><small>Payment source</small><strong x-text="transaction.source?.toUpperCase()"></strong></span><span><small>Payment method</small><strong x-text="transaction.method?.toUpperCase()"></strong></span><span><small>Payment reference</small><strong x-text="transaction.reference || 'Not recorded'"></strong></span></div>
                                </section>

                                <section class="payment-long-receipt-items">
                                    <div class="payment-long-receipt-table-head"><strong>Description</strong><strong>Amount</strong></div>
                                    <div x-show="transaction.allocations.length === 0" class="payment-long-receipt-empty">No open billing balance was available. This payment was recorded as advance credit.</div>
                                    <template x-for="(allocation, index) in transaction.allocations" :key="index">
                                        <div class="payment-long-receipt-item">
                                            <span><strong x-text="allocation.student"></strong><small><span x-text="allocation.month"></span> · Balance before payment</small></span>
                                            <strong x-text="money(allocation.balance_before)"></strong>
                                        </div>
                                    </template>
                                </section>

                                <section class="payment-long-receipt-totals">
                                    <div><span>Subtotal — listed balances</span><strong x-text="money(transaction.itemized_charges_total)"></strong></div>
                                    <div class="is-deduction"><span>Less: <b x-text="transaction.source?.toUpperCase()"></b> · <b x-text="transaction.method?.toUpperCase()"></b></span><strong x-text="'-' + money(transaction.applied_total)"></strong></div>
                                    <div><span>Remaining on listed balances</span><strong x-text="money(transaction.itemized_remaining_total)"></strong></div>
                                    <div x-show="transaction.advance_credit > 0"><span>Advance credit</span><strong x-text="money(transaction.advance_credit)"></strong></div>
                                    <div class="is-total"><span>Total amount paid</span><strong x-text="money(transaction.total)"></strong></div>
                                    <div x-show="transaction.balance_after !== null" class="is-family-balance"><span>Remaining family balance after payment</span><strong x-text="money(transaction.balance_after)"></strong></div>
                                </section>

                                <footer class="payment-long-receipt-notes"><strong>Notes</strong><p x-text="transaction.remarks || 'Verified by AMIS Finance. Payment allocation was completed automatically using the oldest outstanding family balance first.'"></p><small>This official payment record is permanently linked to the AMIS Finance audit trail.</small></footer>
                            </section>

                            <section x-show="transaction.status !== 'verified'" class="space-y-4">
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div class="rounded-xl bg-slate-50 p-3"><small class="block font-bold uppercase text-slate-500">Status</small><strong class="mt-1 block uppercase" x-text="transaction.status"></strong></div>
                                    <div class="rounded-xl bg-slate-50 p-3"><small class="block font-bold uppercase text-slate-500">Payment Source</small><strong class="mt-1 block" x-text="transaction.source?.toUpperCase()"></strong></div>
                                    <div class="rounded-xl bg-slate-50 p-3"><small class="block font-bold uppercase text-slate-500">Payment Method</small><strong class="mt-1 block" x-text="transaction.method?.toUpperCase()"></strong></div>
                                    <div class="rounded-xl bg-slate-50 p-3"><small class="block font-bold uppercase text-slate-500">Submitted</small><strong class="mt-1 block" x-text="transaction.receipt_date || 'Not recorded'"></strong></div>
                                    <div class="col-span-2 rounded-xl bg-slate-50 p-3"><small class="block font-bold uppercase text-slate-500">Submission No.</small><strong class="mt-1 block break-all" x-text="transaction.submission_number"></strong></div>
                                    <div class="col-span-2 rounded-xl bg-slate-50 p-3"><small class="block font-bold uppercase text-slate-500">Payment Reference</small><strong class="mt-1 block break-all" x-text="transaction.reference || 'Not recorded'"></strong></div>
                                </div>
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong class="block">Awaiting Finance verification</strong><span>No family balance has changed yet. AMIS will allocate the payment only after approval.</span></div>
                            </section>
                            <a x-show="transaction.receipt" :href="transaction.receipt" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">View uploaded receipt</a>
                            <button x-show="transaction.status === 'rejected'" type="button" class="ml-2 min-h-11 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800" @click="showTransaction = false; activeTab = 'monthly'">Review Monthly Balance</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function paymentDashboard() {
                return {
                    showAddStudent: false,
                    activeTab: 'notifications',
                    studentNumber: '',
                    linkLoading: false,
                    linkError: '',
                    linkSuccess: '',
                    showBreakdown: false,
                    breakdown: null,
                    installmentsOpen: false,
                    showManualSoaPreview: false,
                    manualSoaPreviewUrl: '',
                    manualSoaPreviewTitle: '',
                    manualSoaPreviewIsPdf: false,
                    showTransaction: false,
                    transaction: null,
                    transactionFilter: 'all',
                    monthFilter: 'current',
                    openMonth: null,

                    init() {
                        const requestedTab = new URLSearchParams(window.location.search).get('tab');
                        if (['notifications', 'monthly', 'transactions'].includes(requestedTab)) this.activeTab = requestedTab;
                    },

                    money(value) {
                        return '₱' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },

                    closeTopModal() {
                        if (this.showManualSoaPreview) return this.showManualSoaPreview = false;
                        if (this.showTransaction) return this.showTransaction = false;
                        if (this.showBreakdown) return this.showBreakdown = false;
                        if (this.showAddStudent && !this.linkLoading) this.showAddStudent = false;
                    },

                    async linkStudent() {
                        this.linkLoading = true;
                        this.linkError = '';
                        this.linkSuccess = '';
                        try {
                            const response = await fetch(@json(route('payment.link-student')), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ student_number: this.studentNumber })
                            });
                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message || 'We could not add this student. Please check the details.');
                            this.linkSuccess = data.message || 'Student linked successfully.';
                            setTimeout(() => window.location.reload(), 1200);
                        } catch (error) {
                            this.linkError = error.message;
                            this.linkLoading = false;
                        }
                    },

                    openBreakdown(data) {
                        this.breakdown = data;
                        this.installmentsOpen = false;
                        this.showBreakdown = true;
                    },

                    openManualSoaPreview(url, title, isPdf) {
                        this.manualSoaPreviewUrl = url;
                        this.manualSoaPreviewTitle = title;
                        this.manualSoaPreviewIsPdf = isPdf;
                        this.showManualSoaPreview = true;
                    },

                    openTransaction(data) {
                        this.transaction = data;
                        this.showTransaction = true;
                    },

                    goToMonth(monthKey) {
                        this.showBreakdown = false;
                        this.activeTab = 'monthly';
                        this.openMonth = monthKey;
                        this.$nextTick(() => document.getElementById('schedule-heading')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                    },

                };
            }
        </script>
    @endpush
</x-app-layout>
