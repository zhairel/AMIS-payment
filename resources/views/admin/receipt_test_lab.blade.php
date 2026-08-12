<x-app-layout>
    <x-slot name="header">
        <div class="amis-test-header">
            <div>
                <h1 class="text-xl font-bold text-slate-900">AMIS AI Receipt Scanner Test Lab</h1>
                <p class="text-xs text-slate-500 mt-0.5">Test receipt detection, OCR extraction, duplicate checking, and verification without creating real payment records.</p>
            </div>
            <span class="amis-badge-isolated">Isolated Testing Environment</span>
        </div>
    </x-slot>

    <div class="py-6" x-data="receiptTestLab()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Toolbar & Upload Section --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <label class="cursor-pointer inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Upload Test Images</span>
                            <input type="file" multiple accept="image/jpeg,image/png,image/jpg" class="hidden" @change="handleFileUpload($event)">
                        </label>

                        <button type="button" @click="runAllTests()" :disabled="testItems.length === 0 || isProcessingAll" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                            <span x-text="isProcessingAll ? 'Running Tests...' : 'Run All Tests'"></span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="clearResults()" :disabled="testItems.length === 0" class="px-3 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                            Clear Results
                        </button>
                        <button type="button" @click="resetLab()" class="px-3 py-2 text-xs font-semibold text-rose-600 hover:text-rose-700 border border-rose-200 rounded-xl hover:bg-rose-50 transition">
                            Reset Test Data
                        </button>
                    </div>
                </div>

                {{-- Batch Summary Header --}}
                <div x-show="testItems.length > 0" class="mt-6 pt-5 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-6 gap-3">
                    <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
                        <small class="text-slate-500 font-semibold block text-[10px] uppercase tracking-wider">Total Tested</small>
                        <strong class="text-slate-900 text-lg font-black" x-text="testItems.length">0</strong>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-3 text-center border border-emerald-100">
                        <small class="text-emerald-700 font-semibold block text-[10px] uppercase tracking-wider">Passed</small>
                        <strong class="text-emerald-700 text-lg font-black" x-text="countByStatus('passed')">0</strong>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center border border-amber-100">
                        <small class="text-amber-700 font-semibold block text-[10px] uppercase tracking-wider">Needs Review</small>
                        <strong class="text-amber-700 text-lg font-black" x-text="countByStatus('needs_review')">0</strong>
                    </div>
                    <div class="bg-rose-50 rounded-xl p-3 text-center border border-rose-100">
                        <small class="text-rose-700 font-semibold block text-[10px] uppercase tracking-wider">Not a Receipt</small>
                        <strong class="text-rose-700 text-lg font-black" x-text="countByStatus('not_a_receipt')">0</strong>
                    </div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center border border-purple-100">
                        <small class="text-purple-700 font-semibold block text-[10px] uppercase tracking-wider">Duplicate</small>
                        <strong class="text-purple-700 text-lg font-black" x-text="countByStatus('duplicate')">0</strong>
                    </div>
                    <div class="bg-slate-100 rounded-xl p-3 text-center border border-slate-200">
                        <small class="text-slate-600 font-semibold block text-[10px] uppercase tracking-wider">Pending</small>
                        <strong class="text-slate-700 text-lg font-black" x-text="countByStatus('pending')">0</strong>
                    </div>
                </div>
            </div>

            {{-- Empty Placeholder --}}
            <div x-show="testItems.length === 0" class="bg-white rounded-2xl p-12 text-center border border-slate-200">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-slate-900 font-bold text-base mb-1">No test receipts uploaded yet</h3>
                <p class="text-slate-500 text-xs max-w-sm mx-auto mb-5">Upload sample receipts (GCash, Maya, Bank Transfers, Remittances, MTCN, selfies, posters) to test AMIS AI OCR classification.</p>
            </div>

            {{-- Result Cards Grid --}}
            <div class="space-y-4">
                <template x-for="(item, index) in testItems" :key="item.id">
                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 transition">
                        <div class="flex flex-col md:flex-row gap-5 items-start">
                            {{-- Image Thumbnail --}}
                            <div class="w-full md:w-36 h-36 rounded-xl bg-slate-50 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center relative">
                                <img :src="item.previewUrl" class="w-full h-full object-contain">
                                <span class="absolute bottom-1 right-1 bg-slate-900/80 text-white text-[9px] font-bold px-1.5 py-0.5 rounded" x-text="item.size"></span>
                            </div>

                            {{-- Card Details --}}
                            <div class="flex-1 w-full">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <div>
                                        <h4 class="font-bold text-slate-900 text-sm truncate max-w-xs" x-text="item.filename"></h4>
                                        <span class="text-[11px] text-slate-400 font-medium" x-text="item.processingTimeMs ? `${item.processingTimeMs} ms processing` : 'Ready for test'"></span>
                                    </div>
                                    <div>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5" :class="statusBadgeClass(item.status)">
                                            <span x-text="item.label || 'PENDING'"></span>
                                        </span>
                                    </div>
                                </div>

                                {{-- Standardized 4 Fields Grid --}}
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/70 p-3 rounded-xl border border-slate-100 mb-4">
                                    <div>
                                        <small class="text-[10px] font-semibold text-slate-400 uppercase block">Provider / Mode</small>
                                        <strong class="text-xs font-bold text-slate-800 truncate block" x-text="item.provider || 'Other / Unknown'"></strong>
                                    </div>
                                    <div>
                                        <small class="text-[10px] font-semibold text-slate-400 uppercase block">Reference No.</small>
                                        <strong class="text-xs font-bold text-slate-800 truncate block" x-text="item.reference_number || 'Not detected'"></strong>
                                    </div>
                                    <div>
                                        <small class="text-[10px] font-semibold text-slate-400 uppercase block">Date & Time</small>
                                        <strong class="text-xs font-bold text-slate-800 truncate block" x-text="item.transaction_date ? `${item.transaction_date} ${item.transaction_time || ''}` : 'Not detected'"></strong>
                                    </div>
                                    <div>
                                        <small class="text-[10px] font-semibold text-slate-400 uppercase block">Amount</small>
                                        <strong class="text-xs font-bold text-slate-800 truncate block" x-text="item.amount !== null ? `${item.currency} ${numberFormat(item.amount)}` : 'Not detected'"></strong>
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-xs text-slate-500 italic truncate max-w-md" x-text="item.message || 'Click Run Test to evaluate this image.'"></p>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="runSingleTest(index)" :disabled="item.isProcessing" class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition">
                                            <span x-text="item.isProcessing ? 'Processing...' : 'Run Test'"></span>
                                        </button>
                                        <button type="button" @click="openScannerModal(item)" :disabled="!item.result" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-lg border border-slate-200 transition disabled:opacity-40">
                                            View Scanner
                                        </button>
                                        <button type="button" @click="item.showTech = !item.showTech" :disabled="!item.result" class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition disabled:opacity-40">
                                            <span x-text="item.showTech ? 'Hide Details' : 'View Technical Details'"></span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Expandable Technical Details --}}
                                <div x-show="item.showTech" x-cloak class="mt-4 pt-4 border-t border-slate-100 text-xs font-mono bg-slate-900 text-slate-200 p-4 rounded-xl overflow-x-auto space-y-2">
                                    <div><strong>OCR Engine:</strong> <span class="text-sky-400" x-text="item.result?.technical_details?.ocr_engine || 'PaddleOCR PP-OCRv6'"></span></div>
                                    <div><strong>Image Dimensions:</strong> <span class="text-indigo-300" x-text="item.result?.technical_details?.image_dimensions || 'Unknown'"></span></div>
                                    <div><strong>Text Regions Detected:</strong> <span class="text-purple-300" x-text="item.result?.technical_details?.text_regions_detected ?? '0'"></span></div>
                                    <div><strong>OCR Raw Text:</strong> <span class="text-emerald-400 block whitespace-pre-wrap mt-1 bg-slate-950 p-2.5 rounded border border-slate-800 text-[11px]" x-text="item.result?.technical_details?.raw_text || 'No raw text detected'"></span></div>
                                    <div><strong>Parser Result:</strong>
                                        <div class="text-slate-300 bg-slate-950 p-2.5 rounded border border-slate-800 mt-1 space-y-0.5 text-[11px]">
                                            <div>Provider: <span class="text-amber-300" x-text="item.result?.technical_details?.parser_result?.provider || 'Other / Unknown'"></span></div>
                                            <div>Mode: <span class="text-amber-200" x-text="item.result?.technical_details?.parser_result?.mode || 'N/A'"></span></div>
                                            <div>Reference: <span class="text-sky-300" x-text="item.result?.technical_details?.parser_result?.reference || 'Not detected'"></span></div>
                                            <div>Date: <span class="text-purple-300" x-text="item.result?.technical_details?.parser_result?.date || 'Not detected'"></span></div>
                                            <div>Time: <span class="text-purple-200" x-text="item.result?.technical_details?.parser_result?.time || 'null'"></span></div>
                                            <div>Amount: <span class="text-emerald-300" x-text="item.result?.technical_details?.parser_result?.amount !== null ? item.result?.technical_details?.parser_result?.amount : 'Not detected'"></span></div>
                                            <div>Currency: <span class="text-emerald-200" x-text="item.result?.technical_details?.parser_result?.currency || 'PHP'"></span></div>
                                        </div>
                                    </div>
                                    <div><strong>Extraction Method:</strong> <span class="text-sky-400 font-bold" x-text="item.result?.technical_details?.extraction_method || 'Alias Parser / Provider Parser'"></span></div>
                                    <div><strong>Normalization Warnings:</strong> <span class="text-rose-400" x-text="JSON.stringify(item.result?.technical_details?.normalization_warnings || [])"></span></div>
                                    <div><strong>Confidence:</strong> <span class="text-amber-400" x-text="item.result?.confidence !== null ? `${item.result?.confidence}%` : 'N/A'"></span></div>
                                    <div><strong>Duplicate Check:</strong> <span class="text-sky-400" x-text="JSON.stringify(item.result?.technical_details?.duplicate_lookup)"></span></div>
                                    <div><strong>Parsed Json:</strong> <pre class="text-slate-300 text-[10px] mt-1" x-text="JSON.stringify(item.result?.technical_details?.parsed_fields, null, 2)"></pre></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- AMIS AI Receipt Scanner Preview Modal --}}
        <div x-show="activeScannerModal" x-cloak class="payment-receipt-modal" @click.self="activeScannerModal = false" role="dialog" aria-modal="true">
            <div class="payment-receipt-modal-card">
                <div class="payment-receipt-modal-loading" aria-live="polite">
                    <span class="payment-loading-kicker">AMIS AI RECEIPT SCANNER</span>
                    <h2 class="payment-loading-title">AMIS AI is scanning your receipt...</h2>
                    <p class="payment-loading-subtitle">Please wait while AMIS AI securely verifies your receipt.</p>

                    <div class="payment-scanner-preview-box">
                        <div class="payment-scanner-frame">
                            <img :src="modalItem?.previewUrl" alt="Test receipt preview" class="payment-scanner-preview-img">
                            <div class="payment-scanner-magnifier">
                                <div class="magnifier-lens">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                </div>
                            </div>
                            <div class="payment-scan-laser-line"></div>
                        </div>
                    </div>

                    <div class="payment-modal-progress-bar-wrapper">
                        <div class="payment-modal-progress-bar"><i style="width: 100%"></i></div>
                        <div class="payment-progress-label-row">
                            <small x-text="modalItem?.message || 'Verification complete'"></small>
                            <strong>100%</strong>
                        </div>
                    </div>

                    <div class="payment-modal-steps-tracker">
                        <div class="payment-step-item is-done"><div class="payment-step-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div><span>Reading</span></div>
                        <div class="payment-step-line is-done"></div>
                        <div class="payment-step-item is-done"><div class="payment-step-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div><span>Extracting</span></div>
                        <div class="payment-step-line is-done"></div>
                        <div class="payment-step-item is-done"><div class="payment-step-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div><span>Reference</span></div>
                        <div class="payment-step-line is-done"></div>
                        <div class="payment-step-item is-done"><div class="payment-step-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div><span>Duplicate</span></div>
                        <div class="payment-step-line is-done"></div>
                        <div class="payment-step-item is-done"><div class="payment-step-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div><span>Verifying</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function receiptTestLab() {
            return {
                testItems: [],
                isProcessingAll: false,
                activeScannerModal: false,
                modalItem: null,

                handleFileUpload(event) {
                    const files = Array.from(event.target.files);
                    files.forEach(file => {
                        this.testItems.push({
                            id: Math.random().toString(36).substring(2, 9),
                            file: file,
                            filename: file.name,
                            size: this.formatSize(file.size),
                            previewUrl: URL.createObjectURL(file),
                            status: 'pending',
                            label: 'PENDING',
                            message: 'Ready for processing.',
                            provider: null,
                            reference_number: null,
                            transaction_date: null,
                            transaction_time: null,
                            amount: null,
                            currency: 'PHP',
                            isProcessing: false,
                            result: null,
                            showTech: false,
                        });
                    });
                },

                async runSingleTest(index) {
                    const item = this.testItems[index];
                    if (!item || item.isProcessing) return;

                    item.isProcessing = true;
                    item.status = 'pending';
                    item.label = 'PROCESSING';

                    const formData = new FormData();
                    formData.append('image', item.file);

                    try {
                        const response = await fetch("{{ route('admin.receipt_test.process') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: formData
                        });

                        const data = await response.json();
                        item.status = data.status || 'failed';
                        item.label = data.label || 'FAILED';
                        item.message = data.message || (data.stage ? `Failed at stage [${data.stage}]` : 'Error processing test image.');
                        item.provider = data.provider;
                        item.reference_number = data.reference_number;
                        item.transaction_date = data.transaction_date;
                        item.transaction_time = data.transaction_time;
                        item.amount = data.amount;
                        item.currency = data.currency || 'PHP';
                        item.processingTimeMs = data.processing_time_ms;
                        item.result = data;
                    } catch (e) {
                        item.status = 'failed';
                        item.label = 'FAILED';
                        item.message = 'Processing error: ' + (e.message || 'Request failed.');
                    } finally {
                        item.isProcessing = false;
                    }
                },

                async runAllTests() {
                    this.isProcessingAll = true;
                    for (let i = 0; i < this.testItems.length; i++) {
                        await this.runSingleTest(i);
                    }
                    this.isProcessingAll = false;
                },

                countByStatus(status) {
                    return this.testItems.filter(item => item.status === status).length;
                },

                statusBadgeClass(status) {
                    switch (status) {
                        case 'passed': return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
                        case 'needs_review': return 'bg-amber-100 text-amber-800 border border-amber-200';
                        case 'not_a_receipt': return 'bg-rose-100 text-rose-800 border border-rose-200';
                        case 'duplicate': return 'bg-purple-100 text-purple-800 border border-purple-200';
                        case 'failed': return 'bg-slate-200 text-slate-800 border border-slate-300';
                        default: return 'bg-slate-100 text-slate-600 border border-slate-200';
                    }
                },

                openScannerModal(item) {
                    this.modalItem = item;
                    this.activeScannerModal = true;
                },

                clearResults() {
                    this.testItems = [];
                },

                resetLab() {
                    this.testItems = [];
                },

                formatSize(bytes) {
                    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
                    return (bytes / 1024).toFixed(0) + ' KB';
                },

                numberFormat(val) {
                    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
                }
            };
        }
    </script>
</x-app-layout>
