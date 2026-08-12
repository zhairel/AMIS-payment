<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Finance receipt review</h2></x-slot>
    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(360px,.8fr)]">
        <section class="space-y-5">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><p class="font-mono text-xs text-slate-500">{{ $receipt->submission_id }}</p><h1 class="text-xl font-bold text-slate-900">Original receipt</h1></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ str_replace('_', ' ', $receipt->status) }}</span></div>
                @if($receipt->original_mime === 'application/pdf')
                    <iframe class="h-[650px] w-full rounded-xl border" src="{{ route('payment.receipts.original', $receipt) }}"></iframe>
                @else
                    <img class="max-h-[750px] w-full rounded-xl bg-slate-100 object-contain" src="{{ route('payment.receipts.original', $receipt) }}" alt="Original uploaded receipt">
                @endif
                <p class="mt-3 text-xs text-slate-500">The original is immutable. OCR uses a separate processed copy.</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="font-bold">OCR attempts and audit trail</h2>
                <div class="mt-4 space-y-3">@foreach($receipt->ocrResults as $result)<details class="rounded-xl border p-3"><summary class="cursor-pointer font-semibold">#{{ $result->attempt_number }} {{ $result->engine }} · {{ $result->status }} · {{ $result->confidence !== null ? round($result->confidence * 100, 1).'%' : 'no confidence' }}</summary><pre class="mt-3 max-h-72 overflow-auto whitespace-pre-wrap text-xs">{{ $result->raw_text ?: 'No OCR text returned.' }}</pre></details>@endforeach</div>
                <ol class="mt-6 space-y-3 border-l-2 border-slate-200 pl-4">@foreach($receipt->auditLogs->sortByDesc('created_at') as $log)<li><strong>{{ str_replace('_', ' ', $log->event) }}</strong><div class="text-xs text-slate-500">{{ $log->created_at }} · {{ $log->user?->name ?: 'System' }}</div>@if($log->notes)<p class="mt-1 text-sm">{{ $log->notes }}</p>@endif</li>@endforeach</ol>
            </div>
        </section>
        <aside class="space-y-5">
            @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="font-bold">Automated checks</h2>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Quality</dt><dd class="font-semibold">{{ $receipt->quality_score ?? '—' }}/100</dd></div><div><dt class="text-slate-500">Duplicate</dt><dd class="font-semibold">{{ $receipt->duplicate_status }}</dd></div><div><dt class="text-slate-500">Primary OCR</dt><dd class="font-semibold">{{ $receipt->primary_ocr_engine ?: '—' }}</dd></div><div><dt class="text-slate-500">Confidence</dt><dd class="font-semibold">{{ $receipt->ocr_confidence !== null ? round($receipt->ocr_confidence * 100, 1).'%' : '—' }}</dd></div></dl>
                @if($receipt->review_reason)<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $receipt->review_reason }}</p>@endif
            </div>
            <form method="POST" action="{{ route('finance.receipts.update', $receipt) }}" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">@csrf @method('PATCH')
                <h2 class="font-bold">Extracted information</h2>
                @foreach(['provider' => 'Provider', 'reference_number' => 'Transaction / reference number', 'amount' => 'Amount', 'currency' => 'Currency', 'transaction_date' => 'Transaction date', 'transaction_time' => 'Transaction time', 'sender_name' => 'Sender', 'receiver_name' => 'Receiver', 'transaction_status' => 'Transaction status'] as $field => $label)
                    <label class="block text-sm"><span class="mb-1 block font-medium text-slate-700">{{ $label }}</span><input class="w-full rounded-lg border-slate-300" name="{{ $field }}" value="{{ old($field, $field === 'transaction_date' ? $receipt->transaction_date?->format('Y-m-d') : ($field === 'transaction_time' ? substr((string)$receipt->transaction_time, 0, 5) : $receipt->{$field})) }}"></label>
                @endforeach
                <label class="block text-sm"><span class="mb-1 block font-medium">Reason for correction</span><textarea class="w-full rounded-lg border-slate-300" name="notes" required></textarea></label>
                <button class="w-full rounded-xl bg-slate-800 px-4 py-3 font-bold text-white">Save corrections</button>
            </form>
            <form method="POST" action="{{ route('finance.receipts.action', $receipt) }}" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">@csrf
                <h2 class="font-bold">Finance decision</h2><textarea class="w-full rounded-lg border-slate-300" name="notes" required placeholder="Required decision notes"></textarea>
                <div class="grid gap-2"><button name="action" value="approve" class="rounded-xl bg-emerald-700 px-4 py-3 font-bold text-white">Approve receipt</button><button name="action" value="request_reupload" class="rounded-xl bg-amber-500 px-4 py-3 font-bold text-white">Request re-upload</button><button name="action" value="reject" class="rounded-xl bg-rose-700 px-4 py-3 font-bold text-white">Reject receipt</button></div>
                <p class="text-xs text-slate-500">Approving posts the verified amount automatically to the family’s oldest outstanding balances. Any excess becomes advance credit.</p>
            </form>
        </aside>
    </div>
</x-app-layout>
