<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.reminder.preview', $campaign) }}"
               class="text-slate-400 hover:text-slate-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Delivery Logs</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ $campaign->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- Quick Stats Bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach([
                    ['SENT',       $campaign->total_sent,                                    'text-emerald-700', 'bg-emerald-50 border-emerald-200'],
                    ['PENDING',    $campaign->total_pending,                                  'text-slate-700',   'bg-slate-50  border-slate-200'],
                    ['RETRY',      $campaign->total_retry,                                    'text-amber-700',   'bg-amber-50  border-amber-200'],
                    ['FAILED',     $campaign->total_failed,                                   'text-red-700',     'bg-red-50    border-red-200'],
                    ['TOTAL',      $campaign->total_unique,                                   'text-slate-700',   'bg-white     border-slate-200'],
                ] as [$label, $count, $textColor, $bgBorder])
                    <a href="{{ route('admin.reminder.logs', ['campaign' => $campaign, 'status' => strtolower($label)]) }}"
                       class="block text-center px-4 py-3.5 rounded-xl border {{ $bgBorder }} shadow-sm hover:shadow transition">
                        <p class="text-2xl font-black {{ $textColor }}">{{ number_format($count) }}</p>
                        <p class="text-xs font-semibold text-slate-500 mt-0.5">{{ $label }}</p>
                    </a>
                @endforeach
            </div>

            {{-- Filter Bar --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <form method="GET" action="{{ route('admin.reminder.logs', $campaign) }}"
                      class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search email…"
                           class="flex-1 min-w-[200px] px-4 py-2 rounded-xl border border-slate-200 text-sm
                                  focus:ring-2 focus:ring-emerald-500 outline-none transition">
                    <select name="status"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-sm
                                   focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white">
                        <option value="">All Statuses</option>
                        @foreach(['SENT', 'PENDING', 'PROCESSING', 'RETRY', 'FAILED'] as $s)
                            <option value="{{ strtolower($s) }}" {{ request('status') === strtolower($s) ? 'selected' : '' }}>
                                {{ $s }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold transition">
                        Filter
                    </button>
                    @if(request('status') || request('search'))
                        <a href="{{ route('admin.reminder.logs', $campaign) }}"
                           class="px-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-500 hover:bg-slate-50 transition">
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            {{-- Recipients Table --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60">
                                <th class="text-left px-5 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">#</th>
                                <th class="text-left px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Email</th>
                                <th class="text-left px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Parent Name</th>
                                <th class="text-center px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Status</th>
                                <th class="text-center px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Attempts</th>
                                <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Sent At</th>
                                <th class="text-right px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Next Retry</th>
                                <th class="text-left px-4 py-3.5 font-semibold text-slate-600 text-xs uppercase tracking-wide">Last Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recipients as $r)
                                <tr class="hover:bg-slate-50/40 transition-colors">
                                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                                        {{ $recipients->firstItem() + $loop->index }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="font-mono text-xs text-slate-800">{{ $r->normalized_email }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-600">
                                        {{ $r->parent_name ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $r->status_color }}">
                                            {{ $r->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center font-mono text-xs text-slate-600">
                                        {{ $r->attempts }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right text-xs text-slate-500">
                                        {{ $r->sent_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right text-xs text-amber-600">
                                        {{ $r->next_retry_at?->format('M d, H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-red-600 max-w-[200px]">
                                        @if($r->last_error)
                                            <span title="{{ $r->last_error }}" class="truncate block max-w-[180px]">
                                                {{ $r->last_error }}
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-400">
                                        No recipients found matching your filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($recipients->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100">
                        {{ $recipients->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
