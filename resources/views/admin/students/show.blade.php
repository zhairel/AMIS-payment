<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Student Record: {{ $student->first_name }} {{ $student->last_name }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">Grade Level: {{ $student->grade_level ?: 'Unassigned' }} · LRN / Student No: {{ $student->student_number }}</p>
            </div>
            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">Active Student</span>
        </div>
    </x-slot>

    <div class="py-6" x-data="studentReceiptProofModal()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Student Summary Card --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <small class="text-xs text-slate-400 font-semibold uppercase block">Student Name</small>
                    <strong class="text-slate-900 text-sm font-bold">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</strong>
                </div>
                <div>
                    <small class="text-xs text-slate-400 font-semibold uppercase block">Grade Level</small>
                    <strong class="text-slate-900 text-sm font-bold">{{ $student->grade_level ?: 'N/A' }}</strong>
                </div>
                <div>
                    <small class="text-xs text-slate-400 font-semibold uppercase block">Student Number</small>
                    <strong class="text-slate-900 text-sm font-bold font-mono">{{ $student->student_number }}</strong>
                </div>
                <div>
                    <small class="text-xs text-slate-400 font-semibold uppercase block">Parent / Guardian</small>
                    <strong class="text-slate-900 text-sm font-bold">{{ $student->user?->name ?: 'Not linked' }}</strong>
                </div>
            </div>

            {{-- Receipts & Proofs Table --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 text-sm">Payment Receipts & Uploaded Proofs</h3>
                    <span class="text-xs font-semibold text-slate-500">{{ $receipts->count() }} Submission(s)</span>
                </div>

                @if($receipts->isEmpty())
                    <div class="p-8 text-center text-slate-400 text-xs italic">
                        No uploaded receipts found for this student.
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($receipts as $receipt)
                            <div class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-slate-700">Submission #{{ $receipt->submission_id }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase {{ $receipt->status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : ($receipt->status === 'REJECTED' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ $receipt->status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        Provider: <strong>{{ $receipt->provider ?: 'GCash / Bank' }}</strong> · Ref: <strong>{{ $receipt->reference_number ?: 'PAY-'.$receipt->id }}</strong> · Date: <strong>{{ strtoupper(($receipt->transaction_date ?: $receipt->created_at)->format('M d, Y')) }}</strong> · Amount: <strong>₱{{ number_format($receipt->amount ?? 0, 2) }}</strong>
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openProofModal(@js([
                                        'id' => $receipt->submission_id,
                                        'originalUrl' => route('payment.receipts.original', $receipt),
                                        'downloadJpgUrl' => route('payment.receipts.download-jpg', $receipt),
                                        'downloadPdfUrl' => route('payment.receipts.download-pdf', $receipt),
                                        'isPdf' => $receipt->original_mime === 'application/pdf',
                                        'ref' => $receipt->reference_number ?: 'PAY-'.$receipt->id,
                                        'student' => $student->last_name.'_'.$student->grade_level
                                    ]))" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <span>View Receipt Proof</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Receipt Proof Viewer Modal --}}
        <div x-show="activeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4" @click.self="closeModal()" @keydown.escape.window="closeModal()">
            <div class="bg-white rounded-2xl w-full max-w-4xl overflow-hidden shadow-2xl border border-slate-200 flex flex-col max-h-[90vh]">
                {{-- Modal Header --}}
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">Receipt Proof Viewer</h3>
                        <p class="text-[11px] text-slate-500 font-mono" x-text="`Ref: ${activeItem?.ref || 'N/A'}`"></p>
                    </div>
                    <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700 text-lg font-bold p-1">&times;</button>
                </div>

                {{-- Toolbar Controls (Zoom Out | 100% | Zoom In | Reset | Download JPG | Download PDF | Close) --}}
                <div class="bg-slate-900 px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-white">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="zoomOut()" class="px-2.5 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 rounded-lg transition">Zoom Out</button>
                        <span class="text-xs font-bold text-slate-300 px-2 min-w-[48px] text-center" x-text="`${Math.round(zoomLevel * 100)}%`">100%</span>
                        <button type="button" @click="zoomIn()" class="px-2.5 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 rounded-lg transition">Zoom In</button>
                        <button type="button" @click="resetZoom()" class="px-2.5 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 rounded-lg transition">Reset</button>
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- DOWNLOAD JPG Button --}}
                        <a :href="activeItem?.downloadJpgUrl" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition shadow-sm" download>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Download JPG</span>
                        </a>

                        {{-- DOWNLOAD PDF Button --}}
                        <a :href="activeItem?.downloadPdfUrl" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition" download>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Download PDF</span>
                        </a>

                        {{-- Close Button --}}
                        <button type="button" @click="closeModal()" class="px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 rounded-lg transition">Close</button>
                    </div>
                </div>

                {{-- Modal Preview Area --}}
                <div class="p-6 bg-slate-100 overflow-auto flex-1 flex items-center justify-center min-h-[450px]">
                    <template x-if="activeItem?.isPdf">
                        <iframe :src="activeItem?.originalUrl" class="w-full h-[550px] rounded-xl border border-slate-300"></iframe>
                    </template>
                    <template x-if="!activeItem?.isPdf">
                        <div class="overflow-auto max-w-full max-h-[600px] flex items-center justify-center">
                            <img :src="activeItem?.originalUrl" alt="Receipt proof preview" class="transition-transform duration-150 ease-out max-w-full h-auto object-contain rounded-xl shadow-md" :style="`transform: scale(${zoomLevel});`">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function studentReceiptProofModal() {
            return {
                activeModal: false,
                activeItem: null,
                zoomLevel: 1.0,

                openProofModal(item) {
                    this.activeItem = item;
                    this.zoomLevel = 1.0;
                    this.activeModal = true;
                },

                closeModal() {
                    this.activeModal = false;
                    this.activeItem = null;
                    this.zoomLevel = 1.0;
                },

                zoomIn() {
                    if (this.zoomLevel < 3.0) this.zoomLevel += 0.25;
                },

                zoomOut() {
                    if (this.zoomLevel > 0.5) this.zoomLevel -= 0.25;
                },

                resetZoom() {
                    this.zoomLevel = 1.0;
                }
            };
        }
    </script>
</x-app-layout>
