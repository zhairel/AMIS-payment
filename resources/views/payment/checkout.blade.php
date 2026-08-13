<x-app-layout>
    <x-slot name="title">Secure Payment Wizard</x-slot>

    <div class="payment-wizard-page" x-data="paymentWizard()">
        <div class="payment-wizard-shell">
            <header class="payment-wizard-header">
                <a href="{{ route('payment.dashboard') }}" class="payment-checkout-back">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Back to payments
                </a>
                <img src="{{ asset('images/MA_Logo.png') }}" alt="Al Munawwara Islamic School">
                <span class="payment-checkout-secure"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0119.5 12.75v6A2.25 2.25 0 0117.25 21H6.75a2.25 2.25 0 01-2.25-2.25v-6a2.25 2.25 0 012.25-2.25z"/></svg> Secure checkout</span>
            </header>

            <nav x-show="!submissionComplete" class="payment-wizard-progress" aria-label="Payment steps">
                @foreach([
                    1 => ['Channel', 'AMIS destination'],
                    2 => ['Receipt', 'Security checks'],
                    3 => ['Submit', 'Finance review'],
                ] as $stepNumber => [$stepLabel, $stepHelp])
                    <button type="button" :class="{ 'is-active': step === {{ $stepNumber }}, 'is-complete': step > {{ $stepNumber }} }" @click="canVisitStep({{ $stepNumber }}) && goToStep({{ $stepNumber }})">
                        <i><span>{{ $stepNumber }}</span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></i>
                        <span><strong>{{ $stepLabel }}</strong><small>{{ $stepHelp }}</small></span>
                    </button>
                @endforeach
            </nav>

            <main x-show="!submissionComplete" x-cloak class="payment-wizard-card" x-ref="wizardCard">
                {{-- Step 1: account selection --}}
                <section x-show="step === 1" class="payment-wizard-step">
                    <div class="payment-wizard-title">
                        <span class="payment-section-kicker">Step 1 of 3 · Family payment</span>
                        <h1>Choose the AMIS receiving channel</h1>
                        <p>Your payment belongs to the family account. AMIS—not the parent—will apply it to the oldest outstanding billing month after Finance approval.</p>
                    </div>

                    <section class="payment-wizard-total">
                        <span class="is-total-due"><small>Total amount due now</small><strong>₱{{ number_format($familyOutstandingBalance, 2) }}</strong><em>Includes past-due balances and the current month only.</em></span>
                        <span class="is-oldest-month"><small>Paid first automatically</small><strong>{{ $oldestOutstandingMonth ?: 'No outstanding balance' }}</strong></span>
                    </section>
                    @if($familyAdvanceCredit > 0)
                        <div class="payment-manual-edit-notice"><strong>Advance credit: ₱{{ number_format($familyAdvanceCredit, 2) }}</strong><span>This credit is already recorded on your family account.</span></div>
                    @endif

                    <aside class="payment-overpayment-banner" role="note" aria-label="Excess payment information">
                        <span class="payment-overpayment-icon" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 6v12m3-9.75H10.5a2.25 2.25 0 000 4.5h3a2.25 2.25 0 010 4.5H9M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <span class="payment-overpayment-copy">
                            <strong>Paying more than the amount due?</strong>
                            <small>Any verified amount above what is due will automatically pay the next unpaid billing month. If all scheduled fees are already covered, the remaining amount becomes advance credit. No separate request is needed.</small>
                        </span>
                    </aside>

                    <div class="payment-wizard-channel-list" role="radiogroup" aria-label="Official payment channels">
                        @if(empty($officialPaymentChannels))
                            <div class="payment-checkout-warning is-error" style="border-color: #fca5a5; background: #fef2f2; color: #991b1b;">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3h.008v.008H12v-.008zM10.3 3.6L1.8 18.3A1.5 1.5 0 003.1 20.5h17.8a1.5 1.5 0 001.3-2.2L13.7 3.6a1.5 1.5 0 00-3.4 0z"/></svg>
                                <span><strong>No payment channel is currently available. Please contact AMIS Support Staff.</strong></span>
                            </div>
                        @else
                            @foreach($officialPaymentChannels as $channelKey => $channel)
                                <button type="button" role="radio" :aria-checked="(paymentMethod === '{{ $channelKey }}').toString()" :class="paymentMethod === '{{ $channelKey }}' ? 'is-selected' : ''" @click="selectChannel('{{ $channelKey }}')">
                                    <img src="{{ asset($channel['logo']) }}" alt="{{ $channel['label'] }} logo">
                                    <span><strong>{{ $channel['label'] }}</strong><small>{{ $channelKey === 'bdo' ? 'Online transfer or over-the-counter deposit' : 'Official mobile wallet' }}</small></span>
                                    <i><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></i>
                                </button>
                            @endforeach
                        @endif
                    </div>

                    <div x-show="paymentMethod" class="payment-wizard-account-picker">
                        <div class="payment-wizard-subheading"><span><strong>Select the receiving account</strong><small>We will show this again before submission.</small></span></div>
                        @foreach($officialPaymentChannels as $channelKey => $channel)
                            <div x-show="paymentMethod === '{{ $channelKey }}'" class="payment-wizard-account-options">
                                @foreach($channel['accounts'] as $account)
                                    <button type="button" :class="selectedAccountIndex === {{ $loop->index }} ? 'is-selected' : ''" @click="selectedAccountIndex = {{ $loop->index }}">
                                        <i></i>
                                        <span><small>{{ $account['label'] }}</small><strong>{{ $account['number'] }}</strong><em>{{ $account['name'] ?? $channel['account_name'] ?? '' }}</em></span>
                                        <span class="payment-wizard-copy" @click.stop="copyDetail('{{ preg_replace('/\s+/', '', $account['number']) }}', '{{ $channelKey }}-{{ $loop->index }}')"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M8.25 7.5V6A2.25 2.25 0 0110.5 3.75h7.5A2.25 2.25 0 0120.25 6v7.5A2.25 2.25 0 0118 15.75h-1.5m-6.75 4.5H6A2.25 2.25 0 013.75 18V10.5A2.25 2.25 0 016 8.25h7.5a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H9.75z"/></svg><b x-text="copiedKey === '{{ $channelKey }}-{{ $loop->index }}' ? 'Copied' : 'Copy'"></b></span>
                                    </button>
                                @endforeach
                                @if(isset($channel['swift_code']))
                                    <p><strong>SWIFT:</strong> {{ $channel['swift_code'] }} · <strong>Branch:</strong> {{ $channel['branch'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="payment-checkout-warning"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3h.008v.008H12v-.008zM10.3 3.6L1.8 18.3A1.5 1.5 0 003.1 20.5h17.8a1.5 1.5 0 001.3-2.2L13.7 3.6a1.5 1.5 0 00-3.4 0z"/></svg><span><strong>Important</strong>MoneyGram and cash pick-up are not accepted or deducted from school fees.</span></div>
                    <div class="payment-wizard-actions is-end"><button type="button" class="payment-primary-action" :disabled="!paymentMethod || {{ empty($officialPaymentChannels) ? 'true' : 'false' }}" @click="goToStep(2)">Continue to receipt <span>→</span></button></div>
                </section>

                {{-- Step 2: secure receipt checks --}}
                <section x-show="step === 2" class="payment-wizard-step">
                                <div class="payment-wizard-title"><span class="payment-section-kicker">Step 2 of 3 · Receipt verification</span><h1>Upload your payment receipt</h1><p>Upload a clear JPG, JPEG, or PNG screenshot. Finance will verify the receipt before AMIS allocates anything.</p></div>
                    @if($retryPayment)
                        <div class="payment-retry-notice" role="status"><strong>{{ $retryPayment['action'] === 'edit' ? 'Previous details restored' : 'Re-upload requested' }}</strong><span>{{ $retryPayment['reason'] ?: 'Finance asked you to review the payment details and upload a new clear receipt.' }} Your previous transaction details are pre-filled below and will be checked again after upload.</span></div>
                    @endif
                    <div class="payment-manual-edit-notice"><strong>Automatic allocation</strong><span>After approval, AMIS applies the verified amount to the oldest family balance first, carries it forward, and records any excess as advance credit.</span></div>
                    <div class="payment-wizard-security-grid is-upload-only">
                        <div class="payment-wizard-form-fields">
                            <div>
                                <span class="payment-field-label">Payment receipt screenshot</span>
                                <label x-show="!receiptPreview" class="payment-wizard-dropzone"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 8.25L12 3.75m0 0L7.5 8.25M12 3.75V15"/></svg><span><strong>Upload payment proof</strong><small>JPG, JPEG, or PNG · Up to 10 MB · PDF is not accepted</small></span><input type="file" class="sr-only" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @change="chooseReceipt($event)" x-ref="receiptInput"></label>
                                <div x-show="receiptPreview" class="payment-wizard-receipt">
                                    <div class="payment-wizard-receipt-preview"><img :src="receiptPreview" alt="Uploaded receipt preview"></div>
                                    <div class="payment-wizard-receipt-details">
                                        <span><strong x-text="receiptFile?.name"></strong><small x-text="receiptScanning ? 'Checking receipt…' : receiptScanMessage"></small></span>
                                        <button type="button" @click="removeReceipt()" aria-label="Upload a different receipt"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992V4.356m-.39 14.269a9 9 0 10-2.122-9.502l2.512.225"/></svg>Upload another image</button>
                                    </div>
                                </div>
                            </div>

                                <div x-show="receiptAnalysisComplete" x-transition.opacity class="payment-receipt-auto-fields">
                                <div class="payment-auto-fill-heading"><span><strong>Transaction details</strong><small x-text="duplicateStatus === 'fail' ? 'This receipt or transaction/reference number was already submitted. Upload a different receipt.' : documentStatus === 'fail' ? 'This file is not a payment receipt. Upload a valid receipt to unlock these fields.' : scanNeedsManualReview ? 'Some details were not clear. Please complete or correct the highlighted fields.' : 'We filled in the receipt details. Please double-check each field.'"></small></span><i :class="scanNeedsManualReview ? 'is-review' : ''" x-text="duplicateStatus === 'fail' ? 'Duplicate receipt' : documentStatus === 'fail' ? 'Invalid receipt' : scanNeedsManualReview ? 'Manual review needed' : 'Auto-filled'"></i></div>
                                <div x-show="scanNeedsManualReview && documentStatus !== 'fail' && duplicateStatus !== 'fail'" class="payment-manual-edit-notice"><strong>Almost done</strong><span>Edit any missing or incorrect information below using the receipt as your guide.</span></div>
                                <div x-show="documentStatus === 'fail'" class="payment-invalid-receipt-notice"><strong>Fields locked</strong><span>The uploaded picture was not recognized as a payment receipt. Upload the actual transaction receipt to continue.</span></div>
                                <div x-show="duplicateStatus === 'fail'" class="payment-invalid-receipt-notice"><strong>Duplicate payment detected</strong><span>These details are locked. Upload a different receipt to continue.</span></div>
                                <fieldset class="payment-auto-fields-grid" :disabled="documentStatus === 'fail' || duplicateStatus === 'fail' || receiptScanning" :aria-disabled="(documentStatus === 'fail' || duplicateStatus === 'fail' || receiptScanning).toString()">
                                    <div :class="!validPaymentMode ? 'needs-manual-edit' : ''"><label for="wizard-payment-mode">Mode of payment <em class="payment-required-label">Required</em></label><select id="wizard-payment-mode" x-model="paymentMode" @change="checkPaymentMode()" required aria-required="true"><option value="">Choose how you sent the money</option><option value="gcash">GCash app</option><option value="maya">Maya app</option><option value="bdo_online">BDO online banking</option><option value="bdo_otc">BDO over-the-counter deposit</option><option value="bank_transfer">Other bank · InstaPay / PESONet</option><option value="remittance">Remittance / transfer service</option></select><small>This is the app, bank, or service you used to send.</small></div>
                                    <div :class="!paymentReference || duplicateStatus === 'fail' ? 'needs-manual-edit' : ''"><label for="wizard-reference">Transaction / reference number <em class="payment-required-label">Required</em></label><input id="wizard-reference" type="text" x-model.trim="paymentReference" @input="duplicateStatus = 'waiting'; duplicateMessage = 'Checking the edited transaction/reference…'" @blur="checkDuplicate()" placeholder="Reference No. or Transaction ID" required aria-required="true"><small>AMIS uses the reference number first; if absent, it automatically uses the transaction ID. Duplicate identifiers are rejected.</small></div>
                                    <fieldset :class="!transactionDate || receiptDateStatus === 'fail' ? 'needs-manual-edit' : ''" class="payment-date-field"><legend>Transaction date <em class="payment-required-label">Required</em></legend><div class="payment-date-parts" aria-label="Transaction date in month, day, and year"><label><span>MM</span><input type="text" inputmode="numeric" maxlength="2" autocomplete="off" :value="dateMonth" @input="updateDatePart('dateMonth', $event, 2, 'dateDayInput')" x-ref="dateMonthInput" aria-label="Transaction month" placeholder="MM" required aria-required="true"></label><b>/</b><label><span>DD</span><input type="text" inputmode="numeric" maxlength="2" autocomplete="off" :value="dateDay" @input="updateDatePart('dateDay', $event, 2, 'dateYearInput')" x-ref="dateDayInput" aria-label="Transaction day" placeholder="DD" required aria-required="true"></label><b>/</b><label class="is-year"><span>YYYY</span><input type="text" inputmode="numeric" maxlength="4" autocomplete="off" :value="dateYear" @input="updateDatePart('dateYear', $event, 4)" x-ref="dateYearInput" aria-label="Transaction year" placeholder="YYYY" required aria-required="true"></label></div><small :class="receiptDateStatus === 'fail' ? 'is-error' : receiptDateStatus === 'warning' ? 'is-warning' : ''" x-text="receiptDateStatus === 'waiting' ? `Use ${currentFinanceYear} or an earlier year.` : receiptDateMessage"></small></fieldset>
                                    <fieldset class="payment-date-field payment-time-field"><legend>Transaction time <em>if shown</em></legend><div class="payment-date-parts" aria-label="Transaction time"><label><span>HH</span><input type="text" inputmode="numeric" maxlength="2" autocomplete="off" :value="timeHour" @input="updateTimePart('timeHour', $event, 'timeMinuteInput')" aria-label="Transaction hour" placeholder="HH"></label><b>:</b><label><span>MM</span><input type="text" inputmode="numeric" maxlength="2" autocomplete="off" :value="timeMinute" @input="updateTimePart('timeMinute', $event)" x-ref="timeMinuteInput" aria-label="Transaction minute" placeholder="MM"></label><label class="is-period"><span>AM/PM</span><select x-model="timePeriod" @change="syncTransactionTime(); checkReceiptDate()" aria-label="AM or PM"><option>AM</option><option>PM</option></select></label></div><small>AMIS records the upload timestamp automatically.</small></fieldset>
                                    <div :class="!Number(transactionAmount) || ocrAmountStatus === 'fail' ? 'needs-manual-edit' : ''"><label for="wizard-receipt-amount">Amount paid <em class="payment-required-label">Required</em></label><input id="wizard-receipt-amount" type="text" inputmode="decimal" autocomplete="off" :value="transactionAmount ?? ''" @input="updateAmountInput($event)" placeholder="0.00" required aria-required="true"><small>Full, partial, and advance payments are allowed. AMIS allocates the verified amount automatically.</small></div>
                                </fieldset>
                                <div class="payment-wizard-mode-summary">
                                    <span class="payment-wizard-mode-copy">
                                        @foreach($officialPaymentChannels as $channelKey => $channel)
                                            <img x-show="paymentMethod === '{{ $channelKey }}'" src="{{ asset($channel['logo']) }}" alt="{{ $channel['label'] }}">
                                        @endforeach
                                        <span><small>AMIS payment channel</small><strong x-text="selectedChannel?.label + ' receiving account'"></strong><em x-text="selectedAccount?.number + ' · ' + (selectedAccount?.name || selectedChannel?.account_name || '')"></em></span>
                                    </span>
                                    <button type="button" @click="goToStep(1)">Change channel</button>
                                </div>
                                <button type="button" class="payment-view-scan-result" @click="showSecurityModal = true"><span><strong>Receipt verification result</strong><small x-text="duplicateStatus === 'fail' ? 'Duplicate detected — upload a different receipt' : documentStatus === 'fail' ? 'Action required — upload a valid receipt' : documentStatus === 'pass' ? 'Receipt detected · View verification details' : 'Some fields need your review'"></small></span><b>View result</b></button>
                            </div>
                        </div>
                    </div>

                    <div x-show="showSecurityModal" x-cloak class="payment-receipt-modal" @click.self="!receiptScanning && duplicateStatus !== 'fail' && (showSecurityModal = false)" role="dialog" aria-modal="true" aria-label="Receipt scan and verification result">
                        <div class="payment-receipt-modal-card">
                            {{-- Secure receipt verification loading modal --}}
                            <div x-show="receiptScanning" class="payment-receipt-modal-loading" aria-live="polite">
                                <span class="payment-loading-kicker">AMIS RECEIPT VERIFICATION</span>
                                <h2 class="payment-loading-title">Checking your payment receipt...</h2>
                                <p class="payment-loading-subtitle">Please wait while your receipt is securely checked.</p>

                                {{-- Center Receipt Scanner Box with Animated Magnifying Glass --}}
                                <div class="payment-scanner-preview-box">
                                    <template x-if="receiptPreview">
                                        <div class="payment-scanner-frame">
                                            <img :src="receiptPreview" alt="Uploaded receipt preview" class="payment-scanner-preview-img">

                                            {{-- Moving Magnifying Glass Lens over Receipt --}}
                                            <div class="payment-scanner-magnifier">
                                                <div class="magnifier-lens">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                                </div>
                                            </div>

                                            {{-- Scanning Laser Line --}}
                                            <div class="payment-scan-laser-line"></div>
                                        </div>
                                    </template>
                                    <template x-if="!receiptPreview">
                                        <div class="payment-scanner-preview-placeholder">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                        </div>
                                    </template>
                                </div>

                                {{-- Progress Bar --}}
                                <div class="payment-modal-progress-bar-wrapper">
                                    <div class="payment-modal-progress-bar"><i :style="`width: ${ocrProgress}%`"></i></div>
                                    <div class="payment-progress-label-row">
                                        <small x-text="ocrProgressLabel"></small>
                                        <strong x-text="Math.round(ocrProgress) + '%'"></strong>
                                    </div>
                                </div>

                                {{-- 5-Stage Verification Steps Tracker --}}
                                <div class="payment-modal-steps-tracker" aria-label="Verification progress stages">
                                    <div class="payment-step-item" :class="ocrProgress >= 20 ? 'is-done' : 'is-active'">
                                        <div class="payment-step-icon">
                                            <template x-if="ocrProgress >= 20"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                            <template x-if="ocrProgress < 20"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg></template>
                                        </div>
                                        <span>Reading</span>
                                    </div>
                                    <div class="payment-step-line" :class="ocrProgress >= 20 ? 'is-done' : ''"></div>

                                    <div class="payment-step-item" :class="ocrProgress >= 40 ? 'is-done' : (ocrProgress >= 20 ? 'is-active' : 'is-upcoming')">
                                        <div class="payment-step-icon">
                                            <template x-if="ocrProgress >= 40"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                            <template x-if="ocrProgress >= 20 && ocrProgress < 40"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></template>
                                        </div>
                                        <span>Extracting</span>
                                    </div>
                                    <div class="payment-step-line" :class="ocrProgress >= 40 ? 'is-done' : ''"></div>

                                    <div class="payment-step-item" :class="ocrProgress >= 60 ? 'is-done' : (ocrProgress >= 40 ? 'is-active' : 'is-upcoming')">
                                        <div class="payment-step-icon">
                                            <template x-if="ocrProgress >= 60"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                            <template x-if="ocrProgress >= 40 && ocrProgress < 60"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg></template>
                                        </div>
                                        <span>Reference</span>
                                    </div>
                                    <div class="payment-step-line" :class="ocrProgress >= 60 ? 'is-done' : ''"></div>

                                    <div class="payment-step-item" :class="ocrProgress >= 80 ? 'is-done' : (ocrProgress >= 60 ? 'is-active' : 'is-upcoming')">
                                        <div class="payment-step-icon">
                                            <template x-if="ocrProgress >= 80"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                            <template x-if="ocrProgress >= 60 && ocrProgress < 80"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9h-1.5c-.621 0-1.125.504-1.125 1.125v3.375m9.75 4.5l-3.375 3.375"/></svg></template>
                                        </div>
                                        <span>Duplicate</span>
                                    </div>
                                    <div class="payment-step-line" :class="ocrProgress >= 80 ? 'is-done' : ''"></div>

                                    <div class="payment-step-item" :class="ocrProgress >= 95 ? 'is-done' : (ocrProgress >= 80 ? 'is-active' : 'is-upcoming')">
                                        <div class="payment-step-icon">
                                            <template x-if="ocrProgress >= 95"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                            <template x-if="ocrProgress >= 80 && ocrProgress < 95"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751A11.959 11.959 0 0112 2.714z"/></svg></template>
                                        </div>
                                        <span>Verifying</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Professional Compact Banking Receipt Scan Result View --}}
                            <div x-show="!receiptScanning" class="payment-receipt-modal-result">
                                <div class="payment-result-header">
                                    <span class="payment-result-kicker">RECEIPT VERIFICATION RESULT</span>
                                    <div class="payment-result-title-row">
                                        <span class="payment-result-status-badge" :class="duplicateStatus === 'fail' || documentStatus === 'fail' || receiptMustBeReuploaded ? 'is-fail' : (scanNeedsManualReview ? 'is-warning' : 'is-pass')">
                                            <template x-if="duplicateStatus === 'fail'">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10" fill="#e11d48" stroke="none"/><path d="M12 7.5v5m0 3.5h.01" stroke="#ffffff"/></svg>
                                            </template>
                                            <template x-if="duplicateStatus !== 'fail' && (documentStatus === 'fail' || receiptMustBeReuploaded)">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </template>
                                            <template x-if="duplicateStatus !== 'fail' && documentStatus !== 'fail' && !receiptMustBeReuploaded && scanNeedsManualReview">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M12 18h.008v.008H12V18z"/></svg>
                                            </template>
                                            <template x-if="duplicateStatus !== 'fail' && documentStatus !== 'fail' && !receiptMustBeReuploaded && !scanNeedsManualReview">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            </template>
                                        </span>
                                        <h2 x-text="scanResultTitle"></h2>
                                    </div>
                                    <p class="payment-result-subtitle" x-text="scanResultSummary"></p>
                                </div>

                                <div class="payment-result-divider"></div>

                                {{-- Receipt Details Grid --}}
                                <div x-show="documentStatus !== 'fail' && (paymentMode || paymentReference || transactionDate || transactionAmount)" class="payment-result-details-section">
                                    <h3 class="payment-result-section-title">Detected payment details</h3>
                                    <div class="payment-result-details-grid">
                                        <div class="payment-result-detail-item">
                                            <span class="payment-detail-label">Payment Provider / Mode</span>
                                            <strong class="payment-detail-value" x-text="paymentModeLabel(paymentMode) || 'Other / Unknown'"></strong>
                                        </div>
                                        <div class="payment-result-detail-item">
                                            <span class="payment-detail-label">Transaction / Reference No.</span>
                                            <strong class="payment-detail-value" x-text="paymentReference || 'Not detected'"></strong>
                                        </div>
                                        <div class="payment-result-detail-item">
                                            <span class="payment-detail-label">Date & Time</span>
                                            <strong class="payment-detail-value" x-text="transactionDate ? formatReceiptTimestamp() : 'Not detected'"></strong>
                                        </div>
                                        <div class="payment-result-detail-item">
                                            <span class="payment-detail-label">Amount</span>
                                            <strong class="payment-detail-value" x-text="transactionAmount ? money(transactionAmount) : 'Not detected'"></strong>
                                        </div>
                                    </div>
                                    <div class="payment-result-divider"></div>
                                </div>

                                {{-- Compact Verification Summary & Collapsible Details --}}
                                <div class="payment-result-verification-section">
                                    <div class="payment-verification-summary-row">
                                        <span class="payment-summary-pass">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            <strong x-text="`${[receiptFile ? 'pass': '', documentStatus, imageQualityStatus, ocrAmountStatus, paymentMethodStatus, receiptDateStatus, duplicateStatus].filter(s => s === 'pass').length} checks passed`"></strong>
                                        </span>
                                        <template x-if="[receiptFile ? 'pass': '', documentStatus, imageQualityStatus, ocrAmountStatus, paymentMethodStatus, receiptDateStatus, duplicateStatus].filter(s => s === 'fail').length > 0">
                                            <span class="payment-summary-fail">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width: 15px; height: 15px;"><circle cx="12" cy="12" r="10" fill="#e11d48" stroke="none"/><path d="M12 7.5v5m0 3.5h.01" stroke="#ffffff"/></svg>
                                                <strong x-text="`${[receiptFile ? 'pass': '', documentStatus, imageQualityStatus, ocrAmountStatus, paymentMethodStatus, receiptDateStatus, duplicateStatus].filter(s => s === 'fail').length} issue detected`"></strong>
                                            </span>
                                        </template>
                                    </div>

                                    {{-- Failed Issue Only (Shown when duplicate/failed) --}}
                                    <div x-show="duplicateStatus === 'fail'" class="payment-failed-issue-box">
                                        <div class="payment-timeline-item is-fail">
                                            <div class="payment-timeline-badge is-error">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width: 16px; height: 16px;"><circle cx="12" cy="12" r="10" fill="#e11d48" stroke="none"/><path d="M12 7.5v5m0 3.5h.01" stroke="#ffffff"/></svg>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>Duplicate receipt detected</strong>
                                                <small x-text="duplicateMessage || 'This reference or receipt was already submitted.'"></small>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Collapsible Details Button --}}
                                    <div class="payment-toggle-details-wrap">
                                        <button type="button" class="payment-toggle-details-btn" @click="showVerificationDetails = !showVerificationDetails" x-text="showVerificationDetails ? 'Hide verification details' : 'View verification details'"></button>
                                    </div>

                                    {{-- Collapsed List of Passed Checks --}}
                                    <div x-show="showVerificationDetails" x-transition.opacity class="payment-result-timeline is-expanded">
                                        <div class="payment-timeline-item" :class="securityClass(receiptFile ? 'pass' : 'waiting')">
                                            <div class="payment-timeline-badge">
                                                <template x-if="receiptFile"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>File verified</strong>
                                                <small x-text="checkDisplayMessage('file', receiptFile ? 'pass' : 'waiting')"></small>
                                            </div>
                                        </div>

                                        <div class="payment-timeline-item" :class="securityClass(documentStatus)">
                                            <div class="payment-timeline-badge">
                                                <template x-if="documentStatus === 'pass'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                                <template x-if="documentStatus === 'fail'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>Receipt readable</strong>
                                                <small x-text="checkDisplayMessage('document', documentStatus)"></small>
                                            </div>
                                        </div>

                                        <div class="payment-timeline-item" :class="securityClass(imageQualityStatus)">
                                            <div class="payment-timeline-badge">
                                                <template x-if="imageQualityStatus === 'pass'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                                <template x-if="imageQualityStatus === 'fail'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>Image clarity verified</strong>
                                                <small x-text="checkDisplayMessage('image', imageQualityStatus)"></small>
                                            </div>
                                        </div>

                                        <div class="payment-timeline-item" :class="securityClass(ocrAmountStatus)">
                                            <div class="payment-timeline-badge">
                                                <template x-if="ocrAmountStatus === 'pass'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                                <template x-if="ocrAmountStatus === 'fail'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>Payment details verified</strong>
                                                <small x-text="checkDisplayMessage('amount', ocrAmountStatus)"></small>
                                            </div>
                                        </div>

                                        <div class="payment-timeline-item" :class="securityClass(paymentMethodStatus)">
                                            <div class="payment-timeline-badge">
                                                <template x-if="paymentMethodStatus === 'pass'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                                <template x-if="paymentMethodStatus === 'fail'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>Payment mode verified</strong>
                                                <small x-text="checkDisplayMessage('mode', paymentMethodStatus)"></small>
                                            </div>
                                        </div>

                                        <div class="payment-timeline-item" :class="securityClass(receiptDateStatus)">
                                            <div class="payment-timeline-badge">
                                                <template x-if="receiptDateStatus === 'pass'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                                <template x-if="receiptDateStatus === 'fail'"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></template>
                                            </div>
                                            <div class="payment-timeline-content">
                                                <strong>Transaction date verified</strong>
                                                <small x-text="checkDisplayMessage('date', receiptDateStatus)"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Primary Action Button (Always Visible) --}}
                                <div class="payment-result-actions">
                                    <button type="button" class="payment-result-btn-primary" @click="duplicateStatus === 'fail' || documentStatus === 'fail' || receiptMustBeReuploaded ? (removeReceipt(), showSecurityModal = false) : (showSecurityModal = false)" x-text="duplicateStatus === 'fail' ? 'Re-upload a different receipt' : (documentStatus === 'fail' || receiptMustBeReuploaded ? 'Replace receipt' : scanNeedsManualReview ? 'Edit missing details' : 'Continue to details')"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p x-show="paymentError" x-text="paymentError" class="payment-form-message is-error"></p>
                    <div class="payment-wizard-actions"><button type="button" class="payment-secondary-action" @click="goToStep(1)">Back</button><button type="button" class="payment-primary-action" :disabled="!securityReady" @click="continueToPreview()">Review receipt <span>→</span></button></div>
                </section>

                {{-- Step 3: final preview --}}
                <section x-show="step === 3" class="payment-wizard-step">
                    <div class="payment-wizard-title"><span class="payment-section-kicker">Step 3 of 3 · Finance verification</span><h1>Submit your family payment receipt</h1><p>After submission, wait for Finance approval. AMIS will allocate the verified amount automatically.</p></div>
                    <div class="payment-wizard-preview-grid">
                        <div class="payment-wizard-preview-block"><h2>AMIS payment channel <button type="button" @click="goToStep(1)">Change</button></h2>@foreach($officialPaymentChannels as $channelKey => $channel)<div x-show="paymentMethod === '{{ $channelKey }}'" class="payment-wizard-preview-account"><img src="{{ asset($channel['logo']) }}" alt="{{ $channel['label'] }}"><span><strong>{{ $channel['label'] }}</strong><small x-text="selectedAccount?.number"></small><em x-text="selectedAccount?.name || selectedChannel?.account_name"></em></span></div>@endforeach</div>
                        <div class="payment-wizard-preview-block"><h2>Transaction <button type="button" @click="goToStep(2)">Change</button></h2><div class="payment-wizard-preview-reference"><span><small x-text="paymentModeLabel(paymentMode) + ' · ' + formatReceiptTimestamp()"></small><strong x-text="paymentReference"></strong><em x-text="money(transactionAmount)"></em></span><img :src="receiptPreview" alt="Receipt thumbnail"></div></div>
                    </div>
                    <div class="payment-wizard-preview-block"><h2>Automatic family allocation</h2><p>Finance verifies the receipt first. AMIS then pays the oldest outstanding family balance, moves any remainder to the next month, and stores unused excess as advance credit.</p></div>
                    <p x-show="paymentError" x-text="paymentError" class="payment-form-message is-error"></p>
                    <div class="payment-wizard-actions"><button type="button" class="payment-secondary-action" @click="goToStep(2)" :disabled="paymentLoading">Back</button><button type="button" class="payment-primary-action" :disabled="paymentLoading" @click="showSubmitConfirmation = true">Submit</button></div>
                </section>

                <div x-show="showSubmitConfirmation" x-cloak class="payment-submit-confirmation-modal" @click.self="!paymentLoading && (showSubmitConfirmation = false)" @keydown.escape.window="!paymentLoading && (showSubmitConfirmation = false)">
                    <section class="payment-submit-confirmation-card" role="dialog" aria-modal="true" aria-labelledby="submit-confirmation-title">
                        <span class="payment-submit-confirmation-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12.75l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <span class="payment-section-kicker">Final confirmation</span>
                        <h2 id="submit-confirmation-title">Submit this payment receipt?</h2>
                        <p>Please confirm the details below. Finance will review the receipt before AMIS applies the payment to your family balance.</p>
                        <dl class="payment-submit-confirmation-details">
                            <div><dt>Amount</dt><dd x-text="money(transactionAmount)"></dd></div>
                            <div><dt>Payment method</dt><dd x-text="paymentModeLabel(paymentMode)"></dd></div>
                            <div><dt>Transaction / Reference No.</dt><dd x-text="paymentReference || 'Not recorded'"></dd></div>
                        </dl>
                        <div class="payment-submit-confirmation-actions">
                            <button type="button" class="payment-secondary-action" :disabled="paymentLoading" @click="showSubmitConfirmation = false">Cancel</button>
                            <button type="button" class="payment-primary-action" :disabled="paymentLoading" @click="submitPayment()"><span x-text="paymentLoading ? 'Submitting…' : 'Yes, submit payment'"></span></button>
                        </div>
                    </section>
                </div>
            </main>

            <section x-show="submissionComplete" x-cloak class="payment-checkout-success">
                <span class="payment-checkout-success-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/></svg></span>
                <span class="payment-section-kicker">Receipt submitted</span>
                <h1>Thank you for your payment!</h1>
                <p>Your receipt is awaiting Finance verification. Duplicate submission protection is now active.</p>
                <div><small>Submission number</small><strong x-text="submissionNumber"></strong></div>

                <aside class="payment-checkout-success-help" aria-label="Payment submission help">
                    <span class="payment-checkout-success-help-icon" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    </span>
                    <span class="payment-checkout-success-help-copy">
                        <strong>Need help?</strong>
                        <small>Check Transactions for updates. For upload or account issues, email IT Support and include your submission number and a screenshot. Please do not submit the same receipt again while it is pending.</small>
                    </span>
                    <a :href="'mailto:zhairel.lingasa@gmail.com?subject=' + encodeURIComponent('AMIS payment help - ' + submissionNumber)">Email IT Support</a>
                </aside>

                <nav><a href="{{ route('payment.dashboard') }}?tab=transactions">View transaction status</a><a href="{{ route('payment.dashboard') }}">Return to dashboard</a></nav>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            function paymentWizard() {
                return {
                    retryPayment: {{ Js::from($retryPayment) }},
                    step: {{ $retryPayment ? 2 : 1 }},
                    maxVisitedStep: {{ $retryPayment ? 2 : 1 }},
                    familyOutstandingBalance: {{ Js::from($familyOutstandingBalance) }},
                    currentFinanceYear: {{ now(config('finance.timezone', 'Asia/Manila'))->year }},
                    channels: {{ Js::from($officialPaymentChannels) }},
                    // paymentMethod is the official AMIS destination channel.
                    paymentMethod: {{ Js::from($retryPayment['method'] ?? '') }},
                    selectedAccountIndex: 0,
                    paymentMode: {{ Js::from($retryPayment['payment_mode'] ?? '') }},
                    paymentReference: {{ Js::from($retryPayment['reference'] ?? '') }},
                    transactionDate: {{ Js::from($retryPayment['transaction_date'] ?? '') }},
                    dateMonth: '',
                    dateDay: '',
                    dateYear: '',
                    transactionTime: {{ Js::from($retryPayment['transaction_time'] ?? '') }},
                    timeHour: '',
                    timeMinute: '',
                    timePeriod: 'AM',
                    transactionAmount: {{ Js::from($retryPayment['amount'] ?? null) }},
                    receiptFile: null,
                    receiptPreview: null,
                    receiptSubmissionId: '',
                    receiptScanning: false,
                    showSecurityModal: false,
                    showSubmitConfirmation: false,
                    showVerificationDetails: false,
                    receiptAnalysisComplete: false,
                    receiptMustBeReuploaded: false,
                    receiptScanMessage: 'Waiting for receipt',
                    ocrProgress: 0,
                    ocrProgressLabel: 'Checking your payment receipt…',
                    receiptHash: '',
                    receiptPerceptualHash: '',
                    imageQualityStatus: 'waiting',
                    imageQualityMessage: 'Waiting for receipt',
                    ocrAmountStatus: 'waiting',
                    ocrAmountMessage: 'Waiting for receipt',
                    documentStatus: 'waiting',
                    documentMessage: 'Waiting for receipt',
                    paymentMethodStatus: 'waiting',
                    paymentMethodMessage: 'Waiting for receipt',
                    receiptDateStatus: 'waiting',
                    receiptDateMessage: 'Waiting for receipt',
                    duplicateStatus: 'waiting',
                    duplicateMessage: 'Upload a receipt to check for duplicates.',
                    detectedReceipt: {method: null, amount: null, date: null, time: null, sender: null, receiver: null, merchant: null, account: null},
                    autoFilledReference: '',
                    paymentError: '',
                    paymentLoading: false,
                    copiedKey: '',
                    clientToken: '',
                    submissionComplete: false,
                    submissionNumber: '',
                    scanToken: '',
                    scanRecordSaved: false,
                    scanRecordError: '',

                    init() {
                        this.clientToken = this.makeUuid();
                        if (this.retryPayment) {
                            const accountIndex = (this.selectedChannel?.accounts || []).findIndex(account => String(account.number).replace(/\s+/g, '') === String(this.retryPayment.account_received || '').replace(/\s+/g, ''));
                            this.selectedAccountIndex = accountIndex >= 0 ? accountIndex : 0;
                            if (this.transactionDate) {
                                const [year, month, day] = this.transactionDate.split('-');
                                this.dateYear = year || ''; this.dateMonth = month || ''; this.dateDay = day || '';
                            }
                            if (this.transactionTime) {
                                const [hour24, minute] = this.transactionTime.split(':');
                                const numericHour = Number(hour24 || 0);
                                this.timePeriod = numericHour >= 12 ? 'PM' : 'AM';
                                this.timeHour = String(numericHour % 12 || 12).padStart(2, '0');
                                this.timeMinute = minute || '';
                            }
                        }
                        this._blockContextMenu = event => event.preventDefault();
                        this._blockDeveloperShortcut = event => {
                            const blocked = event.key === 'F12' || (event.ctrlKey && event.shiftKey && ['I', 'J', 'C'].includes(event.key.toUpperCase())) || (event.ctrlKey && event.key.toUpperCase() === 'U');
                            if (blocked) event.preventDefault();
                        };
                        this.$el.addEventListener('contextmenu', this._blockContextMenu);
                        window.addEventListener('keydown', this._blockDeveloperShortcut);
                    },
                    destroy() { this.$el.removeEventListener('contextmenu', this._blockContextMenu); window.removeEventListener('keydown', this._blockDeveloperShortcut); },
                    makeUuid() { return window.crypto?.randomUUID ? window.crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => { const random = Math.random() * 16 | 0; return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16); }); },
                    async fetchWithTimeout(url, options = {}, timeoutMs = 8000) {
                        const controller = new AbortController();
                        const timeout = setTimeout(() => controller.abort(), timeoutMs);
                        try {
                            return await fetch(url, {...options, signal: controller.signal});
                        } finally {
                            clearTimeout(timeout);
                        }
                    },
                    get paymentTotal() { return Number(this.familyOutstandingBalance || 0); },
                    get selectedChannel() { return this.channels[this.paymentMethod] || null; },
                    get selectedAccount() { return this.selectedChannel?.accounts?.[this.selectedAccountIndex] || null; },
                    get isPartialPayment() { return this.amountCents(this.transactionAmount) > 0 && this.amountCents(this.transactionAmount) < this.amountCents(this.paymentTotal); },
                    get validPaymentMode() { return ['gcash', 'maya', 'bdo_online', 'bdo_otc', 'bank_transfer', 'remittance'].includes(this.paymentMode); },
                    get requiredDetailsComplete() { return this.validPaymentMode && !!this.paymentReference && !!this.transactionDate && Number(this.transactionAmount) > 0; },
                    get scanNeedsManualReview() { return this.documentStatus === 'warning' || this.imageQualityStatus === 'warning' || !this.requiredDetailsComplete || ['warning', 'fail'].includes(this.ocrAmountStatus) || ['warning', 'fail'].includes(this.paymentMethodStatus) || ['warning', 'fail'].includes(this.receiptDateStatus) || this.duplicateStatus !== 'pass'; },
                    get scanResultStatus() { return this.documentStatus === 'fail' || this.receiptMustBeReuploaded || this.duplicateStatus === 'fail' ? 'fail' : this.scanNeedsManualReview ? 'warning' : 'pass'; },
                    get scanResultTitle() { return this.duplicateStatus === 'fail' ? 'Duplicate Receipt Detected' : this.documentStatus === 'fail' ? 'This is not a payment receipt' : this.receiptMustBeReuploaded ? 'Please upload a clearer receipt' : this.scanNeedsManualReview ? 'Some details need your review' : 'Receipt details completed'; },
                    get scanResultSummary() { if (this.duplicateStatus === 'fail') return 'AMIS found that this exact receipt image or reference number was already submitted. Please re-upload a different receipt to continue.'; if (this.documentStatus === 'fail') return this.documentMessage; if (this.receiptMustBeReuploaded) return this.imageQualityMessage; return this.scanNeedsManualReview ? 'Some payment details could not be read automatically. Complete or correct them below; Finance will verify the original receipt.' : 'AMIS found all required payment details. Please double-check them before continuing.'; },
                    get securityReady() { return this.receiptAnalysisComplete && !!this.receiptFile && this.requiredDetailsComplete && !this.receiptMustBeReuploaded && this.documentStatus !== 'fail' && this.ocrAmountStatus !== 'fail' && this.paymentMethodStatus !== 'fail' && this.receiptDateStatus !== 'fail' && this.duplicateStatus !== 'fail' && !this.receiptScanning; },
                    money(value) { return '₱' + Number(value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
                    amountCents(value) { return Math.round(Number(value || 0) * 100); },
                    securityClass(status) { return status === 'pass' ? 'is-pass' : status === 'fail' ? 'is-fail' : status === 'warning' ? 'is-warning' : 'is-waiting'; },
                    checkDisplayMessage(kind, status) {
                        if (status === 'waiting') return 'Checking…';
                        if (this.documentStatus === 'fail' && ['amount', 'mode', 'date'].includes(kind)) return 'Not checked — upload a valid receipt';
                        const passed = {file: 'File accepted', document: 'Payment receipt confirmed', image: 'Image is clear and readable', amount: this.isPartialPayment ? `${this.money(this.transactionAmount)} partial payment accepted` : `${this.money(this.transactionAmount)} exact amount confirmed`, mode: this.paymentMethodMessage || 'Payment mode confirmed', date: this.receiptDateMessage || 'Recent transaction date confirmed', duplicate: this.duplicateMessage || 'No duplicate payment found'};
                        const warning = {document: this.documentMessage || 'Some details need your review', amount: this.ocrAmountMessage || 'Enter the receipt amount', mode: this.paymentMethodMessage || 'Select the payment mode', date: this.receiptDateMessage || 'Enter the transaction date', duplicate: this.duplicateMessage || 'Finance will check again'};
                        const failed = {file: 'Upload a JPG or PNG receipt', document: 'Upload the actual payment receipt', image: 'Please re-upload a clearer, full-size image', amount: this.amountCheckDisplayMessage(), mode: this.paymentMethodMessage || 'Correct the selected payment mode', date: this.receiptDateMessage || 'Correct the transaction date', duplicate: 'Duplicate Receipt: This reference or receipt was already submitted. Please re-upload a different receipt.'};
                        return status === 'pass' ? (passed[kind] || 'Check passed') : status === 'fail' ? (failed[kind] || 'Needs attention') : (warning[kind] || 'Please review');
                    },
                    amountCheckDisplayMessage() {
                        const amount = this.amountCents(this.transactionAmount);
                        const expected = this.amountCents(this.paymentTotal);
                        if (!amount) return 'Enter the amount shown on the receipt';
                        if (amount > expected) return `${this.money((amount - expected) / 100)} over the outstanding balance`;
                        if (amount < expected) return `${this.money((expected - amount) / 100)} remains after Finance verification`;
                        return 'Correct the payment amount';
                    },
                    currentRiskCodes() {
                        const codes = [];
                        if (this.documentStatus === 'fail') codes.push('NOT_A_RECEIPT');
                        if (this.receiptMustBeReuploaded) codes.push('LOW_IMAGE_QUALITY');
                        if (this.ocrAmountStatus === 'fail') codes.push('AMOUNT_MISMATCH');
                        if (!this.requiredDetailsComplete) codes.push('REQUIRED_DETAILS_MISSING');
                        if (this.paymentMethodStatus === 'fail') codes.push('CHANNEL_MISMATCH');
                        if (this.receiptDateStatus === 'fail') codes.push('INVALID_DATE');
                        if (this.receiptDateMessage.startsWith('RECEIPT_EXPIRED')) codes.push('RECEIPT_EXPIRED');
                        if (this.duplicateStatus === 'fail') codes.push('DUPLICATE_REFERENCE_OR_RECEIPT');
                        if (this.duplicateStatus === 'warning' && this.duplicateMessage.includes('visually similar')) codes.push('POSSIBLE_REUSED_RECEIPT');
                        if (this.documentStatus !== 'fail' && (!this.detectedReceipt.account || !this.detectedReceipt.date || this.detectedReceipt.amount === null)) codes.push('RECEIPT_CROPPED');
                        return [...new Set(codes)];
                    },
                    canVisitStep(target) { if (target > this.maxVisitedStep) return false; if (target >= 2 && !this.paymentMethod) return false; if (target >= 3 && !this.securityReady) return false; return true; },
                    goToStep(target) { this.step = target; this.maxVisitedStep = Math.max(this.maxVisitedStep, target); this.paymentError = ''; this.$nextTick(() => this.$refs.wizardCard?.scrollIntoView({behavior: 'smooth', block: 'start'})); },
                    selectChannel(key) { this.paymentMethod = key; this.selectedAccountIndex = 0; },
                    async copyDetail(value, key) { try { await navigator.clipboard.writeText(value); this.copiedKey = key; setTimeout(() => this.copiedKey === key && (this.copiedKey = ''), 1500); } catch (error) { this.paymentError = 'Could not copy automatically. Please copy the number manually.'; } },

                    async chooseReceipt(event) {
                        const file = event.target.files?.[0]; if (!file) return;
                        this.paymentError = '';
                        const extension = String(file.name || '').toLowerCase().split('.').pop();
                        const allowedExtension = ['jpg', 'jpeg', 'png'].includes(extension);
                        const allowedMimeType = !file.type || ['image/jpeg', 'image/jpg', 'image/png'].includes(file.type);
                        if (!allowedExtension || !allowedMimeType) { this.paymentError = 'PDF files are disabled. Upload a JPG, JPEG, or PNG screenshot or photo.'; event.target.value = ''; return; }
                        if (file.size > 10 * 1024 * 1024) { this.paymentError = 'The receipt must be 10 MB or smaller.'; event.target.value = ''; return; }
                        if (this.receiptPreview?.startsWith('blob:')) URL.revokeObjectURL(this.receiptPreview);
                        this.receiptFile = file; this.receiptPreview = URL.createObjectURL(file); this.receiptSubmissionId = ''; this.receiptScanning = true; this.receiptAnalysisComplete = false; this.receiptMustBeReuploaded = false; this.showSecurityModal = true; this.scanToken = this.makeUuid(); this.scanRecordSaved = false; this.scanRecordError = '';
                        this.ocrProgress = 5; this.ocrProgressLabel = 'Checking your payment receipt…'; this.receiptHash = ''; this.receiptPerceptualHash = '';
                        this.imageQualityStatus = 'waiting'; this.imageQualityMessage = 'Checking resolution and clarity…';
                        this.ocrAmountStatus = 'waiting'; this.ocrAmountMessage = 'Reading receipt amount…';
                        this.documentStatus = 'waiting'; this.documentMessage = 'Checking document type…';
                        this.paymentMethodStatus = 'waiting'; this.paymentMethodMessage = 'Detecting payment mode…';
                        this.receiptDateStatus = 'waiting'; this.receiptDateMessage = 'Reading transaction date…';
                        this.duplicateStatus = 'waiting'; this.duplicateMessage = 'Checking reference and receipt fingerprint…';
                        this.detectedReceipt = {method: null, amount: null, date: null, time: null, sender: null, receiver: null, merchant: null, account: null};
                        try {
                            await Promise.all([this.analyzeImage(file), this.hashReceipt(file), this.scanReceipt(file)]);
                            this.ocrProgress = 90; this.ocrProgressLabel = 'Verifying payment details…';
                            await this.checkDuplicate();
                            await this.recordReceiptScan();
                        } finally {
                            this.ocrProgress = 100; this.ocrProgressLabel = 'Receipt verification complete';
                            await new Promise(resolve => setTimeout(resolve, 350));
                            this.receiptScanning = false; this.receiptAnalysisComplete = true;
                        }
                    },
                    removeReceipt() {
                        if (this.receiptPreview?.startsWith('blob:')) URL.revokeObjectURL(this.receiptPreview);
                        this.receiptFile = null; this.receiptPreview = null; this.receiptSubmissionId = ''; this.receiptScanning = false; this.receiptAnalysisComplete = false; this.receiptMustBeReuploaded = false; this.receiptScanMessage = 'Waiting for receipt'; this.ocrProgress = 0; this.ocrProgressLabel = 'Checking your payment receipt…'; this.receiptHash = ''; this.receiptPerceptualHash = '';
                        this.paymentReference = ''; this.autoFilledReference = ''; this.paymentMode = ''; this.transactionDate = ''; this.dateMonth = ''; this.dateDay = ''; this.dateYear = ''; this.transactionTime = ''; this.timeHour = ''; this.timeMinute = ''; this.timePeriod = 'AM'; this.transactionAmount = null; this.scanToken = ''; this.scanRecordSaved = false; this.scanRecordError = '';
                        this.imageQualityStatus = 'waiting'; this.imageQualityMessage = 'Waiting for receipt'; this.ocrAmountStatus = 'waiting'; this.ocrAmountMessage = 'Waiting for receipt'; this.documentStatus = 'waiting'; this.documentMessage = 'Waiting for receipt'; this.paymentMethodStatus = 'waiting'; this.paymentMethodMessage = 'Waiting for receipt'; this.receiptDateStatus = 'waiting'; this.receiptDateMessage = 'Waiting for receipt'; this.duplicateStatus = 'waiting'; this.duplicateMessage = 'Upload a receipt to check for duplicates.';
                        this.detectedReceipt = {method: null, amount: null, date: null, time: null, sender: null, receiver: null, merchant: null, account: null}; if (this.$refs?.receiptInput) this.$refs.receiptInput.value = '';
                    },
                    normalizePaymentMode(value) {
                        const normalized = String(value || '').trim().toLowerCase();
                        if (!normalized || normalized.includes('unknown') || normalized === 'other') return '';
                        if (normalized.includes('gcash')) return 'gcash';
                        if (normalized.includes('maya')) return 'maya';
                        if (normalized === 'bdo' || normalized.includes('banco de oro')) return 'bdo_online';
                        if (['enjaz', 'telemoney', 'western union', 'moneygram', 'cebuana', 'palawan'].some(provider => normalized.includes(provider))) return 'remittance';
                        if (['bpi', 'unionbank', 'metrobank', 'pnb', 'landbank', 'rcbc', 'security bank', 'gotyme', 'wise', 'instapay', 'pesonet', 'd360', 'bank'].some(provider => normalized.includes(provider))) return 'bank_transfer';
                        return '';
                    },
                    paymentModeLabel(value) {
                        if (!value) return 'Other / Unknown';
                        const val = String(value).toLowerCase();
                        const labels = {
                            gcash: 'GCash',
                            maya: 'Maya',
                            bdo: 'BDO',
                            bdo_online: 'BDO Online Banking',
                            bdo_otc: 'BDO OTC Deposit',
                            bpi: 'BPI',
                            metrobank: 'Metrobank',
                            western_union: 'Western Union',
                            moneygram: 'MoneyGram',
                            cebuana: 'Cebuana Lhuillier',
                            palawanpay: 'Palawan Express',
                            bank_transfer: 'Bank Transfer / InstaPay',
                            remittance: 'Remittance Service'
                        };
                        return labels[val] || value || 'Other / Unknown';
                    },
                    formatReceiptDate(value) { if (!value) return 'Date not entered'; return new Intl.DateTimeFormat('en-PH', {month: 'short', day: 'numeric', year: 'numeric'}).format(new Date(`${value}T12:00:00`)).toUpperCase(); },
                    formatReceiptTimestamp() { return `${this.formatReceiptDate(this.transactionDate)}${this.transactionTime ? ` · ${new Date(`2000-01-01T${this.transactionTime}:00`).toLocaleTimeString('en-PH', {hour: 'numeric', minute: '2-digit'})}` : ' · Time not shown'}`; },
                    normalizeDetectedDate(value) {
                        if (!value) return '';
                        if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10);
                        const named = String(value).match(/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\.?\s+(\d{1,2}),?\s+(20\d{2})/i);
                        if (named) { const months = {jan:1,january:1,feb:2,february:2,mar:3,march:3,apr:4,april:4,may:5,jun:6,june:6,jul:7,july:7,aug:8,august:8,sep:9,sept:9,september:9,oct:10,october:10,nov:11,november:11,dec:12,december:12}; const month = months[named[1].toLowerCase().replace('.', '')]; return `${named[3]}-${String(month).padStart(2, '0')}-${String(named[2]).padStart(2, '0')}`; }
                        const numeric = String(value).match(/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})/); if (numeric) { const first = Number(numeric[1]); const second = Number(numeric[2]); const day = first > 12 ? first : second; const month = first > 12 ? second : first; return `${numeric[3]}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`; }
                        return '';
                    },
                    setTransactionDate(value) {
                        const normalized = this.normalizeDetectedDate(value);
                        this.transactionDate = normalized;
                        const parts = normalized ? normalized.split('-') : [];
                        this.dateYear = parts[0] || '';
                        this.dateMonth = parts[1] || '';
                        this.dateDay = parts[2] || '';
                    },
                    normalizeDetectedTime(value) {
                        const match = String(value || '').match(/\b(\d{1,2}):(\d{2})(?::\d{2})?\s*([AP]M)?\b/i);
                        if (!match) return '';
                        let hour = Number(match[1]); const minute = Number(match[2]); const period = match[3]?.toUpperCase();
                        if (minute > 59 || hour > 23 || (period && (hour < 1 || hour > 12))) return '';
                        if (period === 'AM' && hour === 12) hour = 0;
                        if (period === 'PM' && hour !== 12) hour += 12;
                        return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                    },
                    setTransactionTime(value) {
                        const normalized = this.normalizeDetectedTime(value);
                        this.transactionTime = normalized;
                        if (!normalized) { this.timeHour = ''; this.timeMinute = ''; this.timePeriod = 'AM'; return; }
                        const [hour24, minute] = normalized.split(':').map(Number);
                        this.timePeriod = hour24 >= 12 ? 'PM' : 'AM';
                        this.timeHour = String(hour24 % 12 || 12).padStart(2, '0');
                        this.timeMinute = String(minute).padStart(2, '0');
                    },
                    updateTimePart(field, event, nextReference = null) {
                        const value = String(event.target.value || '').replace(/\D/g, '').slice(0, 2);
                        this[field] = value; event.target.value = value; this.syncTransactionTime();
                        if (nextReference && value.length === 2) this.$nextTick(() => this.$refs[nextReference]?.focus());
                    },
                    syncTransactionTime() {
                        if (!this.timeHour && !this.timeMinute) { this.transactionTime = ''; this.checkReceiptDate(); return; }
                        const hour12 = Number(this.timeHour); const minute = Number(this.timeMinute);
                        if (hour12 < 1 || hour12 > 12 || minute < 0 || minute > 59 || this.timeMinute.length !== 2) { this.transactionTime = ''; this.checkReceiptDate(); return; }
                        let hour24 = hour12 % 12; if (this.timePeriod === 'PM') hour24 += 12;
                        this.transactionTime = `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                        this.checkReceiptDate();
                    },
                    updateDatePart(field, event, maximumLength, nextReference = null) {
                        const value = String(event.target.value || '').replace(/\D/g, '').slice(0, maximumLength);
                        this[field] = value;
                        event.target.value = value;
                        this.assembleTransactionDate();
                        if (nextReference && value.length === maximumLength) this.$nextTick(() => this.$refs[nextReference]?.focus());
                    },
                    assembleTransactionDate() {
                        if (this.dateMonth.length !== 2 || this.dateDay.length !== 2 || this.dateYear.length !== 4) {
                            this.transactionDate = '';
                            this.checkReceiptDate();
                            return;
                        }
                        const month = Number(this.dateMonth); const day = Number(this.dateDay); const year = Number(this.dateYear);
                        const date = new Date(year, month - 1, day, 12, 0, 0);
                        const valid = year >= 2000 && month >= 1 && month <= 12 && day >= 1 && day <= 31 && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
                        this.transactionDate = valid && year <= this.currentFinanceYear ? `${this.dateYear}-${this.dateMonth}-${this.dateDay}` : '';
                        if (!valid) { this.receiptDateStatus = 'fail'; this.receiptDateMessage = 'Enter a valid date using MM / DD / YYYY.'; return; }
                        if (year > this.currentFinanceYear) { this.receiptDateStatus = 'fail'; this.receiptDateMessage = `Year must be ${this.currentFinanceYear} or earlier.`; return; }
                        this.checkReceiptDate();
                    },
                    updateAmountInput(event) {
                        let value = String(event.target.value || '').replace(/[^\d.]/g, '');
                        const pieces = value.split('.');
                        value = pieces.shift().slice(0, 6) + (pieces.length ? `.${pieces.join('').slice(0, 2)}` : '');
                        event.target.value = value;
                        this.transactionAmount = value === '' ? null : value;
                        this.checkReceiptAmount();
                    },
                    checkPaymentMode() {
                        if (!this.paymentMode) { this.paymentMethodStatus = 'waiting'; this.paymentMethodMessage = 'Choose how you sent the money.'; return; }
                        const detected = this.normalizePaymentMode(this.detectedReceipt.method);
                        const matches = !detected || detected === this.paymentMode || (detected === 'bdo_online' && this.paymentMode === 'bdo_otc');
                        this.paymentMethodStatus = detected ? (matches ? 'pass' : 'fail') : 'warning';
                        this.paymentMethodMessage = detected ? (matches ? `${this.paymentModeLabel(this.paymentMode)} detected · Please confirm` : `${this.paymentModeLabel(detected)} was detected. Check your selected mode.`) : `${this.paymentModeLabel(this.paymentMode)} selected manually · Finance will verify it.`;
                    },
                    checkReceiptAmount() {
                        if (!Number(this.transactionAmount)) { this.ocrAmountStatus = 'warning'; this.ocrAmountMessage = 'Enter the amount shown on the receipt.'; return; }
                        const amount = this.amountCents(this.transactionAmount);
                        const expected = this.amountCents(this.paymentTotal);
                        if (!expected) { this.ocrAmountStatus = 'pass'; this.ocrAmountMessage = `${this.money(amount / 100)} will become family advance credit after Finance verification.`; return; }
                        if (amount < expected) { this.ocrAmountStatus = 'pass'; this.ocrAmountMessage = `${this.money(amount / 100)} partial family payment accepted · ${this.money((expected - amount) / 100)} remains after Finance verification.`; return; }
                        if (amount > expected) { this.ocrAmountStatus = 'pass'; this.ocrAmountMessage = `${this.money((amount - expected) / 100)} excess will be recorded as family advance credit.`; return; }
                        this.ocrAmountStatus = 'pass'; this.ocrAmountMessage = `${this.money(amount / 100)} matches the family outstanding balance.`;
                    },
                    checkReceiptDate() {
                        if (!this.transactionDate) { this.receiptDateStatus = 'warning'; this.receiptDateMessage = 'Enter the date shown on the receipt.'; return; }
                        const receiptDate = new Date(`${this.transactionDate}T${this.transactionTime || '12:00'}:00`); const today = new Date(); const ageDays = Math.floor((today - receiptDate) / 86400000);
                        if (receiptDate.getFullYear() > this.currentFinanceYear) { this.receiptDateStatus = 'fail'; this.receiptDateMessage = `INVALID_DATE · Year must be ${this.currentFinanceYear} or earlier.`; }
                        else if (receiptDate > today) { this.receiptDateStatus = 'warning'; this.receiptDateMessage = `${this.formatReceiptTimestamp()} · Later date in ${this.currentFinanceYear}; Finance will confirm it.`; }
                        else if (ageDays > 90) { this.receiptDateStatus = 'warning'; this.receiptDateMessage = `RECEIPT_EXPIRED · ${ageDays} days old. Finance review is required.`; }
                        else if (!this.transactionTime) { this.receiptDateStatus = 'warning'; this.receiptDateMessage = `${this.formatReceiptDate(this.transactionDate)} · Time not detected; Finance will review.`; }
                        else { this.receiptDateStatus = 'pass'; this.receiptDateMessage = `${this.formatReceiptTimestamp()} · Within the last 90 days`; }
                    },
                    analyzeImage(file) {
                        return new Promise(resolve => {
                            const image = new Image();
                            image.onload = () => {
                                const resolutionPass = image.width >= 600 && image.height >= 600;
                                const scale = Math.min(1, 320 / Math.max(image.width, image.height));
                                const canvas = document.createElement('canvas'); canvas.width = Math.max(1, Math.round(image.width * scale)); canvas.height = Math.max(1, Math.round(image.height * scale));
                                const context = canvas.getContext('2d', {willReadFrequently: true}); context.drawImage(image, 0, 0, canvas.width, canvas.height);
                                const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data; let difference = 0; let samples = 0;
                                for (let y = 1; y < canvas.height; y += 2) for (let x = 1; x < canvas.width; x += 2) { const index = (y * canvas.width + x) * 4; const left = index - 4; const top = index - canvas.width * 4; const gray = .299 * pixels[index] + .587 * pixels[index + 1] + .114 * pixels[index + 2]; const grayLeft = .299 * pixels[left] + .587 * pixels[left + 1] + .114 * pixels[left + 2]; const grayTop = .299 * pixels[top] + .587 * pixels[top + 1] + .114 * pixels[top + 2]; difference += Math.abs(gray - grayLeft) + Math.abs(gray - grayTop); samples += 2; }
                                const sharpness = samples ? difference / samples : 0; const clarityPass = sharpness >= 2.5;
                                const hashCanvas = document.createElement('canvas'); hashCanvas.width = 9; hashCanvas.height = 8; const hashContext = hashCanvas.getContext('2d', {willReadFrequently: true}); hashContext.drawImage(image, 0, 0, 9, 8); const hashPixels = hashContext.getImageData(0, 0, 9, 8).data; let bits = ''; for (let row = 0; row < 8; row++) for (let column = 0; column < 8; column++) { const left = ((row * 9) + column) * 4; const right = left + 4; const leftGray = .299 * hashPixels[left] + .587 * hashPixels[left + 1] + .114 * hashPixels[left + 2]; const rightGray = .299 * hashPixels[right] + .587 * hashPixels[right + 1] + .114 * hashPixels[right + 2]; bits += leftGray > rightGray ? '1' : '0'; } this.receiptPerceptualHash = bits.match(/.{4}/g).map(nibble => parseInt(nibble, 2).toString(16)).join('');
                                this.imageQualityStatus = resolutionPass && clarityPass ? 'pass' : 'warning';
                                this.imageQualityMessage = resolutionPass && clarityPass ? `${image.width}×${image.height} · Image looks readable` : !resolutionPass ? `Low resolution (${image.width}×${image.height}). Finance may need to review the original.` : 'The image may be blurry. Finance will review the original if details cannot be read automatically.';
                                URL.revokeObjectURL(image.src); resolve();
                            };
                            image.onerror = () => { this.receiptMustBeReuploaded = true; this.imageQualityStatus = 'fail'; this.imageQualityMessage = 'The image file could not be opened. Please upload a valid receipt image.'; resolve(); };
                            image.src = URL.createObjectURL(file);
                        });
                    },
                    async hashReceipt(file) {
                        if (!window.crypto?.subtle) return;
                        const digest = await window.crypto.subtle.digest('SHA-256', await file.arrayBuffer());
                        this.receiptHash = [...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('');
                    },
                    applyReceiptResult(data) {
                        const detectedMethod = data.detected_method;
                        const detectedAmount = data.detected_amount;
                        const detectedDate = data.detected_date;
                        const detectedTime = data.detected_time;
                        const detectedReference = data.detected_ref;
                        if (data.perceptual_hash) this.receiptPerceptualHash = data.perceptual_hash;
                        this.detectedReceipt = {method: detectedMethod || null, amount: detectedAmount ?? null, date: detectedDate || null, time: detectedTime || null, sender: data.detected_sender || null, receiver: data.detected_receiver || null, merchant: data.detected_merchant || null, account: data.detected_account || null};
                        const documentType = data.document_type;
                        this.documentStatus = documentType === 'receipt' ? 'pass' : documentType === 'not_receipt' ? 'fail' : 'warning';
                        this.documentMessage = data.document_message || 'Finance will manually verify this receipt.';
                        if (this.documentStatus === 'fail') {
                            this.paymentReference = ''; this.autoFilledReference = ''; this.transactionAmount = null; this.setTransactionDate(''); this.setTransactionTime(''); this.paymentMode = '';
                            this.ocrAmountStatus = 'fail'; this.ocrAmountMessage = 'Payment amount was not checked because this is not a receipt.';
                            this.paymentMethodStatus = 'fail'; this.paymentMethodMessage = 'Payment mode was not checked because this is not a receipt.';
                            this.receiptDateStatus = 'fail'; this.receiptDateMessage = 'Transaction date was not checked because this is not a receipt.';
                        } else {
                            this.paymentReference = detectedReference || '';
                            this.autoFilledReference = this.paymentReference;
                            this.transactionAmount = detectedAmount ?? null;
                            this.setTransactionDate(detectedDate);
                            this.setTransactionTime(detectedTime);
                            this.paymentMode = this.normalizePaymentMode(detectedMethod);
                        }
                        if (this.documentStatus !== 'fail') { this.checkReceiptAmount(); this.checkReceiptDate(); this.checkPaymentMode(); }
                        this.receiptScanMessage = documentType === 'not_receipt' ? 'Not a payment receipt — upload the actual transaction receipt' : detectedReference ? 'Payment details detected — please double-check' : 'Some payment details could not be detected';
                    },
                    async scanReceipt(file) {
                        let serverResult = null;
                        try {
                            const formData = new FormData(); formData.append('receipt', file);
                            if (this.retryPayment?.id) formData.append('retry_submission_id', this.retryPayment.id);
                            this.ocrProgress = 12; this.ocrProgressLabel = 'Checking your payment receipt…';
                            const upload = await this.fetchWithTimeout(@json(route('payment.receipts.store')), {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'}, body: formData}, 15000);
                            const accepted = await upload.json();
                            if (!upload.ok) throw new Error(accepted.message || 'Receipt upload failed.');
                            this.receiptSubmissionId = accepted.submission_id;
                            const foregroundDeadline = Date.now() + 15000;
                            let attempt = 0;
                            while (Date.now() < foregroundDeadline) {
                                await new Promise(resolve => setTimeout(resolve, 500));
                                const statusResponse = await this.fetchWithTimeout(accepted.status_url, {headers: {'Accept': 'application/json'}}, 3000);
                                serverResult = await statusResponse.json();
                                if (!statusResponse.ok) throw new Error(serverResult.message || 'Receipt status could not be read.');
                                attempt++;
                                const elapsedRatio = Math.min(1, (15000 - Math.max(0, foregroundDeadline - Date.now())) / 15000);
                                this.ocrProgress = Math.min(72, 18 + elapsedRatio * 54);
                                this.ocrProgressLabel = serverResult.status === 'UPLOADED' ? 'Verifying payment details…' : 'Checking your payment receipt…';
                                if (!serverResult.processing) break;
                            }
                            if (!serverResult || serverResult.processing) {
                                this.documentStatus = 'warning';
                                this.documentMessage = 'The detailed scan is still processing. Continue below and complete the required fields; AMIS Support Staff will verify the original receipt.';
                                if (this.imageQualityStatus === 'waiting') this.imageQualityStatus = 'warning';
                                this.receiptScanMessage = 'Proof received · Continue with the required details';
                                this.ocrProgress = 86;
                                this.ocrProgressLabel = 'Receipt received · You may continue';
                                return;
                            }
                            this.receiptMustBeReuploaded = serverResult.status === 'REUPLOAD_REQUIRED';
                            const readability = serverResult.quality?.readability;
                            this.imageQualityStatus = this.receiptMustBeReuploaded ? 'fail' : ['good', 'acceptable'].includes(readability) ? 'pass' : 'warning';
                            this.imageQualityMessage = serverResult.quality?.message || (this.receiptMustBeReuploaded ? 'The receipt image cannot be reviewed. Please upload a clearer complete receipt.' : readability === 'poor' || readability === 'unreadable' ? 'Some details may need Finance review.' : 'Receipt image received.');
                            serverResult.detected_method = serverResult.provider;
                            serverResult.document_type = serverResult.document_type || 'uncertain';
                            serverResult.document_message = serverResult.document_message
                                || serverResult.review_reason
                                || 'Receipt processed and queued for Finance verification.';
                            this.applyReceiptResult(serverResult);
                            this.ocrProgress = 86;
                            if (this.receiptMustBeReuploaded) {
                                this.imageQualityMessage = serverResult.review_reason || this.imageQualityMessage;
                                this.receiptScanMessage = 'A clearer complete receipt is required';
                                this.ocrProgressLabel = 'Receipt image needs replacement';
                                return;
                            }
                            const criticalComplete = Boolean(serverResult.detected_ref && serverResult.detected_amount && serverResult.detected_date);
                            if (!criticalComplete) {
                                this.documentStatus = 'warning';
                                this.documentMessage = serverResult.review_reason || 'Some payment details could not be read automatically. Complete them below; Finance will verify the original receipt.';
                                this.receiptScanMessage = 'Proof received · Complete missing details';
                            }
                            this.ocrProgressLabel = criticalComplete ? 'Reviewing the detected information…' : 'Receipt received for Finance verification';
                        } catch (serverError) {
                            this.documentStatus = 'warning';
                            this.documentMessage = 'Some payment details could not be read automatically. Complete them below; your proof can still be submitted for Finance verification.';
                            if (this.imageQualityStatus === 'waiting') this.imageQualityStatus = 'warning';
                            this.receiptScanMessage = 'Proof received · Complete the payment details';
                            this.ocrProgress = 86;
                            this.ocrProgressLabel = 'Receipt received for Finance verification';
                        }
                    },
                    async checkDuplicate() {
                        if (!this.paymentReference && !this.receiptHash) { this.duplicateStatus = 'waiting'; this.duplicateMessage = 'Enter the reference number to check for duplicates.'; return; }
                        try {
                            const response = await fetch(@json(route('payment.check-duplicate')), {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({reference_no: this.paymentReference || null, receipt_hash: this.receiptHash || null, perceptual_hash: this.receiptPerceptualHash || null, retry_submission_id: this.retryPayment?.id || null})});
                            const data = await response.json(); if (!response.ok) throw new Error();
                            this.duplicateStatus = data.duplicate ? 'fail' : data.possible_reuse ? 'warning' : 'pass'; this.duplicateMessage = data.message;
                        } catch (error) { this.duplicateStatus = 'warning'; this.duplicateMessage = 'Early duplicate check unavailable; the server will check again before submission.'; }
                    },
                    async recordReceiptScan() {
                        if (!this.scanToken || !this.receiptFile) return false;
                        if (!this.requiredDetailsComplete) {
                            this.scanRecordError = 'Complete all required transaction details before reviewing the receipt.';
                            return false;
                        }
                        try {
                            const response = await fetch(@json(route('payment.ocr-scan-log')), {
                                method: 'POST',
                                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                                body: JSON.stringify({
                                    scan_token: this.scanToken,
                                    billing_ids: [],
                                    receiving_channel: this.paymentMethod || null,
                                    receiving_account: this.selectedAccount?.number || null,
                                    payment_mode: this.paymentMode || null,
                                    reference_no: this.paymentReference || null,
                                    transaction_date: this.transactionDate || null,
                                    transaction_time: this.transactionTime || null,
                                    detected_amount: Number(this.transactionAmount) || null,
                                    expected_amount: this.paymentTotal,
                                    ocr_passes: 0,
                                    document_status: this.documentStatus,
                                    image_quality_status: this.imageQualityStatus,
                                    amount_status: this.ocrAmountStatus,
                                    date_status: this.receiptDateStatus,
                                    duplicate_status: this.duplicateStatus,
                                    scan_status: this.scanResultStatus === 'pass' ? 'complete' : this.scanResultStatus === 'fail' ? 'rejected' : 'manual_review',
                                    risk_codes: this.currentRiskCodes(),
                                    receipt_hash: this.receiptHash || null,
                                    perceptual_hash: this.receiptPerceptualHash || null,
                                }),
                            });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                                throw new Error(validationMessage || data.message || 'Receipt scan log could not be saved.');
                            }
                            this.scanRecordSaved = true;
                            this.scanRecordError = '';
                            return true;
                        } catch (error) {
                            this.scanRecordSaved = false;
                            this.scanRecordError = error.message || 'Receipt scan log could not be saved.';
                            return false;
                        }
                    },
                    async continueToPreview() {
                        if (!this.securityReady) return;
                        const saved = await this.recordReceiptScan();
                        if (!saved) {
                            this.paymentError = this.scanRecordError || 'We could not save the receipt scan record. Please try Review receipt again.';
                            return;
                        }
                        this.goToStep(3);
                    },
                    async submitPayment() {
                        if (!this.securityReady || !this.selectedAccount) return;
                        this.paymentLoading = true; this.paymentError = '';
                        const formData = new FormData(); formData.append('client_token', this.clientToken); formData.append('method', this.paymentMethod); formData.append('payment_mode', this.paymentMode); formData.append('account_received', this.selectedAccount.number); formData.append('reference_no', this.paymentReference); formData.append('transaction_date', this.transactionDate); if (this.transactionTime) formData.append('transaction_time', this.transactionTime); formData.append('receipt_amount', this.transactionAmount); if (this.receiptSubmissionId) formData.append('receipt_submission_id', this.receiptSubmissionId); if (this.retryPayment?.id) formData.append('retry_submission_id', this.retryPayment.id); if (this.detectedReceipt.method) formData.append('local_detected_method', this.detectedReceipt.method); if (this.detectedReceipt.account) formData.append('local_detected_account', this.detectedReceipt.account); if (this.detectedReceipt.receiver) formData.append('local_detected_receiver', this.detectedReceipt.receiver); formData.append('receipt', this.receiptFile);
                        try {
                            const response = await fetch(@json(route('payment.submit')), {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'}, body: formData}); const data = await response.json();
                            if (!response.ok) { const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null; throw new Error(validationMessage || data.message || 'Payment could not be submitted.'); }
                            this.submissionNumber = data.submission_number; this.showSubmitConfirmation = false; this.submissionComplete = true; window.scrollTo({top: 0, behavior: 'smooth'});
                        } catch (error) { this.showSubmitConfirmation = false; this.paymentError = error.message; } finally { this.paymentLoading = false; }
                    }
                };
            }
        </script>
    @endpush
</x-app-layout>
