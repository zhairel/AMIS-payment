<x-app-layout>
    @php
        $demoChildren = $demoChildren ?? collect();
        $allChildrenList = $students->isNotEmpty() ? $students : $demoChildren;
        $schoolYearLabel = $students->first()?->account?->school_year ?? $demoChildren->first()?->school_year ?? '2026–2027';
    @endphp
    <x-slot name="title">Family Payments</x-slot>

    <div
        class="min-h-screen bg-slate-100/70 py-8 sm:py-12"
        x-data="paymentDashboard()"
        @keydown.escape.window="closeTopModal()"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                       <!-- Top Breadcrumb & Page Kicker -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-800 uppercase tracking-wide">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>School Year {{ $schoolYearLabel }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-800 uppercase tracking-wide">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Family Portal Active
                        </span>
                    </div>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Family Payments</h1>
                    <p class="mt-1 text-sm text-slate-600">Manage your children's balances, monthly tuition fees, official statements of account, and payment receipts.</p>
                </div>
            </div>

            @if($students->isEmpty() && $demoChildren->isEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 18.72a9.1 9.1 0 003.74-.48 3 3 0 00-4.68-2.72m.94 3.2v-.01c0-1.09-.28-2.11-.77-3M18 18.72v.78c0 .41-.34.75-.75.75H6.75A.75.75 0 016 19.5v-.78m12 0a9.72 9.72 0 00-6-1.97 9.72 9.72 0 00-6 1.97m0 0a5.98 5.98 0 00-.77-3m0 0a3 3 0 00-4.68 2.72 9.1 9.1 0 003.74.48m.94-3a5.97 5.97 0 0113.54 0M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                        </svg>
                    </div>
                    <h2 class="mt-4 text-xl font-black text-slate-900">No students linked yet</h2>
                    <p class="mx-auto mb-6 mt-2 max-w-md text-sm leading-relaxed text-slate-600">Link an existing student using their school ID. AMIS securely matches the parent email recorded in enrollment.</p>
                    <button type="button" class="inline-flex items-center rounded-xl bg-emerald-700 px-6 py-3 text-sm font-extrabold text-white hover:bg-emerald-800 shadow-sm transition" @click="showAddStudent = true">
                        Link Student Account
                    </button>
                </div>
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
                @endphp

                <!-- PARENT-FRIENDLY OVERVIEW HERO BANNER (Official AMIS Emerald) -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#065f46] via-[#054e3a] to-[#033b2c] p-8 sm:p-12 text-white shadow-xl shadow-emerald-950/10">
                    <!-- Decorative background circles -->
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full border border-white/10 bg-white/[0.03] pointer-events-none"></div>
                    <div class="absolute -bottom-24 -left-16 h-72 w-72 rounded-full border border-white/10 bg-white/[0.03] pointer-events-none"></div>

                    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                        <!-- Left Hero: Month Fee Heading & Amount -->
                        <div class="lg:col-span-7 space-y-3">
                            <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-white/90">
                                {{ $currentPaymentMonth }} {{ now()->format('Y') }} MONTHLY FEE
                            </h2>

                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl sm:text-4xl font-extrabold text-white">₱</span>
                                <span class="text-4xl sm:text-6xl font-black text-white tracking-tight">
                                    {{ number_format($familyAmountDueNow, 2) }}
                                </span>
                            </div>

                            <p class="text-xs sm:text-sm text-white/80 leading-relaxed pt-1">
                                Total outstanding monthly installment and any overdue balances for your family.
                            </p>
                        </div>

                        <!-- Right Hero: Remaining for School Year & Advance Credit -->
                        <div class="lg:col-span-5 rounded-2xl bg-white/10 border border-white/15 p-6 sm:p-8 backdrop-blur-sm space-y-3">
                            <div class="text-xs font-bold uppercase tracking-wider text-white/90">
                                {{ $demoChildren->isNotEmpty() && $students->isEmpty() ? "Demo Remaining for School Year $schoolYearLabel" : "Remaining for School Year $schoolYearLabel" }}
                            </div>

                            <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                                ₱{{ number_format($familyRemainingBalance, 2) }}
                            </div>

                            <p class="text-xs text-white/80">
                                Total balance across all school months.
                            </p>

                            @if($familyAdvanceCredit > 0)
                                <div class="pt-3 border-t border-white/15 flex items-center gap-2 text-xs font-bold text-white uppercase tracking-wide">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Advance Credit: ₱{{ number_format($familyAdvanceCredit, 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 4-METRIC STATISTICS GRID -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                    <!-- Metric 1: Total Paid This Month -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Total Paid This Month</span>
                            </div>
                            <div class="mt-4 text-2xl font-black text-slate-900">₱{{ number_format($currentMonthPaid, 2) }}</div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 font-medium">Payments received</div>
                    </div>

                    <!-- Metric 2: Current Balance -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Current Balance</span>
                            </div>
                            <div class="mt-4 text-2xl font-black text-slate-900">₱{{ number_format($currentMonthDueNow, 2) }}</div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 font-medium">Amount to be paid</div>
                    </div>

                    <!-- Metric 3: Past Due -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition {{ $pastDueNow > 0 ? 'ring-2 ring-rose-500/20' : '' }}">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl {{ $pastDueNow > 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }} shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold uppercase tracking-wider {{ $pastDueNow > 0 ? 'text-rose-700' : 'text-slate-500' }}">Past Due</span>
                            </div>
                            <div class="mt-4 text-2xl font-black {{ $pastDueNow > 0 ? 'text-rose-700' : 'text-slate-900' }}">₱{{ number_format($pastDueNow, 2) }}</div>
                        </div>
                        <div class="mt-2 text-xs font-medium {{ $pastDueNow > 0 ? 'text-rose-600 font-bold' : 'text-slate-500' }}">{{ $pastDueNow > 0 ? 'Action needed' : 'No overdue payments' }}</div>
                    </div>

                    <!-- Metric 4: Upcoming Balance -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Upcoming Balance</span>
                            </div>
                            <div class="mt-4 text-2xl font-black text-slate-900">₱{{ number_format($futureScheduledBalance, 2) }}</div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 font-medium">Future payments</div>
                    </div>
                </div>

                <!-- Muted One-Line Demo Notice -->
                @if($demoChildren->isNotEmpty() && $students->isEmpty())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 flex items-center gap-3 text-xs text-amber-900 font-medium">
                        <svg class="h-5 w-5 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Demo Account Preview: Sample student records for demonstration only. No official school records are modified.</span>
                    </div>
                @endif

                <!-- LINKED STUDENTS SECTION -->
                <div class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">Your Linked Students / Children</h2>
                            <p class="text-xs text-slate-500">Open a student account to see official statements of account and tuition breakdowns.</p>
                        </div>

                        @if($demoChildren->isEmpty())
                            <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-800 shadow-sm transition" @click="showAddStudent = true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Link Student Account</span>
                            </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($students as $student)
                            @php
                                $account = $student->account;
                                $applicant = $student->applicant;
                                $studentName = mb_strtoupper($applicant?->full_name ?? '');
                                $studentName = $studentName ?: 'STUDENT';
                                $sId = (string) ($student->student_number ?: $student->id);
                                $studentInitial = mb_substr($studentName, 0, 1);
                                $studentAccentBg = str_contains(strtoupper($studentName), 'MARYAM') ? 'bg-blue-50 text-blue-700 border-blue-200' : (str_contains(strtoupper($studentName), 'YUSUF') ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200');
                            @endphp

                            <div class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border {{ $studentAccentBg }} text-lg font-black shadow-inner">
                                            {{ $studentInitial }}
                                        </div>
                                        <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800 uppercase tracking-wide">
                                            ACTIVE STUDENT
                                        </span>
                                    </div>

                                    <div class="mt-4">
                                        <h3 class="text-base font-black text-slate-900" title="{{ $studentName }}">{{ $studentName }}</h3>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-slate-500 font-semibold">
                                            <span>{{ $student->grade_level }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span class="font-mono text-slate-400">ID {{ $student->student_number }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-600">
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-400 font-medium">Remaining Balance:</span>
                                            <span class="font-black text-slate-900 text-sm">₱{{ number_format($account?->remaining_balance ?? 0, 2) }}</span>
                                        </div>
                                        @if(($account?->discount_percentage ?? 0) > 0)
                                            <div class="flex justify-between items-center text-emerald-700">
                                                <span class="font-medium">Discount Applied:</span>
                                                <span class="font-bold uppercase tracking-wider">{{ number_format((float) $account->discount_percentage, 0) }}% SIBLING DISCOUNT</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-6 pt-4 border-t border-slate-100">
                                    <a href="{{ route('payment.students.official-soa', ['studentIdentifier' => $sId]) }}" target="_blank" class="flex items-center justify-center gap-2 w-full rounded-xl bg-slate-50 border border-slate-200 py-3 text-xs font-extrabold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span>Official Statement of Account (PDF)</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach

                        @foreach($demoChildren as $demoChild)
                            @php
                                $demoInstallmentBreakdown = app(\App\Services\DemoPaymentScheduleService::class)->installmentsFor($demoChild, $demoChildren);
                                $demoRemainingBalance = (float) collect($demoInstallmentBreakdown)->sum('remaining');
                                $demoInitial = mb_substr($demoChild->display_name, 0, 1);
                                $demoAccentBg = str_contains(strtoupper($demoChild->display_name), 'MARYAM') ? 'bg-blue-50 text-blue-700 border-blue-200' : (str_contains(strtoupper($demoChild->display_name), 'YUSUF') ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200');
                            @endphp

                            <div class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border {{ $demoAccentBg }} text-lg font-black shadow-inner">
                                            {{ $demoInitial }}
                                        </div>
                                        <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800 uppercase tracking-wide">
                                            ACTIVE STUDENT
                                        </span>
                                    </div>

                                    <div class="mt-4">
                                        <h3 class="text-base font-black text-slate-900" title="{{ $demoChild->display_name }}">{{ mb_strtoupper($demoChild->display_name) }}</h3>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-slate-500 font-semibold">
                                            <span>{{ $demoChild->grade_level }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span class="font-mono text-slate-400">ID {{ $demoChild->demo_student_number }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-600">
                                        <div class="flex justify-between items-center">
                                            <span class="text-slate-400 font-medium">Remaining Balance:</span>
                                            <span class="font-black text-slate-900 text-sm">₱{{ number_format($demoRemainingBalance, 2) }}</span>
                                        </div>
                                        @if((float) $demoChild->discount_percentage > 0)
                                            <div class="flex justify-between items-center text-emerald-700">
                                                <span class="font-medium">Discount Applied:</span>
                                                <span class="font-bold uppercase tracking-wider">{{ number_format((float) $demoChild->discount_percentage, 0) }}% SIBLING DISCOUNT</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-6 pt-4 border-t border-slate-100">
                                    <a href="{{ route('payment.students.official-soa', ['studentIdentifier' => $demoChild->demo_student_number]) }}" target="_blank" class="flex items-center justify-center gap-2 w-full rounded-xl bg-slate-50 border border-slate-200 py-3 text-xs font-extrabold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span>Official Statement of Account (PDF)</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- MODERN TAB NAVIGATION SWITCHER -->
                @php
                    $familyTransactionCount = $familyFinanceTransactions->count() + $unpostedPaymentSubmissions->count();
                @endphp
                <div class="flex items-center border-b border-slate-200 gap-1 sm:gap-4">
                    <button type="button"
                            role="tab"
                            aria-label="Notifications"
                            title="Notifications"
                            @click="activeTab = 'notifications'"
                            :class="activeTab === 'notifications' ? 'border-emerald-700 text-emerald-800 font-extrabold border-b-2' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center sm:justify-start gap-1.5 sm:gap-2 px-2.5 sm:px-4 py-3 text-xs sm:text-sm whitespace-nowrap transition pb-3.5 -mb-px">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="hidden sm:inline">Notifications</span>
                        @if($paymentNotifications->isNotEmpty())
                            <span class="rounded-full bg-emerald-100 text-emerald-800 px-1.5 py-0.5 text-[11px] sm:text-xs font-bold">{{ $paymentNotifications->count() }}</span>
                        @endif
                    </button>

                    <button type="button"
                            role="tab"
                            aria-label="Monthly Payments"
                            title="Monthly Payments"
                            @click="activeTab = 'monthly'"
                            :class="activeTab === 'monthly' ? 'border-emerald-700 text-emerald-800 font-extrabold border-b-2' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center sm:justify-start gap-1.5 sm:gap-2 px-2.5 sm:px-4 py-3 text-xs sm:text-sm whitespace-nowrap transition pb-3.5 -mb-px">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="hidden sm:inline">Monthly Payments</span>
                    </button>

                    <button type="button"
                            role="tab"
                            aria-label="Transactions & Receipts"
                            title="Transactions & Receipts"
                            @click="activeTab = 'transactions'"
                            :class="activeTab === 'transactions' ? 'border-emerald-700 text-emerald-800 font-extrabold border-b-2' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center sm:justify-start gap-1.5 sm:gap-2 px-2.5 sm:px-4 py-3 text-xs sm:text-sm whitespace-nowrap transition pb-3.5 -mb-px">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span class="hidden sm:inline">Transactions & Receipts</span>
                        <span class="rounded-full bg-slate-100 text-slate-700 px-1.5 py-0.5 text-[11px] sm:text-xs font-bold">{{ $familyTransactionCount }}</span>
                    </button>
                </div>

                <!-- TAB 1: NOTIFICATIONS & UPDATES -->
                <div x-show="activeTab === 'notifications'" x-cloak class="space-y-6">
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-10 lg:p-12 shadow-sm">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-5">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-slate-900">Account Notifications & Updates</h2>
                                <p class="text-xs text-slate-500">Important reminders about due payments, receipt verification, and account activity.</p>
                            </div>
                        </div>

                        @if($paymentNotifications->isNotEmpty())
                            <div class="mt-6 space-y-4">
                                @foreach($paymentNotifications as $notification)
                                    @php
                                        $notificationType = $notification['type'];
                                        $badgeBg = match($notificationType) {
                                            'overdue', 'previous' => 'bg-rose-50 border-rose-200 text-rose-800',
                                            'pending' => 'bg-amber-50 border-amber-200 text-amber-800',
                                            'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                                            default => 'bg-slate-100 border-slate-200 text-slate-700',
                                        };
                                        $notificationLabel = match($notificationType) {
                                            'overdue', 'previous' => 'ACTION NEEDED',
                                            'pending' => 'UNDER REVIEW',
                                            'success' => 'PAYMENT VERIFIED',
                                            default => 'UPCOMING',
                                        };
                                    @endphp

                                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-7 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 shadow-sm hover:border-slate-300 transition">
                                        <div class="space-y-2.5 max-w-2xl">
                                            <!-- 1. Status Badge & 2. Date -->
                                            <div class="flex items-center gap-3">
                                                <span class="rounded-full border px-3 py-0.5 text-xs font-bold uppercase tracking-wide {{ $badgeBg }}">
                                                    {{ $notificationLabel }}
                                                </span>
                                                @if($notification['date'])
                                                    <span class="text-xs text-slate-400 font-medium">{{ $notification['date']->format('M j, Y') }}</span>
                                                @endif
                                            </div>

                                            <!-- 3. Main Title -->
                                            <h3 class="text-base font-bold text-slate-900 leading-snug">{{ $notification['title'] }}</h3>

                                            <!-- 4. Short Explanation -->
                                            <p class="text-sm text-slate-600 leading-relaxed">{{ $notification['message'] }}</p>

                                            <!-- Secondary Technical Details (Receipt / Submission Number) -->
                                            @if(!empty($notification['reference']))
                                                <div class="text-xs text-slate-400 font-mono font-medium pt-0.5">
                                                    {{ $notification['reference'] }}
                                                </div>
                                            @endif
                                        </div>

                                        <!-- 5. Amount Label & 6. Amount Value -->
                                        <div class="sm:text-right shrink-0 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100 flex sm:flex-col justify-between items-baseline sm:items-end">
                                            <span class="text-xs font-medium text-slate-400 block">Amount</span>
                                            <strong class="text-lg sm:text-xl font-black text-slate-900 tracking-tight block sm:mt-1">₱{{ number_format($notification['amount'], 2) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-8 text-center py-8">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-base font-extrabold text-slate-900">You're all caught up!</h3>
                                <p class="mt-1 text-xs text-slate-500">New payment reminders and receipt updates will appear here automatically.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- TAB 2: MONTHLY PAYMENT SCHEDULE -->
                <div x-show="activeTab === 'monthly'" x-cloak class="space-y-8">
                    
                    <!-- Fee Inquiry Banner -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-900">Need Assistance with Tuition Balances?</h3>
                                <p class="text-xs text-slate-500">Contact the Finance & IT Office with student name and ID for rapid balance checks.</p>
                            </div>
                        </div>

                        <a href="mailto:zhairel.lingasa@gmail.com?subject=AMIS%20Tuition%20Fee%20or%20Balance%20Concern" class="inline-flex items-center gap-2 rounded-xl bg-slate-50 border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shrink-0">
                            <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>Email Support</span>
                        </a>
                    </div>

                    @if(empty($monthlyGroups))
                        <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                            <h3 class="text-base font-extrabold text-slate-900">No payment schedule yet</h3>
                            <p class="mt-1 text-xs text-slate-500">The Statement of Account may not have been generated. Please check again later.</p>
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

                        <!-- Filter Switcher Buttons -->
                        <div class="flex items-center gap-2">
                            @foreach(['current' => 'CURRENT DUE', 'upcoming' => 'UPCOMING', 'paid' => 'PAID MONTHS'] as $filterValue => $filterLabel)
                                <button type="button"
                                        @click="monthFilter = '{{ $filterValue }}'; openMonth = null"
                                        :class="monthFilter === '{{ $filterValue }}' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
                                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wide transition">
                                    <span>{{ $filterLabel }}</span>
                                    <span :class="monthFilter === '{{ $filterValue }}' ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2 py-0.5 text-[10px] font-extrabold">
                                        {{ $monthFilterCounts[$filterValue] }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <!-- Month Cards List -->
                        <div class="space-y-4">
                            @foreach($orderedMonthlyGroups as $monthKey => $group)
                                @php
                                    $isCurrent = $group['is_current'];
                                    $allPaid = $group['unpaid_count'] === 0;
                                    $isOverdue = !$isCurrent && $group['is_overdue'];
                                    $hasAdvanceApplied = ! $allPaid && ! $isCurrent && ! $isOverdue && (float) $group['total_paid'] > 0.01;
                                    $mainAmount = $allPaid ? 0 : $group['total_remaining'];
                                    $monthLabel = $group['month_number'] === 0 ? 'Enrollment / Initial Payment' : mb_strtoupper($group['month_label']);
                                    $monthAbbr = strtoupper(substr($group['month_number'] === 0 ? 'ENR' : $group['month_label'], 0, 3));
                                    $monthCardFilter = $allPaid ? 'paid' : (($isCurrent || $isOverdue) ? 'current' : 'upcoming');
                                @endphp

                                <div x-show="monthFilter === '{{ $monthCardFilter }}'" x-cloak class="rounded-3xl border border-slate-200/80 bg-white overflow-hidden shadow-sm transition hover:border-slate-300">
                                    <div class="p-6 sm:p-8 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 cursor-pointer" @click="openMonth = openMonth === {{ Js::from($monthKey) }} ? null : {{ Js::from($monthKey) }}">
                                        <div class="flex items-center gap-4">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $allPaid ? 'bg-emerald-50 text-emerald-700' : ($isOverdue ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700') }} font-black text-sm shrink-0 uppercase">
                                                {{ $monthAbbr }}
                                            </div>

                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h3 class="text-base font-black text-slate-900">{{ $monthLabel }}</h3>
                                                    @if($allPaid)
                                                        <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800 uppercase tracking-wide">PAID IN FULL</span>
                                                    @elseif($isOverdue)
                                                        <span class="rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[10px] font-extrabold text-rose-800 uppercase tracking-wide">PAST DUE</span>
                                                    @elseif($isCurrent)
                                                        <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800 uppercase tracking-wide">CURRENT</span>
                                                    @else
                                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-extrabold text-slate-600 uppercase tracking-wide">UPCOMING</span>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-slate-400 font-medium">Due {{ strtoupper($group['due_date']->format('F Y')) }}</span>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between lg:justify-end gap-6 border-t lg:border-t-0 border-slate-100 pt-4 lg:pt-0">
                                            <div>
                                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">{{ $allPaid ? 'Status' : 'Remaining Balance' }}</span>
                                                <strong class="text-lg font-black {{ $allPaid ? 'text-emerald-700' : 'text-slate-900' }}">₱{{ number_format($mainAmount, 2) }}</strong>
                                            </div>

                                            <div class="flex items-center gap-2 text-xs font-bold text-emerald-700">
                                                <span x-text="openMonth === {{ Js::from($monthKey) }} ? 'Hide Details' : 'View Fee Breakdown'"></span>
                                                <svg :class="openMonth === {{ Js::from($monthKey) }} ? 'rotate-180' : ''" class="w-4 h-4 transition transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Collapsible Breakdown Panel -->
                                    <div x-show="openMonth === {{ Js::from($monthKey) }}" x-collapse class="border-t border-slate-100 bg-slate-50/50 p-6 sm:p-8 space-y-4">
                                        <div class="text-xs font-black uppercase tracking-wider text-slate-500">Student Breakdown for {{ $monthLabel }}</div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            @foreach($group['children'] as $child)
                                                @php
                                                    $childPaymentStatus = $child['is_paid'] ? 'PAID' : ((float) $child['verified_paid'] > 0.01 ? 'PARTIAL' : 'UNPAID');
                                                    $statusBadge = match($childPaymentStatus) {
                                                        'PAID' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                        'PARTIAL' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                        default => 'bg-slate-100 text-slate-700 border-slate-200'
                                                    };
                                                    $cName = $child['full_name'];
                                                    $cInitial = mb_substr($cName, 0, 1);
                                                    $cAccentBg = str_contains(strtoupper($cName), 'MARYAM') ? 'bg-blue-50 text-blue-700 border-blue-200' : (str_contains(strtoupper($cName), 'YUSUF') ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200');
                                                @endphp
                                                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 flex flex-col justify-between shadow-sm hover:shadow transition">
                                                    <div>
                                                        <div class="flex items-center justify-between gap-3">
                                                            <div class="flex items-center gap-3">
                                                                <div class="flex h-10 w-10 items-center justify-center rounded-xl border {{ $cAccentBg }} text-sm font-black shrink-0">
                                                                    {{ $cInitial }}
                                                                </div>
                                                                <div>
                                                                    <strong class="text-sm font-black text-slate-900 block leading-tight">{{ mb_strtoupper($child['full_name']) }}</strong>
                                                                    <span class="text-xs text-slate-500 font-semibold">{{ $child['grade_level'] }} · ID {{ $child['student_number'] }}</span>
                                                                </div>
                                                            </div>
                                                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide {{ $statusBadge }} shrink-0">
                                                                {{ $childPaymentStatus }}
                                                            </span>
                                                        </div>

                                                        <div class="mt-5 grid grid-cols-2 gap-3 pt-4 border-t border-slate-100">
                                                            <div>
                                                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Remaining Balance</span>
                                                                <strong class="text-base font-black text-slate-900 block mt-0.5">₱{{ number_format($child['remaining_amount'], 2) }}</strong>
                                                            </div>

                                                            <div class="text-right">
                                                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Paid</span>
                                                                <strong class="text-base font-black text-emerald-700 block mt-0.5">₱{{ number_format($child['verified_paid'], 2) }}</strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <!-- Upload Payment Proof Action Card for Current Month -->
                            <div x-show="monthFilter === 'current'" x-cloak class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-8 sm:p-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 shadow-sm">
                                <div class="space-y-1">
                                    <span class="rounded-full bg-emerald-100 border border-emerald-200 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wide text-emerald-800">
                                        ONLINE PAYMENT GATEWAY
                                    </span>
                                    <h3 class="text-lg font-black text-slate-900">Submit Payment Proof for Verification</h3>
                                    <p class="text-xs text-slate-600 max-w-xl leading-relaxed">
                                        Pay via GCash QR or Bank Transfer, then upload your transaction receipt. AMIS Finance will verify and apply payments automatically.
                                    </p>
                                </div>

                                <a href="{{ route('payment.checkout') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-6 py-3.5 text-xs font-extrabold text-white hover:bg-emerald-800 shadow-sm transition shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span>Upload Payment Proof</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- TAB 3: TRANSACTION & RECEIPT HISTORY -->
                <div x-show="activeTab === 'transactions'" x-cloak class="space-y-6">
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-10 lg:p-12 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 shrink-0">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-900">Payment & Transaction History</h2>
                                    <p class="text-xs text-slate-500">All submitted receipts and approved Official Receipts (OR) recorded in the AMIS audit trail.</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @foreach(['all' => 'ALL', 'pending' => 'PENDING', 'verified' => 'VERIFIED', 'rejected' => 'REJECTED'] as $filterValue => $filterLabel)
                                    <button type="button"
                                            @click="transactionFilter = '{{ $filterValue }}'"
                                            :class="transactionFilter === '{{ $filterValue }}' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                            class="rounded-xl px-3.5 py-1.5 text-xs font-bold uppercase tracking-wide transition">
                                        {{ $filterLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if($unpostedPaymentSubmissions->isNotEmpty() || $familyFinanceTransactions->isNotEmpty())
                            <div class="mt-6 space-y-3">
                                @foreach($unpostedPaymentSubmissions as $submission)
                                    @php
                                        $effectiveStatus = $submission->effective_status;
                                        $displayPaymentNumber = $submission->submission_number;
                                        $historyStatusLabel = match($effectiveStatus) {
                                            'rejected' => 'REJECTED',
                                            'verified' => 'VERIFIED',
                                            default => 'UNDER REVIEW',
                                        };
                                        $historyStatusClass = match($effectiveStatus) {
                                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            'verified' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            default => 'bg-amber-50 text-amber-700 border-amber-200',
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

                                    <div x-show="transactionFilter === 'all' || transactionFilter === '{{ $effectiveStatus }}'"
                                         @click="openTransaction({{ Js::from($transactionData) }})"
                                         class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 cursor-pointer hover:border-emerald-300 hover:shadow-md transition">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2.5">
                                                <span class="rounded-full border px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide {{ $historyStatusClass }}">
                                                    {{ $historyStatusLabel }}
                                                </span>
                                                <span class="text-xs font-semibold text-slate-500 uppercase">{{ $pendingMethodLabel }}</span>
                                            </div>
                                            <h4 class="text-sm sm:text-base font-bold text-slate-900">Submission #{{ $displayPaymentNumber }}</h4>
                                            <p class="text-xs text-slate-500">
                                                Ref: {{ $submission->reference_no ?: 'None' }} · Submitted {{ $submission->submitted_at ? $submission->submitted_at->format('M j, Y') : '' }}
                                            </p>
                                        </div>

                                        <div class="sm:text-right shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 flex sm:flex-col justify-between items-baseline sm:items-end">
                                            <strong class="text-base sm:text-lg font-black text-slate-900 block">₱{{ number_format($submission->total_amount, 2) }}</strong>
                                            <span class="text-xs text-emerald-700 font-bold sm:mt-1">View Details →</span>
                                        </div>
                                    </div>
                                @endforeach

                                @foreach($familyFinanceTransactions as $record)
                                    <div x-show="transactionFilter === 'all' || transactionFilter === 'verified'"
                                         class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2.5">
                                                <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold text-emerald-800 uppercase tracking-wide">
                                                    VERIFIED OFFICIAL RECEIPT
                                                </span>
                                                <span class="text-xs font-semibold text-slate-500 uppercase">{{ $record['method_label'] }}</span>
                                            </div>
                                            <h4 class="text-sm sm:text-base font-bold text-slate-900">OR #{{ $record['official_receipt_number'] }}</h4>
                                            <p class="text-xs text-slate-500">
                                                {{ $record['source'] === 'ONLINE' ? 'Approved Online Payment' : 'Recorded Onsite by Finance' }} · {{ $record['transaction_at'] ? $record['transaction_at']->format('M j, Y') : '' }}
                                            </p>
                                        </div>

                                        <div class="sm:text-right shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 flex sm:flex-col justify-between items-baseline sm:items-end">
                                            <strong class="text-base sm:text-lg font-black text-slate-900 block">₱{{ number_format($record['amount'], 2) }}</strong>
                                            <span class="text-xs text-emerald-700 font-bold sm:mt-1">Official Audit Record</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-8 text-center py-8">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14.25l2.25 2.25L15 12.75M6.75 3.75h10.5A2.25 2.25 0 0119.5 6v12A2.25 2.25 0 0117.25 20.25H6.75A2.25 2.25 0 014.5 18V6a2.25 2.25 0 012.25-2.25z"/>
                                    </svg>
                                </div>
                                <h3 class="mt-3 text-base font-extrabold text-slate-900">No payment receipts yet</h3>
                                <p class="mt-1 text-xs text-slate-500">Submitted receipts and their verification status will appear here.</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Add Student Modal -->
        <div x-show="showAddStudent" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="!linkLoading && (showAddStudent = false)">
            <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl" role="dialog" aria-modal="true">
                <div class="h-2 bg-gradient-to-r from-[#065f46] via-emerald-600 to-teal-500"></div>
                <div class="p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-emerald-800">STUDENT ACCOUNT</span>
                            <h2 class="mt-2 text-xl font-black text-slate-900">Link Student Account</h2>
                        </div>
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100" @click="showAddStudent = false" :disabled="linkLoading">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="mt-3 text-xs leading-relaxed text-slate-600">Enter the student's official school ID. AMIS will verify the parent email from enrollment records.</p>
                    <form class="mt-5 space-y-4" @submit.prevent="linkStudent()">
                        <div>
                            <label for="student-number" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Student Number / ID</label>
                            <input id="student-number" type="text" x-model.trim="studentNumber" class="mt-1.5 w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-sm focus:border-emerald-600 focus:bg-white focus:ring-emerald-600" placeholder="e.g. 260001" required :disabled="linkLoading">
                        </div>
                        <p x-show="linkError" x-text="linkError" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-800"></p>
                        <p x-show="linkSuccess" x-text="linkSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-bold text-emerald-800"></p>
                        <div class="flex gap-3 pt-3 border-t border-slate-100">
                            <button type="button" class="flex-1 rounded-xl border border-slate-300 bg-white py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50" @click="showAddStudent = false" :disabled="linkLoading">Cancel</button>
                            <button type="submit" class="flex-1 rounded-xl bg-emerald-700 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-800 disabled:opacity-60" :disabled="linkLoading">
                                <span x-text="linkLoading ? 'Linking…' : 'Link Account'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Transaction Details Modal -->
        <div x-show="showTransaction" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="showTransaction = false">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl" role="dialog" aria-modal="true">
                <div class="h-2 bg-gradient-to-r from-[#065f46] via-emerald-600 to-teal-500"></div>
                <div class="p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-emerald-800" x-text="transaction?.status === 'verified' ? 'VERIFIED RECEIPT' : 'PAYMENT SUBMISSION'"></span>
                            <h2 class="mt-2 text-xl font-black text-slate-900" x-text="transaction?.number"></h2>
                            <p class="text-xs text-slate-400" x-text="transaction?.date"></p>
                        </div>
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100" @click="showTransaction = false">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <template x-if="transaction">
                        <div class="mt-6 space-y-4">
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="rounded-xl bg-slate-50 p-3"><span class="font-bold text-slate-400 uppercase block">Status</span><strong class="text-slate-900 uppercase" x-text="transaction.status"></strong></div>
                                <div class="rounded-xl bg-slate-50 p-3"><span class="font-bold text-slate-400 uppercase block">Method</span><strong class="text-slate-900" x-text="transaction.method"></strong></div>
                                <div class="rounded-xl bg-slate-50 p-3"><span class="font-bold text-slate-400 uppercase block">Amount</span><strong class="text-slate-900" x-text="money(transaction.total)"></strong></div>
                                <div class="rounded-xl bg-slate-50 p-3"><span class="font-bold text-slate-400 uppercase block">Reference</span><strong class="text-slate-900 break-all" x-text="transaction.reference || 'None'"></strong></div>
                            </div>

                            <div x-show="transaction.receipt" class="pt-2">
                                <a :href="transaction.receipt" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2.5 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>View Uploaded Proof</span>
                                </a>
                            </div>
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
