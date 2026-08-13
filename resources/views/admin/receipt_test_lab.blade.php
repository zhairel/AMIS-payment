<x-app-layout>
    <x-slot name="header">
        <div class="amis-test-header">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Independent 3-Engine OCR Benchmark</h1>
                <p class="text-xs text-slate-500 mt-0.5">Compare docTR, Tesseract, and Paperless-ngx independently.</p>
            </div>
            <span class="amis-badge-isolated">Isolated Testing Environment</span>
        </div>
    </x-slot>

    <div class="py-6" x-data="receiptTestLab()" x-init="fetchEnvDiagnostics()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Copy Success Toast Notification --}}
            <div x-show="toastMessage" x-cloak x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" class="fixed bottom-5 right-5 z-50 bg-slate-900 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-2xl border border-slate-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="toastMessage"></span>
            </div>

            {{-- Toolbar & Upload Section --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="cursor-pointer inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Upload Test Images</span>
                            <input type="file" multiple accept="image/jpeg,image/png,image/jpg" class="hidden" @change="handleFileUpload($event)">
                        </label>

                        <button type="button" @click="runAllTests()" :disabled="testItems.length === 0 || isProcessingAll" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                            <span x-text="isProcessingAll ? 'Running Tests...' : 'Run All Tests (Normal)'"></span>
                        </button>

                        <button type="button" @click="runAllCompareTests()" :disabled="testItems.length === 0 || isComparingAll" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 disabled:opacity-50 text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span x-text="isComparingAll ? 'Running 3-Pipeline Benchmark...' : 'Run All OCR Comparisons'"></span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="bg-slate-100 p-1 rounded-xl flex items-center border border-slate-200">
                            <button type="button" @click="viewMode = 'summary'" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="viewMode === 'summary' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'">
                                Summary View
                            </button>
                            <button type="button" @click="viewMode = 'all_expanded'" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="viewMode === 'all_expanded' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'">
                                Display All Results
                            </button>
                        </div>

                        <button type="button" @click="clearResults()" :disabled="testItems.length === 0" class="px-3 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                            Clear Results
                        </button>
                        <button type="button" @click="resetLab()" class="px-3 py-2 text-xs font-semibold text-rose-600 hover:text-rose-700 border border-rose-200 rounded-xl hover:bg-rose-50 transition">
                            Reset Test Data
                        </button>
                    </div>
                </div>

                {{-- Global Batch Controls Bar --}}
                <div x-show="testItems.length > 0" class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Global Controls:</span>
                        <button type="button" @click="expandAllRawText()" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                            Expand All Raw Text
                        </button>
                        <button type="button" @click="expandAllJson()" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                            Expand All JSON
                        </button>
                        <button type="button" @click="collapseAll()" class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 border border-slate-200 rounded-lg transition">
                            Collapse All
                        </button>
                    </div>

                    <div>
                        <button type="button" @click="copyAllResultsJson()" class="px-3.5 py-1.5 text-xs font-bold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-lg transition inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span>Copy All Results (Batch JSON)</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Python Environment & Service Diagnostic Header Banner --}}
            <div class="bg-slate-900 text-slate-100 rounded-2xl p-5 text-xs font-mono border border-slate-800 space-y-3 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-3">
                    <div>
                        <strong class="text-sky-400">AMIS OCR Python Executable:</strong>
                        <span class="text-amber-300 ml-1.5" x-text="envDiagnostics?.python_executable || '/home/tatsuya/Projects/AMIS/amis_payment/.venv-ocr/bin/python'"></span>
                    </div>
                    <div>
                        <strong class="text-slate-400">Environment Version:</strong>
                        <span class="text-slate-200 ml-1" x-text="envDiagnostics?.python_version || 'Python 3.12.13'"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                    <template x-for="(status, name) in (envDiagnostics?.engines || {})" :key="name">
                        <div class="p-3 rounded-xl border text-[11px]" :class="status.available ? 'bg-emerald-950/60 border-emerald-800/80 text-emerald-200' : 'bg-rose-950/40 border-rose-900/60 text-rose-300'">
                            <div class="flex items-center justify-between font-bold mb-1">
                                <span x-text="engineDisplayName(name)"></span>
                                <span class="px-2 py-0.5 text-[9px] rounded font-extrabold uppercase" :class="status.available ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'" x-text="status.available ? 'AVAILABLE' : 'NOT AVAILABLE'"></span>
                            </div>
                            <small class="block text-[10px] text-slate-400 font-sans leading-tight truncate" x-text="status.available ? `Version: ${status.version || 'installed'}` : (status.reason || 'Not installed')"></small>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Batch Summary Dashboard & Engine Performance Cards --}}
            <div x-show="testItems.length > 0" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-black text-slate-900 uppercase tracking-wider">Batch Engine Performance Summary</h2>
                    <span class="text-xs text-slate-500 font-medium" x-text="`${countComparedReceipts()} of ${testItems.length} receipts compared across 3 pipelines`"></span>
                </div>

                {{-- 3 Engine Summary Metric Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <template x-for="engineKey in ['doctr', 'tesseract', 'paperless']" :key="engineKey">
                        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2.5">
                                    <div>
                                        <h3 class="font-extrabold text-sm text-slate-900" x-text="engineDisplayName(engineKey)"></h3>
                                        <small class="text-[9px] text-slate-400 block font-medium" x-text="engineSubtitle(engineKey)"></small>
                                    </div>
                                    <span class="px-2 py-0.5 text-[10px] font-black rounded uppercase" :class="envDiagnostics?.engines?.[engineKey]?.available ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'" x-text="envDiagnostics?.engines?.[engineKey]?.available ? 'ONLINE' : 'OFFLINE'"></span>
                                </div>

                                <div class="space-y-2 text-xs">
                                    <div class="flex justify-between items-center text-slate-600">
                                        <span>Receipts Tested:</span>
                                        <strong class="text-slate-900 font-bold" x-text="engineStats(engineKey).testedCount">0</strong>
                                    </div>
                                    <div class="flex justify-between items-center text-slate-600">
                                        <span x-text="engineStats(engineKey).hasGroundTruth ? 'Avg Accuracy Score:' : 'Avg Fields Detected:'"></span>
                                        <strong class="font-black text-emerald-600" x-text="engineStats(engineKey).hasGroundTruth ? `${engineStats(engineKey).avgCorrect} / 4 Correct` : `${engineStats(engineKey).avgFieldsDetected} / 4 Fields`">0 / 4</strong>
                                    </div>
                                    <div class="flex justify-between items-center text-slate-600">
                                        <span>Avg Confidence:</span>
                                        <strong class="text-amber-600 font-bold" x-text="engineStats(engineKey).avgConfidence !== null ? `${engineStats(engineKey).avgConfidence}%` : 'N/A'">N/A</strong>
                                    </div>
                                    <div class="flex justify-between items-center text-slate-600">
                                        <span>Avg Processing Time:</span>
                                        <strong class="text-sky-700 font-bold" x-text="`${engineStats(engineKey).avgDurationMs} ms`">0 ms</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Batch Comparison Benchmark Matrix Table --}}
                <div x-show="countComparedReceipts() > 0" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm overflow-x-auto">
                    <h3 class="font-extrabold text-sm text-slate-900 mb-3 uppercase tracking-wider">Batch Comparison Matrix Table</h3>
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200">
                                <th class="p-3">Receipt Filename</th>
                                <th class="p-3 text-center">docTR</th>
                                <th class="p-3 text-center">Tesseract</th>
                                <th class="p-3 text-center">Paperless-ngx</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(item, idx) in testItems" :key="item.id">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-3 font-semibold text-slate-900 truncate max-w-xs flex items-center gap-2">
                                        <img :src="item.previewUrl" class="w-8 h-8 rounded object-cover border border-slate-200 flex-shrink-0">
                                        <span x-text="item.filename"></span>
                                    </td>
                                    <td class="p-3 text-center font-bold">
                                        <span class="px-2.5 py-1 rounded-lg text-xs" :class="itemEngineScoreClass(item, 'doctr')" x-text="itemEngineScore(item, 'doctr')"></span>
                                    </td>
                                    <td class="p-3 text-center font-bold">
                                        <span class="px-2.5 py-1 rounded-lg text-xs" :class="itemEngineScoreClass(item, 'tesseract')" x-text="itemEngineScore(item, 'tesseract')"></span>
                                    </td>
                                    <td class="p-3 text-center font-bold">
                                        <span class="px-2.5 py-1 rounded-lg text-xs" :class="itemEngineScoreClass(item, 'paperless')" x-text="itemEngineScore(item, 'paperless')"></span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <button type="button" @click="runEngineComparison(item)" :disabled="item.isComparing" class="px-2.5 py-1 text-xs font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg border border-sky-200 transition">
                                            <span x-text="item.isComparing ? 'Comparing...' : 'Run Compare'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Empty Placeholder --}}
            <div x-show="testItems.length === 0" class="bg-white rounded-2xl p-12 text-center border border-slate-200">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-slate-900 font-bold text-base mb-1">No test receipts uploaded yet</h3>
                <p class="text-slate-500 text-xs max-w-sm mx-auto mb-5">Upload multiple receipt images to benchmark docTR, Tesseract, and Paperless-ngx side-by-side.</p>
            </div>

            {{-- Main Receipt Cards Display List --}}
            <div class="space-y-6">
                <template x-for="(item, index) in testItems" :key="item.id">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-5 transition">
                        
                        {{-- Top Per-Receipt Header & Final AMIS Summary Banner --}}
                        <div class="flex flex-col lg:flex-row gap-5 items-start justify-between border-b border-slate-100 pb-5">
                            <div class="flex items-start gap-4">
                                <div class="w-24 h-24 rounded-xl bg-slate-50 border border-slate-200 overflow-hidden flex-shrink-0 relative">
                                    <img :src="item.previewUrl" class="w-full h-full object-contain">
                                    <span class="absolute bottom-1 right-1 bg-slate-900/80 text-white text-[9px] font-bold px-1.5 py-0.5 rounded" x-text="item.size"></span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-extrabold text-slate-900 text-base" x-text="item.filename"></h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" :class="statusBadgeClass(item.status)" x-text="item.label || 'PENDING'"></span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1" x-text="item.message || 'Ready for test'"></p>

                                    {{-- Quick Field Summary Pill Row --}}
                                    <div class="flex flex-wrap gap-2 mt-3 text-xs">
                                        <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-slate-700 font-bold">Provider: <span class="text-amber-700" x-text="item.provider || 'Other / Unknown'"></span></span>
                                        <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-slate-700 font-bold">Ref: <span class="text-sky-700" x-text="item.reference_number || 'Not detected'"></span></span>
                                        <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-slate-700 font-bold">Date: <span class="text-purple-700" x-text="item.transaction_date || 'Not detected'"></span></span>
                                        <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-slate-700 font-bold">Amount: <span class="text-emerald-700" x-text="item.amount !== null ? `${item.currency} ${numberFormat(item.amount)}` : 'Not detected'"></span></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="runSingleTest(index)" :disabled="item.isProcessing" class="px-3.5 py-2 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-xl border border-emerald-200 transition">
                                    <span x-text="item.isProcessing ? 'Processing...' : 'Run Test (Single)'"></span>
                                </button>
                                <button type="button" @click="runEngineComparison(item)" :disabled="item.isComparing" class="px-3.5 py-2 text-xs font-bold text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-xl border border-sky-200 transition">
                                    <span x-text="item.isComparing ? 'Comparing 3 Pipelines...' : 'Run Compare OCR'"></span>
                                </button>
                                <button type="button" @click="openScannerModal(item)" :disabled="!item.result" class="px-3.5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 rounded-xl border border-slate-200 transition disabled:opacity-40">
                                    View Scanner
                                </button>
                        </div>

                        {{-- Preprocessing Quality Diagnostic Pill --}}
                        <template x-if="item.comparison?.preprocessing">
                            <div class="bg-slate-900 text-slate-200 border border-slate-800 p-3 rounded-xl text-xs font-mono flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="px-2.5 py-0.5 rounded font-black text-[10px] uppercase tracking-wider" :class="item.comparison?.preprocessing?.image_type === 'CAMERA_PHOTO' ? 'bg-amber-400 text-slate-950' : 'bg-sky-400 text-slate-950'" x-text="`Type: ${item.comparison?.preprocessing?.image_type || 'CAMERA_PHOTO'}`"></span>
                                    <span x-show="item.comparison?.preprocessing?.crop_applied" class="text-emerald-400 font-semibold">✓ Auto-crop: Applied</span>
                                    <span x-show="item.comparison?.preprocessing?.perspective_corrected" class="text-emerald-400 font-semibold">✓ Perspective: Corrected</span>
                                    <span class="text-slate-400" x-text="`Quality: Score ${item.comparison?.preprocessing?.quality_score}/100 (Blur: ${item.comparison?.preprocessing?.blur_status || 'ACCEPTABLE'}, Glare: ${item.comparison?.preprocessing?.glare_detected ? 'Detected' : 'Clear'})`"></span>
                                </div>
                                <div x-show="item.comparison?.preprocessing?.user_message" class="text-rose-300 text-[11px] font-sans font-semibold">
                                    <span x-text="item.comparison?.preprocessing?.user_message"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Per-Receipt Ground Truth Input Section --}}
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Expected Ground Truth (Optional Benchmark Evaluation)</h4>
                                <button type="button" @click="runEngineComparison(item)" class="text-sky-700 font-semibold hover:underline">Re-evaluate Ground Truth</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Expected Provider</label>
                                    <input type="text" x-model="item.expected.provider" placeholder="e.g. ANB / TeleMoney" class="w-full text-xs rounded-lg border-slate-200 p-1.5">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Expected Reference</label>
                                    <input type="text" x-model="item.expected.reference" placeholder="e.g. 400857439" class="w-full text-xs rounded-lg border-slate-200 p-1.5">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Expected Date</label>
                                    <input type="text" x-model="item.expected.date" placeholder="e.g. 2026-08-08" class="w-full text-xs rounded-lg border-slate-200 p-1.5">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Expected Amount</label>
                                    <input type="text" x-model="item.expected.amount" placeholder="e.g. SAR 260.32" class="w-full text-xs rounded-lg border-slate-200 p-1.5">
                                </div>
                            </div>
                        </div>

                        {{-- Loading Indicator for Comparison --}}
                        <div x-show="item.isComparing" class="p-8 text-center bg-slate-50 rounded-xl border border-slate-200">
                            <div class="inline-block animate-spin w-7 h-7 border-3 border-sky-600 border-t-transparent rounded-full mb-2"></div>
                            <h4 class="text-slate-800 font-bold text-xs">Running docTR, Tesseract, and Paperless-ngx independently...</h4>
                        </div>

                        {{-- 3 Engine Results Responsive Side-by-Side Grid --}}
                        <div x-show="item.comparison" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <template x-for="engineKey in ['doctr', 'tesseract', 'paperless']" :key="engineKey">
                                <div class="bg-white rounded-xl border p-4 shadow-sm flex flex-col justify-between" :class="item.comparison?.engines?.[engineKey]?.status === 'SUCCESS' ? 'border-emerald-200' : 'border-slate-200'">
                                    <div>
                                        {{-- Engine Card Header --}}
                                        <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                                            <div>
                                                <h4 class="font-black text-xs text-slate-900" x-text="item.comparison?.engines?.[engineKey]?.engine"></h4>
                                                <span class="text-[9px] text-slate-400 block" x-text="item.comparison?.engines?.[engineKey]?.attempted ? `${item.comparison?.engines?.[engineKey]?.duration_ms} ms` : 'Attempted: NO'"></span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="badgeClassForStatus(item.comparison?.engines?.[engineKey]?.status)" x-text="item.comparison?.engines?.[engineKey]?.status || 'NOT AVAILABLE'"></span>
                                        </div>

                                        {{-- Ground Truth / Fields Score --}}
                                        <div class="bg-slate-50 p-2 rounded-lg border border-slate-100 mb-3 flex items-center justify-between">
                                            <small class="text-[9px] font-bold text-slate-400 uppercase" x-text="item.comparison?.engines?.[engineKey]?.ground_truth?.has_expected ? 'Accuracy Score' : 'Fields Detected'"></small>
                                            <strong class="text-xs font-black text-sky-700" x-text="itemEngineScore(item, engineKey)"></strong>
                                        </div>

                                        {{-- Engine Metrics --}}
                                        <div class="grid grid-cols-2 gap-2 text-[10px] bg-slate-50 p-2 rounded-lg border border-slate-100 mb-3">
                                            <div>
                                                <span class="text-slate-400 font-bold block text-[8px] uppercase">Regions / Lines</span>
                                                <strong class="text-slate-800" x-text="item.comparison?.engines?.[engineKey]?.regions ?? '0'"></strong>
                                            </div>
                                            <div>
                                                <span class="text-slate-400 font-bold block text-[8px] uppercase">Confidence</span>
                                                <strong class="text-slate-800" x-text="item.comparison?.engines?.[engineKey]?.confidence !== null ? `${Math.round(item.comparison?.engines?.[engineKey]?.confidence * (item.comparison?.engines?.[engineKey]?.confidence <= 1 ? 100 : 1))}%` : 'N/A'"></strong>
                                            </div>
                                        </div>

                                        {{-- Paperless-ngx Specific Technical Details --}}
                                        <template x-if="engineKey === 'paperless' && item.comparison?.engines?.[engineKey]?.paperless_document_id">
                                            <div class="bg-sky-50 border border-sky-200 text-sky-900 p-2 rounded-lg text-[10px] mb-3 font-mono space-y-1">
                                                <div class="flex justify-between">
                                                    <span>Paperless Document ID:</span>
                                                    <strong x-text="`#${item.comparison?.engines?.[engineKey]?.paperless_document_id}`"></strong>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span>Cleanup Status:</span>
                                                    <strong class="text-emerald-700 font-bold" x-text="item.comparison?.engines?.[engineKey]?.cleanup_status"></strong>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Engine Error Reason Box --}}
                                        <div x-show="item.comparison?.engines?.[engineKey]?.error" class="bg-rose-50 border border-rose-200 text-rose-800 p-2 rounded-lg text-[9px] font-mono mb-3 whitespace-pre-wrap leading-tight" x-text="item.comparison?.engines?.[engineKey]?.error"></div>

                                        {{-- 4 Standardized AMIS Fields --}}
                                        <div class="space-y-1.5 bg-slate-50/70 p-2.5 rounded-lg border border-slate-100 text-[11px]">
                                            <div>
                                                <small class="text-[8px] font-bold text-slate-400 uppercase block">Provider</small>
                                                <strong class="font-bold text-slate-800 block truncate" x-text="item.comparison?.engines?.[engineKey]?.parsed?.provider || 'Other / Unknown'"></strong>
                                            </div>
                                            <div>
                                                <small class="text-[8px] font-bold text-slate-400 uppercase block">Reference</small>
                                                <strong class="font-bold text-slate-800 block truncate" x-text="item.comparison?.engines?.[engineKey]?.parsed?.reference_number || 'Not detected'"></strong>
                                            </div>
                                            <div>
                                                <small class="text-[8px] font-bold text-slate-400 uppercase block">Date & Time</small>
                                                <strong class="font-bold text-slate-800 block truncate" x-text="item.comparison?.engines?.[engineKey]?.parsed?.transaction_date ? `${item.comparison?.engines?.[engineKey]?.parsed?.transaction_date} ${item.comparison?.engines?.[engineKey]?.parsed?.transaction_time || ''}` : 'Not detected'"></strong>
                                            </div>
                                            <div>
                                                <small class="text-[8px] font-bold text-slate-400 uppercase block">Amount</small>
                                                <strong class="font-bold text-slate-800 block truncate" x-text="item.comparison?.engines?.[engineKey]?.parsed?.amount !== null ? `${item.comparison?.engines?.[engineKey]?.parsed?.currency || 'PHP'} ${numberFormat(item.comparison?.engines?.[engineKey]?.parsed?.amount)}` : 'Not detected'"></strong>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Raw OCR Text & Engine JSON Collapsible Containers --}}
                                    <div class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                                        {{-- Raw OCR Text Block --}}
                                        <div>
                                            <div class="flex items-center justify-between text-[10px]">
                                                <button type="button" @click="item.showRaw[engineKey] = !item.showRaw[engineKey]" class="font-bold text-slate-600 hover:text-slate-900">
                                                    <span>Raw OCR Text</span>
                                                    <span x-text="item.showRaw[engineKey] ? '(Hide)' : '(Show)'"></span>
                                                </button>
                                                <button type="button" @click="copyToClipboard(item.comparison?.engines?.[engineKey]?.raw_text, `${engineDisplayName(engineKey)} Raw Text`)" class="text-sky-600 font-bold hover:underline">Copy</button>
                                            </div>
                                            <div x-show="item.showRaw[engineKey]" class="mt-1 bg-slate-950 text-emerald-400 p-2 rounded text-[9px] font-mono whitespace-pre-wrap max-h-40 overflow-y-auto" x-text="item.comparison?.engines?.[engineKey]?.raw_text || 'No text'"></div>
                                        </div>

                                        {{-- Engine Parsed JSON Block --}}
                                        <div>
                                            <div class="flex items-center justify-between text-[10px]">
                                                <button type="button" @click="item.showJson[engineKey] = !item.showJson[engineKey]" class="font-bold text-slate-600 hover:text-slate-900">
                                                    <span>Parsed JSON</span>
                                                    <span x-text="item.showJson[engineKey] ? '(Hide)' : '(Show)'"></span>
                                                </button>
                                                <button type="button" @click="copyToClipboard(JSON.stringify(item.comparison?.engines?.[engineKey]?.parsed, null, 2), `${engineDisplayName(engineKey)} Parsed JSON`)" class="text-sky-600 font-bold hover:underline">Copy</button>
                                            </div>
                                            <pre x-show="item.showJson[engineKey]" class="mt-1 bg-slate-950 text-sky-300 p-2 rounded text-[9px] font-mono max-h-40 overflow-y-auto" x-text="JSON.stringify(item.comparison?.engines?.[engineKey]?.parsed, null, 2)"></pre>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Final Normalized AMIS JSON Box --}}
                        <div x-show="item.result?.technical_details?.parsed_fields" class="bg-slate-900 text-slate-200 rounded-xl p-4 text-xs font-mono border border-slate-800">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <strong class="text-emerald-400 font-bold">Final AMIS Normalized Result JSON</strong>
                                    <span class="text-[10px] text-slate-400" x-text="`Extraction Method: ${item.result?.technical_details?.extraction_method}`"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="item.showFinalJson = !item.showFinalJson" class="text-[11px] font-semibold text-slate-400 hover:text-white">
                                        <span x-text="item.showFinalJson ? 'Hide JSON' : 'View Final JSON'"></span>
                                    </button>
                                    <button type="button" @click="copyToClipboard(JSON.stringify(item.result?.technical_details?.parsed_fields, null, 2), 'Final AMIS Normalized JSON')" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-sky-300 rounded font-bold text-[10px] transition">
                                        Copy Final JSON
                                    </button>
                                </div>
                            </div>
                            <pre x-show="item.showFinalJson || viewMode === 'all_expanded'" class="text-slate-300 text-[10px] mt-2 max-h-60 overflow-y-auto bg-slate-950 p-3 rounded border border-slate-800" x-text="JSON.stringify(item.result?.technical_details?.parsed_fields, null, 2)"></pre>
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
                isComparingAll: false,
                activeScannerModal: false,
                modalItem: null,
                viewMode: 'summary',
                envDiagnostics: null,
                toastMessage: null,

                async fetchEnvDiagnostics() {
                    try {
                        const response = await fetch("{{ route('admin.receipt_test.env') }}");
                        if (response.ok) {
                            this.envDiagnostics = await response.json();
                        }
                    } catch (e) {
                        console.error('Error fetching environment diagnostics:', e);
                    }
                },

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
                            message: 'Ready for test.',
                            provider: null,
                            reference_number: null,
                            transaction_date: null,
                            transaction_time: null,
                            amount: null,
                            currency: 'PHP',
                            isProcessing: false,
                            isComparing: false,
                            result: null,
                            comparison: null,
                            expected: {
                                provider: '',
                                reference: '',
                                date: '',
                                amount: ''
                            },
                            showRaw: { doctr: false, tesseract: false, paperless: false },
                            showJson: { doctr: false, tesseract: false, paperless: false },
                            showFinalJson: false,
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

                async runEngineComparison(item) {
                    if (!item) return;

                    item.isComparing = true;

                    const formData = new FormData();
                    if (item.result?.test_id) {
                        formData.append('test_id', item.result.test_id);
                    }
                    if (item.file) {
                        formData.append('image', item.file);
                    }
                    formData.append('expected_provider', item.expected?.provider || '');
                    formData.append('expected_reference', item.expected?.reference || '');
                    formData.append('expected_date', item.expected?.date || '');
                    formData.append('expected_amount', item.expected?.amount || '');

                    try {
                        const response = await fetch("{{ route('admin.receipt_test.compare') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: formData
                        });

                        if (response.ok) {
                            const data = await response.json();
                            item.comparison = data.comparison;
                            if (data.comparison?.environment) {
                                this.envDiagnostics = data.comparison.environment;
                            }
                        } else {
                            console.error('Compare request failed');
                        }
                    } catch (e) {
                        console.error('Error running OCR comparison:', e);
                    } finally {
                        item.isComparing = false;
                    }
                },

                async runAllTests() {
                    this.isProcessingAll = true;
                    for (let i = 0; i < this.testItems.length; i++) {
                        await this.runSingleTest(i);
                    }
                    this.isProcessingAll = false;
                },

                async runAllCompareTests() {
                    this.isComparingAll = true;
                    for (let i = 0; i < this.testItems.length; i++) {
                        await this.runEngineComparison(this.testItems[i]);
                    }
                    this.isComparingAll = false;
                },

                countComparedReceipts() {
                    return this.testItems.filter(item => item.comparison !== null).length;
                },

                engineStats(engineKey) {
                    const comparedItems = this.testItems.filter(item => item.comparison?.engines?.[engineKey]?.attempted === true);
                    const testedCount = comparedItems.length;

                    if (testedCount === 0) {
                        return { testedCount: 0, avgFieldsDetected: '0.0', avgConfidence: null, avgDurationMs: 0, hasGroundTruth: false, avgCorrect: '0.0' };
                    }

                    let totalFields = 0;
                    let totalConfidence = 0;
                    let confCount = 0;
                    let totalDuration = 0;
                    let totalCorrect = 0;
                    let groundTruthItems = 0;

                    comparedItems.forEach(item => {
                        const eng = item.comparison.engines[engineKey];
                        const parsed = eng.parsed || {};
                        let fields = 0;
                        if (parsed.provider && parsed.provider !== 'Other / Unknown') fields++;
                        if (parsed.reference_number) fields++;
                        if (parsed.transaction_date) fields++;
                        if (parsed.amount !== null && parsed.amount !== undefined) fields++;

                        totalFields += fields;
                        totalDuration += (eng.duration_ms || 0);

                        if (eng.confidence !== null && eng.confidence !== undefined) {
                            let conf = eng.confidence;
                            if (conf <= 1) conf = conf * 100;
                            totalConfidence += conf;
                            confCount++;
                        }

                        if (eng.ground_truth?.has_expected) {
                            groundTruthItems++;
                            totalCorrect += eng.ground_truth.correct_count;
                        }
                    });

                    return {
                        testedCount: testedCount,
                        avgFieldsDetected: (totalFields / testedCount).toFixed(1),
                        avgConfidence: confCount > 0 ? Math.round(totalConfidence / confCount) : null,
                        avgDurationMs: Math.round(totalDuration / testedCount),
                        hasGroundTruth: groundTruthItems > 0,
                        avgCorrect: groundTruthItems > 0 ? (totalCorrect / groundTruthItems).toFixed(1) : '0.0'
                    };
                },

                itemEngineScore(item, engineKey) {
                    const eng = item.comparison?.engines?.[engineKey];
                    if (!eng) return 'Not run';

                    if (eng.status === 'NOT_AVAILABLE') return 'Not Available';
                    if (eng.status === 'FAILED') return 'Failed';

                    if (eng.ground_truth?.has_expected) {
                        return `${eng.ground_truth.correct_count}/4 Correct`;
                    }

                    const parsed = eng.parsed || {};
                    let fields = 0;
                    if (parsed.provider && parsed.provider !== 'Other / Unknown') fields++;
                    if (parsed.reference_number) fields++;
                    if (parsed.transaction_date) fields++;
                    if (parsed.amount !== null && parsed.amount !== undefined) fields++;

                    return `${fields}/4 Fields`;
                },

                itemEngineScoreClass(item, engineKey) {
                    const eng = item.comparison?.engines?.[engineKey];
                    if (!eng) return 'bg-slate-100 text-slate-500';
                    if (eng.status === 'NOT_AVAILABLE') return 'bg-amber-100 text-amber-800';
                    if (eng.status === 'FAILED') return 'bg-rose-100 text-rose-800';

                    if (eng.ground_truth?.has_expected) {
                        return eng.ground_truth.correct_count === 4 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
                    }

                    return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
                },

                expandAllRawText() {
                    this.testItems.forEach(item => {
                        item.showRaw = { doctr: true, tesseract: true, paperless: true };
                    });
                    this.toast('Expanded all raw OCR text containers.');
                },

                expandAllJson() {
                    this.testItems.forEach(item => {
                        item.showJson = { doctr: true, tesseract: true, paperless: true };
                        item.showFinalJson = true;
                    });
                    this.toast('Expanded all JSON containers.');
                },

                collapseAll() {
                    this.testItems.forEach(item => {
                        item.showRaw = { doctr: false, tesseract: false, paperless: false };
                        item.showJson = { doctr: false, tesseract: false, paperless: false };
                        item.showFinalJson = false;
                        item.showTech = false;
                    });
                    this.toast('Collapsed all raw text and JSON containers.');
                },

                copyAllResultsJson() {
                    const exportData = {
                        timestamp: new Date().toISOString(),
                        environment: this.envDiagnostics,
                        total_receipts: this.testItems.length,
                        receipts: this.testItems.map(item => ({
                            filename: item.filename,
                            status: item.status,
                            label: item.label,
                            final_amis_result: {
                                provider: item.provider,
                                reference_number: item.reference_number,
                                transaction_date: item.transaction_date,
                                transaction_time: item.transaction_time,
                                amount: item.amount,
                                currency: item.currency,
                                extraction_method: item.result?.technical_details?.extraction_method,
                                parsed_fields: item.result?.technical_details?.parsed_fields,
                            },
                            ocr_comparison: item.comparison,
                        }))
                    };

                    this.copyToClipboard(JSON.stringify(exportData, null, 2), 'All Batch Results JSON');
                },

                copyToClipboard(text, label) {
                    if (!text) return;
                    navigator.clipboard.writeText(text).then(() => {
                        this.toast(`Copied ${label} to clipboard!`);
                    }).catch(err => {
                        console.error('Copy failed:', err);
                    });
                },

                toast(msg) {
                    this.toastMessage = msg;
                    setTimeout(() => {
                        if (this.toastMessage === msg) this.toastMessage = null;
                    }, 2500);
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

                badgeClassForStatus(status) {
                    switch (status) {
                        case 'SUCCESS': return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
                        case 'FAILED': return 'bg-rose-100 text-rose-800 border border-rose-200';
                        case 'NOT_AVAILABLE': return 'bg-amber-100 text-amber-800 border border-amber-200';
                        default: return 'bg-slate-100 text-slate-600 border border-slate-200';
                    }
                },

                engineDisplayName(name) {
                    switch (name) {
                        case 'doctr': return 'docTR';
                        case 'tesseract': return 'Tesseract';
                        case 'paperless': return 'Paperless-ngx';
                        default: return name;
                    }
                },

                engineSubtitle(name) {
                    switch (name) {
                        case 'doctr': return 'Deep Learning OCR';
                        case 'tesseract': return 'Native Tesseract 5.5';
                        case 'paperless': return 'Full document-processing pipeline';
                        default: return '';
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
