<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.reminder.index') }}"
               class="text-slate-400 hover:text-slate-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ $campaign->name }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">School Year {{ $campaign->school_year }} · Payment Reminder Campaign</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Messages --}}
            @foreach(['success', 'error', 'info', 'warning'] as $type)
                @if(session($type))
                    @php
                        $colors = [
                            'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                            'error'   => 'bg-red-50 border-red-200 text-red-800',
                            'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
                            'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
                        ];
                    @endphp
                    <div class="flex items-start gap-3 border rounded-xl px-4 py-3 text-sm {{ $colors[$type] }}">
                        <p>{{ session($type) }}</p>
                    </div>
                @endif
            @endforeach

            @if(!empty($missingImages))
                <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-semibold">Missing email images:</p>
                        <ul class="list-disc list-inside mt-1 text-xs space-y-0.5">
                            @foreach($missingImages as $key => $path)
                                <li><code>{{ basename($path) }}</code></li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-xs">Place the image files in <code>public/images/reminder/</code> before sending.</p>
                    </div>
                </div>
            @endif

            {{-- SMTP Paused Notice --}}
            @if($campaign->status === \App\Models\ReminderCampaign::STATUS_PAUSED)
                <div class="bg-amber-50 border border-amber-300 rounded-xl px-5 py-4">
                    <p class="font-bold text-amber-800 text-sm mb-1">⏸ Email sending has been temporarily paused.</p>
                    @if($campaign->paused_reason)
                        <p class="text-xs text-amber-700 mb-2">Reason: {{ $campaign->paused_reason }}</p>
                    @endif
                    <p class="text-xs text-amber-700">
                        Successfully sent recipients will <strong>not</strong> receive another email.
                        Remaining unsent emails will continue when you click <strong>Resume Pending Emails</strong>.
                    </p>
                </div>
            @endif

            {{-- ── STATS CARDS ──────────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                @php
                    $stats = [
                        ['label' => 'Sources Found',   'value' => $campaign->total_sources,            'color' => 'text-slate-700'],
                        ['label' => 'Unique Parents',  'value' => $campaign->total_unique,             'color' => 'text-slate-700'],
                        ['label' => 'Duplicates Out',  'value' => $campaign->total_duplicates_removed, 'color' => 'text-slate-500'],
                        ['label' => 'Invalid',         'value' => $campaign->total_invalid,            'color' => 'text-slate-500'],
                        ['label' => 'Sent ✓',          'value' => $campaign->total_sent,               'color' => 'text-emerald-700'],
                        ['label' => 'Pending',         'value' => $campaign->total_pending + $campaign->total_retry, 'color' => 'text-amber-600'],
                        ['label' => 'Failed',          'value' => $campaign->total_failed,             'color' => 'text-red-600'],
                    ];
                @endphp
                @foreach($stats as $stat)
                    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center shadow-sm">
                        <p class="text-2xl font-black {{ $stat['color'] }}">{{ number_format($stat['value']) }}</p>
                        <p class="text-xs text-slate-500 mt-1 font-medium">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- ── CAMPAIGN DETAILS ─────────────────────────────────────────── --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
                    <h2 class="font-bold text-slate-800 text-sm">Campaign Details</h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $campaign->status_color }}">
                        {{ $campaign->status_label }}
                    </span>
                </div>
                <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Sender Name</p>
                        <p class="font-semibold text-slate-800">{{ env('REMINDER_MAIL_FROM_NAME', 'AMIS Support Staff') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Sender Email</p>
                        <p class="font-semibold text-slate-800">{{ env('REMINDER_MAIL_FROM_ADDRESS', config('mail.from.address')) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Subject</p>
                        <p class="font-semibold text-slate-800">AMIS Payment Reminder – Monthly School Fees</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Prepared By</p>
                        <p class="font-semibold text-slate-800">{{ $campaign->sentBy?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Created</p>
                        <p class="font-semibold text-slate-800">{{ $campaign->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Started</p>
                        <p class="font-semibold text-slate-800">{{ $campaign->started_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Completed</p>
                        <p class="font-semibold text-slate-800">{{ $campaign->completed_at?->format('M d, Y h:i A') ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- ── WORKFLOW ACTIONS ──────────────────────────────────────────── --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60">
                    <h2 class="font-bold text-slate-800 text-sm">Actions</h2>
                </div>
                <div class="p-5 space-y-4">

                    {{-- Step indicator --}}
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 select-none mb-2">
                        <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">1. Prepare</span>
                        <span class="text-slate-300">→</span>
                        <span class="px-2.5 py-1 rounded-full {{ $campaign->status !== \App\Models\ReminderCampaign::STATUS_DRAFT ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">2. Preview</span>
                        <span class="text-slate-300">→</span>
                        <span class="px-2.5 py-1 rounded-full {{ !in_array($campaign->status, ['DRAFT', 'PROCESSING']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">3. Test</span>
                        <span class="text-slate-300">→</span>
                        <span class="px-2.5 py-1 rounded-full {{ $campaign->started_at ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">4. Confirm</span>
                        <span class="text-slate-300">→</span>
                        <span class="px-2.5 py-1 rounded-full {{ $campaign->isFinished() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">5. Done</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

                        {{-- Preview Email --}}
                        <a href="{{ route('admin.reminder.logs', $campaign) }}?preview=1"
                           onclick="document.getElementById('emailPreviewModal').classList.remove('hidden'); return false;"
                           class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-200
                                  hover:border-emerald-300 hover:bg-emerald-50 transition group">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 group-hover:bg-emerald-100 flex items-center justify-center transition">
                                <svg class="w-5 h-5 text-slate-500 group-hover:text-emerald-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Preview Email</p>
                                <p class="text-xs text-slate-500">View final email with 3 images</p>
                            </div>
                        </a>

                        {{-- Send Test Email --}}
                        <button onclick="document.getElementById('testEmailModal').classList.remove('hidden')"
                                class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-200
                                       hover:border-blue-300 hover:bg-blue-50 transition group text-left w-full">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 group-hover:bg-blue-100 flex items-center justify-center transition">
                                <svg class="w-5 h-5 text-slate-500 group-hover:text-blue-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Send Test Email</p>
                                <p class="text-xs text-slate-500">Verify email without affecting stats</p>
                            </div>
                        </button>

                        {{-- Start / Resume / Status --}}
                        @if($campaign->canStart())
                            <button onclick="document.getElementById('confirmSendModal').classList.remove('hidden')"
                                    class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-emerald-300
                                           bg-emerald-50 hover:bg-emerald-100 transition group text-left w-full">
                                <div class="w-9 h-9 rounded-lg bg-emerald-100 group-hover:bg-emerald-200 flex items-center justify-center transition">
                                    <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-emerald-800">Confirm & Start Sending</p>
                                    <p class="text-xs text-emerald-600">Sends to {{ number_format($campaign->total_unique) }} unique parents</p>
                                </div>
                            </button>
                        @elseif($campaign->canResume())
                            <form method="POST" action="{{ route('admin.reminder.resume', $campaign) }}" id="resumeForm">
                                @csrf
                                <button type="submit"
                                        onclick="this.disabled=true; this.innerHTML='Resuming…'; this.closest('form').submit()"
                                        class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-amber-300
                                               bg-amber-50 hover:bg-amber-100 transition group text-left w-full">
                                    <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-amber-800">Resume Pending Emails</p>
                                        <p class="text-xs text-amber-600">Only PENDING & RETRY — SENT are safe</p>
                                    </div>
                                </button>
                            </form>
                        @elseif($campaign->status === \App\Models\ReminderCampaign::STATUS_PROCESSING)
                            <div class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-blue-200 bg-blue-50">
                                <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-blue-800">Sending in Progress…</p>
                                    <p class="text-xs text-blue-600">{{ number_format($campaign->total_sent) }} sent · {{ number_format($campaign->total_pending + $campaign->total_retry) }} remaining</p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-emerald-200 bg-emerald-50/60">
                                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-700" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-emerald-800">{{ $campaign->status_label }}</p>
                                    <p class="text-xs text-emerald-600">{{ number_format($campaign->total_sent) }} emails delivered</p>
                                </div>
                            </div>
                        @endif

                        {{-- Pause --}}
                        @if($campaign->status === \App\Models\ReminderCampaign::STATUS_PROCESSING)
                            <form method="POST" action="{{ route('admin.reminder.pause', $campaign) }}">
                                @csrf
                                <input type="hidden" name="reason" value="Manually paused by staff">
                                <button type="submit"
                                        class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-200
                                               hover:border-slate-300 hover:bg-slate-50 transition group text-left w-full">
                                    <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-700">Pause Campaign</p>
                                        <p class="text-xs text-slate-500">SENT emails are always safe</p>
                                    </div>
                                </button>
                            </form>
                        @endif

                        {{-- View Logs --}}
                        <a href="{{ route('admin.reminder.logs', $campaign) }}"
                           class="flex items-center gap-3 px-4 py-3.5 rounded-xl border border-slate-200
                                  hover:border-slate-300 hover:bg-slate-50 transition group">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 group-hover:bg-slate-200 flex items-center justify-center transition">
                                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">View Delivery Logs</p>
                                <p class="text-xs text-slate-500">Detailed recipient status</p>
                            </div>
                        </a>

                    </div>
                </div>
            </div>

            {{-- Progress bar --}}
            @if($campaign->total_unique > 0 && !$campaign->canStart())
                @php $pct = round(($campaign->total_sent / $campaign->total_unique) * 100) @endphp
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between text-sm mb-3">
                        <span class="font-semibold text-slate-700">Delivery Progress</span>
                        <span class="font-bold text-emerald-700">{{ $pct }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-3">
                        <div class="bg-emerald-600 h-3 rounded-full transition-all duration-500"
                             style="width: {{ $pct }}%"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500 mt-2">
                        <span>{{ number_format($campaign->total_sent) }} sent</span>
                        <span>{{ number_format($campaign->total_unique) }} total unique</span>
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- ── MODALS ──────────────────────────────────────────────────────────── --}}

    {{-- Test Email Modal --}}
    <div id="testEmailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
         onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8" onclick="event.stopPropagation()">
            <h2 class="text-lg font-black text-slate-900 mb-2">Send Test Email</h2>
            <p class="text-sm text-slate-500 mb-5">
                The test email includes the complete final email with all 3 official images.
                <strong>No recipient records or campaign stats will be changed.</strong>
            </p>
            <form method="POST" action="{{ route('admin.reminder.send-test', $campaign) }}">
                @csrf
                <div class="mb-4">
                    <label for="test_email" class="block text-sm font-semibold text-slate-700 mb-1.5">Test Email Address</label>
                    <input type="email" id="test_email" name="test_email" required
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium
                                  focus:ring-2 focus:ring-blue-500 outline-none transition"
                           placeholder="your-email@example.com">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('testEmailModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            onclick="this.disabled=true; this.textContent='Sending…'; this.closest('form').submit()"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition">
                        Send Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Confirm Send Modal --}}
    <div id="confirmSendModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
         onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-900">Confirm One-Time Send</h2>
                    <p class="text-xs text-slate-500">This cannot be undone.</p>
                </div>
            </div>

            <div class="bg-slate-50 rounded-xl p-4 mb-5 text-sm space-y-2">
                <div class="flex justify-between">
                    <span class="text-slate-500">Unique Parents:</span>
                    <span class="font-bold text-slate-900">{{ number_format($campaign->total_unique) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Sender:</span>
                    <span class="font-bold text-slate-900">{{ env('REMINDER_MAIL_FROM_NAME', 'AMIS Support Staff') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Subject:</span>
                    <span class="font-bold text-slate-900 text-right ml-4">AMIS Payment Reminder – Monthly School Fees</span>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-5 text-xs text-amber-800">
                <p class="font-semibold">⚠ Important:</p>
                <p class="mt-1">Each parent email will receive <strong>exactly one</strong> email.
                Once marked SENT, they will <strong>never receive this campaign again</strong>,
                even if the server restarts or staff clicks Resume later.</p>
            </div>

            <form method="POST" action="{{ route('admin.reminder.start', $campaign) }}" id="confirmStartForm">
                @csrf
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('confirmSendModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit" id="confirmStartBtn"
                            onclick="
                                this.disabled = true;
                                this.textContent = 'Starting…';
                                document.getElementById('confirmStartForm').submit();
                            "
                            class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800
                                   text-white text-sm font-bold transition disabled:opacity-60 disabled:cursor-not-allowed">
                        ✓ Confirm & Start Sending
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Email Preview Modal --}}
    <div id="emailPreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4"
         onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl h-[85vh] flex flex-col overflow-hidden" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h2 class="font-bold text-slate-900">Email Preview</h2>
                <button onclick="document.getElementById('emailPreviewModal').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-4 bg-slate-100">
                <iframe src="{{ route('admin.reminder.logs', ['campaign' => $campaign, 'preview_frame' => 1]) }}"
                        class="w-full h-full min-h-[600px] bg-white rounded-xl border border-slate-200"
                        title="Email Preview">
                </iframe>
            </div>
        </div>
    </div>

</x-app-layout>
