<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Payment Reminder</h1>
                <p class="text-sm text-slate-500 mt-0.5">One-time campaign email to enrolled parents & guardians</p>
            </div>
            <button onclick="document.getElementById('newCampaignModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800
                           text-white text-sm font-bold shadow-sm transition-all duration-150 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                New Campaign
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            {{-- Campaign List --}}
            @if($campaigns->isEmpty())
                <div class="bg-white rounded-2xl border border-slate-200 p-16 text-center shadow-sm">
                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-lg font-bold text-slate-800 mb-1">No campaigns yet</p>
                    <p class="text-sm text-slate-500 mb-6">Create your first payment reminder campaign to get started.</p>
                    <button onclick="document.getElementById('newCampaignModal').classList.remove('hidden')"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-700 hover:bg-emerald-800
                                   text-white text-sm font-bold rounded-xl transition">
                        Create First Campaign
                    </button>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/60">
                                    <th class="text-left px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Campaign</th>
                                    <th class="text-center px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Status</th>
                                    <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Unique</th>
                                    <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide text-emerald-700">Sent</th>
                                    <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Pending</th>
                                    <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide text-red-600">Failed</th>
                                    <th class="text-left px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Sent By</th>
                                    <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Created</th>
                                    <th class="px-4 py-3.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($campaigns as $campaign)
                                    <tr class="hover:bg-slate-50/60 transition-colors group">
                                        <td class="px-5 py-4">
                                            <p class="font-semibold text-slate-900">{{ $campaign->name }}</p>
                                            <p class="text-xs text-slate-400 mt-0.5">SY {{ $campaign->school_year }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $campaign->status_color }}">
                                                {{ $campaign->status_label }}
                                            </span>
                                            @if($campaign->paused_reason)
                                                <p class="text-xs text-amber-600 mt-1 max-w-[140px] mx-auto truncate" title="{{ $campaign->paused_reason }}">
                                                    {{ $campaign->paused_reason }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right font-mono text-slate-700 font-medium">{{ number_format($campaign->total_unique) }}</td>
                                        <td class="px-4 py-4 text-right font-mono text-emerald-700 font-bold">{{ number_format($campaign->total_sent) }}</td>
                                        <td class="px-4 py-4 text-right font-mono text-slate-500">{{ number_format($campaign->total_pending + $campaign->total_retry) }}</td>
                                        <td class="px-4 py-4 text-right font-mono text-red-600">{{ number_format($campaign->total_failed) }}</td>
                                        <td class="px-4 py-4 text-slate-600 text-xs">{{ $campaign->sentBy?->name ?? '—' }}</td>
                                        <td class="px-4 py-4 text-right text-xs text-slate-400">{{ $campaign->created_at->format('M d, Y') }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <a href="{{ route('admin.reminder.preview', $campaign) }}"
                                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100
                                                      text-emerald-700 text-xs font-semibold transition">
                                                View
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($campaigns->hasPages())
                        <div class="px-5 py-4 border-t border-slate-100">
                            {{ $campaigns->links() }}
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

    {{-- New Campaign Modal --}}
    <div id="newCampaignModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
         onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-black text-slate-900">New Payment Reminder Campaign</h2>
                <button onclick="document.getElementById('newCampaignModal').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.reminder.prepare') }}" id="prepareCampaignForm">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="campaign_name" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Campaign Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="campaign_name"
                               name="name"
                               value="{{ old('name', 'Monthly Payment Reminder – ' . now()->format('F Y')) }}"
                               required
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium
                                      focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                               placeholder="e.g. Monthly Payment Reminder – August 2026">
                    </div>
                    <div>
                        <label for="school_year" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            School Year <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="school_year"
                               name="school_year"
                               value="{{ old('school_year', '2026-2027') }}"
                               required
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium
                                      focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                               placeholder="2026-2027">
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-800">
                        <p class="font-semibold mb-1">⚠ Before preparing:</p>
                        <ul class="space-y-1 list-disc list-inside text-amber-700">
                            <li>Only parents/guardians of <strong>approved</strong> enrollment records will be included</li>
                            <li>Duplicate emails are automatically removed</li>
                            <li>Student emails are <strong>never</strong> included</li>
                        </ul>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-6">
                    <button type="button"
                            onclick="document.getElementById('newCampaignModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold
                                   text-slate-600 hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            onclick="this.disabled=true;this.textContent='Preparing…';this.closest('form').submit()"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800
                                   text-white text-sm font-bold transition disabled:opacity-60">
                        Prepare Recipients
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
