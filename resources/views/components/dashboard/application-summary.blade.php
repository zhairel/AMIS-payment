@props(['applicant'])

<div style="background:white;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;margin-bottom:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:40px;height:40px;border-radius:50%;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
            <div>
                <div style="font-weight:700;font-size:1rem;color:#111827;">{{ $applicant->last_name }}, {{ $applicant->first_name }} {{ $applicant->middle_name }}</div>
                <div style="font-size:0.8125rem;color:#6b7280;">Prepared {{ strtoupper($applicant->updated_at->format('F j, Y')) }}</div>
            </div>
        </div>
        @php
            $statusColors = [
                'pending' => ['bg'=>'#fef9c3','color'=>'#854d0e','label'=>'Pending Review'],
                'ready_for_submission' => ['bg'=>'#ccfbf1','color'=>'#0f766e','label'=>'Ready for Submission'],
                'submitted' => ['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'Submitted'],
                'under_review' => ['bg'=>'#ede9fe','color'=>'#5b21b6','label'=>'Under Review'],
                'approved' => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'Approved'],
                'rejected' => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Rejected'],
            ];
            $sc = $statusColors[$applicant->status] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($applicant->status)];
        @endphp
        <span style="padding:0.3rem 0.875rem;border-radius:999px;font-size:0.8125rem;font-weight:600;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">{{ $sc['label'] }}</span>
    </div>

    @if ($applicant->status === 'ready_for_submission')
        <div style="padding:1rem 1.5rem;background:#f0fdfa;border-bottom:1px solid #ccfbf1;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
            <span style="font-size:0.875rem;color:#115e59;font-weight:600;">Review this child before final submission.</span>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="{{ route('enrollment.form.child', $applicant) }}" class="family-child-open">Edit</a>
                <a href="{{ route('enrollment.finalize.preview') }}" class="family-finalize">Finalize Enrollment</a>
            </div>
        </div>
    @endif

    <div style="padding:1.25rem 1.5rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
        @php
            $details = [
                'Student Type' => strtoupper($applicant->student_type) . ' Student',
                'Grade Level' => $applicant->grade_level,
                'Learning Mode' => $applicant->learning_mode,
                'School Year' => $applicant->school_year,
                'LRN' => $applicant->lrn,
                'Gender' => $applicant->gender,
                'Date of Birth' => $applicant->date_of_birth ? strtoupper($applicant->date_of_birth->format('M j, Y')) : null,
                'Place of Birth' => $applicant->place_of_birth,
                'Religion' => $applicant->religion,
                'Ethnicity' => $applicant->ethnicity,
                'Country' => $applicant->country,
                'Complete Address' => $applicant->street_address,
                'Postal Code' => $applicant->postal_code,
                'Mobile' => trim(($applicant->mobile_country_code ?? '') . ' ' . ($applicant->mobile_number ?? '')),
                'Parent Mobile' => trim(($applicant->parent_country_code ?? '') . ' ' . ($applicant->parent_mobile ?? '')),
                'Referral Source' => $applicant->referral_source,
            ];
        @endphp
        @foreach ($details as $label => $value)
            <div>
                <div style="font-size:0.75rem;color:#9ca3af;font-weight:500;margin-bottom:0.2rem;">{{ $label }}</div>
                <div style="font-size:0.875rem;color:#111827;font-weight:600;">{{ $value ?? '-' }}</div>
            </div>
        @endforeach
    </div>

    @php
        $docs = [
            '2x2 Picture' => $applicant->photo_2x2_url,
            'Birth Certificate' => $applicant->birth_cert_url,
            'Report Card' => $applicant->report_card_url,
            'Marriage Contract' => $applicant->marriage_contract_url,
            'Medical Record' => $applicant->medical_record_url,
            'Affidavit' => $applicant->affidavit_url,
        ];
        $uploadedDocs = array_filter($docs);
        $paymentReceipts = $applicant->payment?->receipt_urls ?? [];
    @endphp
    @if (count($uploadedDocs) || count($paymentReceipts))
        <div style="padding:0 1.5rem 1.25rem;">
            <div style="font-size:0.75rem;color:#9ca3af;font-weight:500;margin-bottom:0.625rem;">UPLOADED DOCUMENTS</div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                @foreach ($uploadedDocs as $docLabel => $docPath)
                    <a href="{{ asset('storage/' . $docPath) }}" target="_blank" style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.375rem 0.75rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:0.8125rem;color:#065f46;text-decoration:none;font-weight:500;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        {{ $docLabel }}
                    </a>
                @endforeach
                @foreach ($paymentReceipts as $index => $receiptPath)
                    <a href="{{ asset('storage/' . $receiptPath) }}" target="_blank" style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.375rem 0.75rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:0.8125rem;color:#065f46;text-decoration:none;font-weight:500;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Payment Proof {{ count($paymentReceipts) > 1 ? '#' . ($index + 1) : '' }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

@if ($applicant->status === 'rejected')
    @php
        $docStatuses = $applicant->document_statuses ?? [];
        $rejectedItems = collect([
            'registration_form' => 'Registration form',
            'documents' => 'Documents',
            'photo_2x2' => '2x2 picture',
            'birth_cert' => 'Birth certificate',
            'report_card' => 'Report card',
            'marriage_contract' => 'Marriage contract',
            'medical_record' => 'Medical record',
            'affidavit' => 'Affidavit',
            'payment_proof' => 'Payment proof',
        ])->filter(fn ($label, $key) => ($docStatuses[$key] ?? '') === 'rejected')->values();
    @endphp
    <div style="background:white;border-radius:16px;border:1.5px solid #fecdd3;overflow:hidden;margin-bottom:1.5rem;box-shadow:0 2px 8px rgba(220,38,38,0.08);">
        <div style="background:linear-gradient(135deg,#dc2626,#b91c1c);padding:1.25rem 1.5rem;color:white;font-weight:800;">Application Rejected</div>
        <div style="padding:1.25rem 1.5rem;">
            @if ($applicant->review_remarks)
                <div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;color:#7f1d1d;font-size:0.875rem;font-weight:650;line-height:1.5;margin-bottom:0.85rem;padding:0.8rem 0.9rem;">{{ $applicant->review_remarks }}</div>
            @endif
            @if ($rejectedItems->isNotEmpty())
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.85rem;">
                    @foreach ($rejectedItems as $item)
                        <span style="align-items:center;background:#fee2e2;border-radius:999px;color:#991b1b;display:inline-flex;font-size:0.78rem;font-weight:800;gap:0.35rem;padding:0.35rem 0.65rem;">x {{ $item }}</span>
                    @endforeach
                </div>
            @endif
            <a href="{{ route('enrollment.form.child', $applicant) }}" style="display:flex;align-items:center;justify-content:center;gap:0.5rem;width:100%;padding:0.875rem;background:#dc2626;color:white;border-radius:10px;font-size:0.9375rem;font-weight:700;text-decoration:none;">
                Re-edit Form
            </a>
        </div>
    </div>
@endif
