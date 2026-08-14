<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Receipt verification queue</h2></x-slot>
    <div class="mx-auto max-w-7xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>@endif
        <div class="flex flex-wrap gap-2">
            @foreach(['' => 'All', 'NEEDS_REVIEW' => 'Needs review', 'REUPLOAD_REQUIRED' => 'Re-upload', 'PENDING_VERIFICATION' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected'] as $value => $label)
                <a href="{{ route('finance.receipts.index', array_filter(['status' => $value])) }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $status === $value ? 'bg-emerald-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Receipt</th><th class="px-5 py-3">Uploader</th><th class="px-5 py-3">Extracted payment</th><th class="px-5 py-3">Quality</th><th class="px-5 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($receipts as $receipt)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4"><a class="font-semibold text-emerald-700" href="{{ route('finance.receipts.show', $receipt) }}">{{ $receipt->submission_id }}</a><div class="text-xs text-slate-500">{{ strtoupper($receipt->created_at->format('M d, Y H:i')) }}</div></td>
                        <td class="px-5 py-4">{{ $receipt->user?->name }}<div class="text-xs text-slate-500">{{ $receipt->original_filename }}</div></td>
                        <td class="px-5 py-4"><div>{{ $receipt->provider ?: 'Unknown provider' }}</div><div class="font-mono text-xs">{{ $receipt->reference_number ?: 'Reference unreadable' }}</div><div>{{ $receipt->currency }} {{ $receipt->amount }}</div></td>
                        <td class="px-5 py-4">{{ $receipt->quality_score ?? '—' }}/100<div class="text-xs text-slate-500">{{ data_get($receipt->quality_assessment, 'readability', 'not assessed') }}</div></td>
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ str_replace('_', ' ', $receipt->status) }}</span><div class="mt-1 text-xs text-amber-700">{{ $receipt->duplicate_status }}</div></td>
                    </tr>
                @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No receipt submissions found.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        {{ $receipts->links() }}
    </div>
</x-app-layout>
