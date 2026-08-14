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
                    <div class="payment-page-eyebrow-wrapper">
                        <span class="welcome-sy-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>School Year {{ $students->first()?->account?->school_year ?? $demoChildren->first()?->school_year ?? '2026–2027' }}</span>
                        </span>
                    </div>
                    <h1 class="payment-page-title">Family Payments</h1>
                    <p class="payment-page-subtitle">Manage your children's school payments in one place.</p>
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
                    $currentMonthCharges = (float) $currentPaymentSummary['current_charges'];
                    $currentMonthPaid = (float) $currentPaymentSummary['verified_payments'];
                    $currentMonthDueNow = max(0, round($currentMonthCharges - $currentMonthPaid, 2));
                    $familyAmountDueNow = round($pastDueNow + $currentMonthDueNow, 2);
                    $familyRemainingBalance = max(0, round((float) $familyTotalRemaining, 2));
                    $futureScheduledBalance = max(0, round($familyRemainingBalance - $familyAmountDueNow, 2));
                    $currentPaymentMonth = $currentPaymentSummary['month_key'] !== null
                        ? ($monthlyGroups[$currentPaymentSummary['month_key']]['month_name'] ?? null)
                        : null;
                    $currentPaymentMonth = mb_strtoupper($currentPaymentMonth ?: now(config('finance.timezone', 'Asia/Manila'))->format('F'));
                    $allChildrenList = $students->isNotEmpty() ? $students : $demoChildren;
                @endphp

                <!-- PARENT-FRIENDLY OVERVIEW HUB (Modern School Payment Portal Vibe) -->
                <section class="family-overview-hub" aria-labelledby="family-overview-heading">
                    <h2 id="family-overview-heading" class="sr-only">Family Payment Overview</h2>

                    <!-- 1. TOP HERO ROW (Soft green-tinted hero panel + soft neutral annual panel) -->
                    <div class="family-hero-row">
                        <div class="family-hero-due-panel">
                            <div class="due-month-kicker">{{ $currentPaymentMonth }} {{ now()->format('Y') }}</div>
                            <div class="due-sub-label">Amount due this month</div>

                            <div class="due-amount-hero">
                                <span class="due-currency">₱</span>
                                <strong class="due-value">{{ number_format($familyAmountDueNow, 2) }}</strong>
                            </div>
                        </div>

                        <div class="family-hero-annual-panel">
                            <div class="annual-balance-block">
                                <span class="annual-label">{{ $demoChildren->isNotEmpty() && $students->isEmpty() ? 'Demo Remaining for School Year' : 'Remaining for School Year' }}</span>
                                <strong class="annual-amount">₱{{ number_format($familyRemainingBalance, 2) }}</strong>
                                <p class="annual-sub">Total balance across all school months</p>
                            </div>

                            @if($familyAdvanceCredit > 0)
                                <div class="advance-credit-line">
                                    <svg class="w-3.5 h-3.5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Advance credit: ₱{{ number_format($familyAdvanceCredit, 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="hub-divider" aria-hidden="true"></div>

                    <!-- 2. STATS GRID (One clean horizontal premium statistics bar) -->
                    <div class="family-stats-grid">
                        <div class="stat-card is-paid">
                            <div class="stat-head">
                                <span class="stat-icon-circle">
                                    <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </span>
                                <span class="stat-caption">TOTAL PAID THIS MONTH</span>
                            </div>
                            <strong class="stat-amount">₱{{ number_format($currentMonthPaid, 2) }}</strong>
                            <span class="stat-sub">Payments received</span>
                        </div>

                        <div class="stat-card is-still-due">
                            <div class="stat-head">
                                <span class="stat-icon-circle">
                                    <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <span class="stat-caption">CURRENT BALANCE</span>
                            </div>
                            <strong class="stat-amount">₱{{ number_format($currentMonthDueNow, 2) }}</strong>
                            <span class="stat-sub">Amount to be paid</span>
                        </div>

                        <div class="stat-card is-past-due {{ $pastDueNow > 0 ? 'has-due' : '' }}">
                            <div class="stat-head">
                                <span class="stat-icon-circle">
                                    <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </span>
                                <span class="stat-caption">PAST DUE</span>
                            </div>
                            <strong class="stat-amount">₱{{ number_format($pastDueNow, 2) }}</strong>
                            <span class="stat-sub">{{ $pastDueNow > 0 ? 'Action needed' : 'No overdue payments' }}</span>
                        </div>

                        <div class="stat-card is-future">
                            <div class="stat-head">
                                <span class="stat-icon-circle">
                                    <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </span>
                                <span class="stat-caption">UPCOMING BALANCE</span>
                            </div>
                            <strong class="stat-amount">₱{{ number_format($futureScheduledBalance, 2) }}</strong>
                            <span class="stat-sub">Future payments</span>
                        </div>
                    </div>
                </section>

                <!-- 4. MUTED ONE-LINE DEMO NOTICE -->
                @if($demoChildren->isNotEmpty() && $students->isEmpty())
                    <div class="family-demo-muted-notice" role="status">
                        <svg class="demo-notice-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Demo account: These sample students are for preview only. No official AMIS records will be changed.</span>
                    </div>
                @endif

                <section class="payment-section" aria-labelledby="students-heading">
                    <div class="payment-section-heading">
                        <div>
                            <span class="payment-section-kicker">STUDENT ACCOUNTS</span>
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

                                $studentAccentClass = str_contains(strtoupper($studentName), 'MARYAM') 
                                    ? 'accent-blue' 
                                    : (str_contains(strtoupper($studentName), 'YUSUF') 
                                        ? 'accent-gold' 
                                        : ($loop->index % 3 === 1 ? 'accent-blue' : ($loop->index % 3 === 2 ? 'accent-gold' : 'accent-green')));

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

                            <a
                                href="{{ route('payment.students.official-soa', ['studentIdentifier' => $sId]) }}"
                                target="_blank"
                                class="payment-student-card {{ $studentAccentClass }}"
                                aria-label="Open Official Statement of Account (PDF) for {{ $studentName }}"
                            >
                                <div class="student-card-main">
                                    <div class="student-card-avatar">
                                        <img
                                            src="{{ $avatarUrl }}"
                                            alt="Profile avatar of {{ $studentName }}"
                                            class="{{ $hasUploadedPhoto ? '' : 'avatar-placeholder' }}"
                                        >
                                    </div>
                                    <div class="student-card-details">
                                        <h3 class="student-card-name" title="{{ $studentName }}">{{ $studentName }}</h3>
                                        <div class="student-card-meta">{{ $student->grade_level }} · ID {{ $student->student_number }}</div>
                                        @if(($account?->discount_percentage ?? 0) > 0)
                                            <div class="student-card-tags">
                                                <span class="student-tag-discount">{{ number_format((float) $account->discount_percentage, 0) }}% sibling discount</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="student-card-finance">
                                    <div class="student-card-balance">
                                        <span class="balance-label">BALANCE</span>
                                        <strong class="balance-value">₱{{ number_format($account?->remaining_balance ?? 0, 2) }}</strong>
                                    </div>
                                    <span class="student-card-soa-btn" title="Open & Print Official School Statement of Account (PDF)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span>Official SOA (PDF)</span>
                                    </span>
                                </div>
                            </a>
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

                                $demoAccentClass = str_contains(strtoupper($demoChild->display_name), 'MARYAM') 
                                    ? 'accent-blue' 
                                    : (str_contains(strtoupper($demoChild->display_name), 'YUSUF') 
                                        ? 'accent-gold' 
                                        : ($loop->index % 3 === 1 ? 'accent-blue' : ($loop->index % 3 === 2 ? 'accent-gold' : 'accent-green')));

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
                            <a
                                href="{{ route('payment.students.official-soa', ['studentIdentifier' => $demoChild->demo_student_number]) }}"
                                target="_blank"
                                class="payment-student-card is-demo {{ $demoAccentClass }}"
                                aria-label="Open Official Statement of Account (PDF) for {{ $demoChild->display_name }}"
                            >
                                <div class="student-card-main">
                                    <div class="student-card-avatar">
                                        <img src="{{ $demoAvatarUrl }}" alt="Demo avatar of {{ $demoChild->display_name }}" class="avatar-placeholder">
                                    </div>
                                    <div class="student-card-details">
                                        <h3 class="student-card-name" title="{{ $demoChild->display_name }}">{{ mb_strtoupper($demoChild->display_name) }}</h3>
                                        <div class="student-card-meta">{{ $demoChild->grade_level }} · ID {{ $demoChild->demo_student_number }}</div>
                                        <div class="student-card-tags">
                                            <span class="student-tag-demo">Demo record</span>
                                            @if((float) $demoChild->discount_percentage > 0)
                                                <span class="tag-sep" aria-hidden="true">·</span>
                                                <span class="student-tag-discount">{{ number_format((float) $demoChild->discount_percentage, 0) }}% sibling discount</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="student-card-finance">
                                    <div class="student-card-balance">
                                        <span class="balance-label">BALANCE</span>
                                        <strong class="balance-value">₱{{ number_format($demoRemainingBalance, 2) }}</strong>
                                    </div>
                                    <span class="student-card-soa-btn" title="Open & Print Official School Statement of Account (PDF)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span>Official SOA (PDF)</span>
                                    </span>
                                </div>
                            </a>
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
                        <span class="tab-icon-circle">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.85 23.85 0 005.454-1.31A8.97 8.97 0 0118 9.75V9A6 6 0 006 9v.75a8.97 8.97 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.26 24.26 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                            </svg>
                        </span>
                        <span class="tab-content">
                            <strong class="tab-title">Notifications</strong>
                            <small class="tab-sub">{{ $paymentNotifications->count() }} important {{ Str::plural('update', $paymentNotifications->count()) }}</small>
                        </span>
                        <svg class="tab-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="payment-view-tab"
                        :class="activeTab === 'monthly' ? 'is-active' : ''"
                        :aria-selected="(activeTab === 'monthly').toString()"
                        @click="activeTab = 'monthly'"
                    >
                        <span class="tab-icon-circle">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 001.5-1.5V6.75a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5V19.5a1.5 1.5 0 001.5 1.5z"/>
                            </svg>
                        </span>
                        <span class="tab-content">
                            <strong class="tab-title">Monthly Payments</strong>
                            <small class="tab-sub">Balances and payment schedule</small>
                        </span>
                        <svg class="tab-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="payment-view-tab"
                        :class="activeTab === 'transactions' ? 'is-active' : ''"
                        :aria-selected="(activeTab === 'transactions').toString()"
                        @click="activeTab = 'transactions'"
                    >
                        <span class="tab-icon-circle">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14.25l2.25 2.25L15 12.75M6.75 3.75h10.5A2.25 2.25 0 0119.5 6v12A2.25 2.25 0 0117.25 20.25H6.75A2.25 2.25 0 014.5 18V6a2.25 2.25 0 012.25-2.25z"/>
                            </svg>
                        </span>
                        @php
                            $familyTransactionCount = $familyFinanceTransactions->count() + $unpostedPaymentSubmissions->count();
                        @endphp
                        <span class="tab-content">
                            <strong class="tab-title">Transactions</strong>
                            <small class="tab-sub">{{ $familyTransactionCount }} payment {{ Str::plural('record', $familyTransactionCount) }}</small>
                        </span>
                        <svg class="tab-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
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
                                                                <small>Paid This Month</small>
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
