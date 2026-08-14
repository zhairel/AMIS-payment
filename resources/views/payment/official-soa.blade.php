<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Statement of Account - {{ $soaData['student_name'] }} (SY {{ $soaData['school_year'] }})</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.35;
        }

        .no-print-bar {
            width: 210mm;
            max-width: 100%;
            margin: 14px auto 0;
            padding: 0 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }
        .btn-print {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: background 0.15s;
        }
        .btn-print:hover {
            background: #334155;
        }
        .btn-back {
            color: #475569;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 0;
        }
        .btn-back:hover {
            color: #0f172a;
            text-decoration: underline;
        }

        /* SOA VIEWPORT (Interactive mobile viewer: pan & pinch-zoom) */
        .soa-viewport {
            width: 100%;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-x pan-y pinch-zoom;
            padding: 12px 16px 36px;
            box-sizing: border-box;
        }

        /* FIXED A4 SOA PAGE CONTAINER (Never collapses or wraps on mobile) */
        .soa-page {
            width: 210mm;
            min-width: 210mm;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 16px 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            box-sizing: border-box;
        }

        @media screen and (max-width: 768px) {
            .soa-viewport {
                overflow-x: auto;
                overflow-y: auto;
                padding: 10px 8px 24px;
            }
            .soa-page {
                width: 210mm;
                min-width: 210mm;
                max-width: none;
                margin: 0 auto;
            }
        }

        /* HEADER */
        .school-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #065f46;
            padding-bottom: 6px;
            margin-bottom: 8px;
            width: 100%;
        }
        .school-header-cell {
            display: flex;
            align-items: center;
        }
        .header-english {
            width: 42%;
            font-size: 14.5px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0.2px;
            text-align: left;
            justify-content: flex-start;
        }
        .header-logo {
            width: 16%;
            text-align: center;
            justify-content: center;
        }
        .header-logo img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .header-arabic {
            width: 42%;
            justify-content: flex-end;
            text-align: right;
            margin-left: auto;
        }
        .header-arabic img {
            max-height: 42px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
            display: block;
            margin-left: auto;
            margin-right: 0;
        }
        .header-arabic span {
            font-family: 'Amiri', 'Traditional Arabic', serif;
            font-size: 25px;
            font-weight: 700;
            color: #065f46;
            line-height: 1.15;
            display: block;
            width: 100%;
            text-align: right;
            margin-left: auto;
            margin-right: 0;
            direction: rtl;
        }

        /* TITLE BANNER */
        .title-banner {
            background: #a9beba;
            color: #0f172a;
            text-align: center;
            font-size: 12.5px;
            font-weight: 900;
            padding: 4.5px 0;
            border: 1px solid #475569;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        /* UPPER SECTION GRID (3-Column Top-Aligned Layout) */
        .upper-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            table-layout: fixed;
        }
        .upper-left {
            display: table-cell;
            width: 28%;
            vertical-align: top;
            padding-right: 12px;
            font-size: 10px;
            line-height: 1.35;
        }
        .upper-mid {
            display: table-cell;
            width: 38%;
            vertical-align: top;
            padding-right: 12px;
            font-size: 10.5px;
        }
        .upper-right {
            display: table-cell;
            width: 34%;
            vertical-align: top;
        }

        .ayah-quote {
            margin-top: 8px;
            color: #1d4ed8;
            font-style: italic;
            font-size: 9.5px;
            line-height: 1.35;
        }
        .ayah-source {
            font-weight: bold;
            display: block;
            margin-top: 3px;
        }

        .student-header-block {
            margin-bottom: 5px;
            padding-bottom: 3.5px;
            border-bottom: 1px solid #cbd5e1;
            margin-right: -12px;
            width: calc(100% + 12px);
        }
        .student-header-label {
            font-size: 9.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1;
        }
        .student-header-name {
            font-size: 12.5px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.25;
            margin-top: 2px;
            white-space: nowrap;
        }

        .student-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .student-info-table td {
            padding: 2px 0;
            font-size: 10.5px;
        }
        .info-lbl {
            color: #64748b;
            width: 112px;
            font-weight: 500;
            white-space: nowrap;
        }
        .info-val {
            font-weight: 700;
            color: #0f172a;
        }

        /* FEE SUMMARY TABLE */
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            border: 1px solid #475569;
        }
        .fee-table th {
            background: #ffffff;
            border: 1px solid #475569;
            padding: 3.5px 4px;
            font-weight: bold;
            text-align: center;
            font-size: 9.5px;
        }
        .fee-table td {
            border: 1px solid #475569;
            padding: 3px 5px;
        }
        .fee-table .text-right {
            text-align: right;
        }
        .fee-table .text-center {
            text-align: center;
        }
        .fee-table .font-bold {
            font-weight: bold;
        }

        /* MAIN SCHEDULE TABLE */
        .main-ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            border: 1px solid #475569;
            font-size: 10.5px;
        }
        .main-ledger-table th {
            background: #a9beba;
            color: #111827;
            border: 1px solid #475569;
            padding: 4.5px 6px;
            font-weight: bold;
            text-align: left;
            font-size: 10px;
        }
        .main-ledger-table th.th-center { text-align: center; }
        .main-ledger-table th.th-right { text-align: right; }
        .main-ledger-table td {
            border: 1px solid #cbd5e1;
            padding: 3.5px 6px;
            font-size: 10.5px;
        }
        .main-ledger-table td.cell-right { text-align: right; }
        .main-ledger-table td.cell-center { text-align: center; }
        .row-section-header {
            background: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
        }
        .highlight-yellow {
            background-color: #fef08a !important;
            font-weight: bold;
            cursor: pointer;
        }
        .highlight-blue {
            background-color: #bae6fd !important;
            font-weight: 900;
        }

        /* SUMMARY & FOOTER */
        .summary-subtable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10.5px;
        }
        .summary-subtable td {
            padding: 3px 6px;
        }

        .discrepancy-note {
            margin-top: 10px;
            font-size: 10px;
            color: #111827;
        }
        .discrepancy-red {
            color: #dc2626;
            font-weight: bold;
            text-decoration: underline;
        }

        .shukran-bar {
            background: #fef08a;
            color: #111827;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            padding: 4px 0;
            margin: 10px 0 8px;
            border: 1px solid #eab308;
        }

        .legal-footer {
            display: table;
            width: 100%;
            font-size: 9px;
            color: #374151;
            margin-top: 4px;
        }
        .legal-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            line-height: 1.35;
        }
        .legal-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
            line-height: 1.35;
        }

        /* PRINT MEDIA QUERIES (A4 PORTRAIT EXACT SCALING) */
        @media print {
            html,
            body {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .no-print-bar,
            .skeleton,
            .loading-overlay,
            .loading-indicator,
            .toast-stack,
            .amis-loading-overlay {
                display: none !important;
            }

            .soa-viewport {
                overflow: visible !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .soa-page {
                width: 190mm !important;
                min-width: 190mm !important;
                max-width: 190mm !important;
                min-height: auto !important;
                margin: 0 auto !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                transform: none !important;
                zoom: 1 !important;
            }

            table,
            .upper-grid,
            .main-ledger-table,
            .fee-table,
            .student-header-block,
            .discrepancy-note,
            .shukran-bar,
            .legal-footer {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <a href="javascript:history.back()" class="btn-back">← Back to Dashboard</a>
        <button type="button" onclick="window.print()" class="btn-print">
            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print / Save to PDF
        </button>
    </div>

    <div class="soa-viewport">
        <div class="soa-page">
        {{-- TOP SCHOOL HEADER --}}
        <div class="school-header">
            <div class="school-header-cell header-english">
                AL MUNAWWARA ISLAMIC SCHOOL
            </div>
            <div class="school-header-cell header-logo">
                <img src="/images/AMIS_Logo.png" alt="AMIS Logo" onerror="this.src='/images/AMIS_Logo_receipt.jpg'">
            </div>
            <div class="school-header-cell header-arabic">
                <span dir="rtl">المدرسة المنورة الإسلامية</span>
            </div>
        </div>

        {{-- STATEMENT OF ACCOUNT BANNER --}}
        <div class="title-banner">
            STATEMENT OF ACCOUNT SY {{ $soaData['school_year'] }}
        </div>

        {{-- UPPER GRID --}}
        <div class="upper-grid">
            {{-- COLUMN 1: SCHOOL INFO & QURANIC QUOTE --}}
            <div class="upper-left">
                <div><strong>Address:</strong></div>
                <div>Bugac Ma-a Road, Davao City</div>
                <div style="margin-top: 6px;"><strong>Email Add:</strong></div>
                <div>almunawwaraislamicschool@gmail.com</div>

                <div class="ayah-quote">
                    <strong>Sahih International</strong><br>
                    "Whoever does righteousness, whether male or female, while he is a believer - We will surely cause him to live a good life, and We will surely give them their reward [in the Hereafter] according to the best of what they do."
                    <span class="ayah-source">Qur'an 16:97</span>
                </div>
            </div>

            {{-- COLUMN 2: STUDENT DETAILS --}}
            <div class="upper-mid">
                @php
                    $sName = strtoupper($soaData['student_name'] ?? '');
                    $sLen = mb_strlen($sName);
                    $nameSize = $sLen > 32 ? '9.5px' : ($sLen > 25 ? '11px' : ($sLen > 18 ? '12px' : '13px'));
                    $nameSpacing = $sLen > 28 ? '-0.2px' : '0px';
                @endphp
                
                <div class="student-header-block">
                    <div class="student-header-label">NAME OF STUDENT:</div>
                    <div class="student-header-name" style="font-size: {{ $nameSize }}; letter-spacing: {{ $nameSpacing }};">
                        {{ $sName }}
                    </div>
                </div>

                <table class="student-info-table">
                    <tr>
                        <td class="info-lbl">Address:</td>
                        <td class="info-val">{{ strtoupper($soaData['address']) }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Email:</td>
                        <td class="info-val" style="font-size:10px;">{{ $soaData['email'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">LRN:</td>
                        <td class="info-val">{{ $soaData['lrn'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Category:</td>
                        <td class="info-val">{{ $soaData['category'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Grade Level:</td>
                        <td class="info-val">{{ $soaData['grade_level'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Discount Privilege:</td>
                        <td class="info-val">{{ $soaData['discount_privilege'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Discount Status:</td>
                        <td class="info-val">{{ $soaData['discount_status'] }}</td>
                    </tr>
                </table>
            </div>

            {{-- COLUMN 3: FEE BREAKDOWN TABLE --}}
            <div class="upper-right">
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th rowspan="2">DESCRIPTION</th>
                            <th rowspan="2">AMOUNT</th>
                            <th colspan="2">DISCOUNT</th>
                            <th rowspan="2">NET</th>
                        </tr>
                        <tr>
                            <th>%</th>
                            <th>AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tuition Fees</td>
                            <td class="text-right">{{ number_format($soaData['tuition_fee'], 2) }}</td>
                            <td class="text-center">{{ $soaData['discount_privilege'] }}</td>
                            <td class="text-center">{{ $soaData['discount_amount'] > 0 ? number_format($soaData['discount_amount'], 2) : '-' }}</td>
                            <td class="text-right">{{ number_format($soaData['tuition_fee'] - $soaData['discount_amount'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Miscellaneous</td>
                            <td class="text-right">{{ number_format($soaData['misc_fee'], 2) }}</td>
                            <td></td>
                            <td></td>
                            <td class="text-right">{{ number_format($soaData['misc_fee'], 2) }}</td>
                        </tr>
                        <tr class="font-bold">
                            <td>Total Fees</td>
                            <td class="text-right">{{ number_format($soaData['total_fees'], 2) }}</td>
                            <td></td>
                            <td></td>
                            <td class="text-right">{{ number_format($soaData['total_fees'], 2) }}</td>
                        </tr>
                        <tr class="font-bold">
                            <td>Final Fees</td>
                            <td></td>
                            <td></td>
                            <td class="text-center">-</td>
                            <td class="text-right">{{ number_format($soaData['final_fees'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MAIN STATEMENT & PAYMENT LEDGER TABLE --}}
        <table class="main-ledger-table">
            <thead>
                <tr>
                    <th style="width: 26%;">Description</th>
                    <th style="width: 14%;">Month</th>
                    <th class="th-right" style="width: 12%;">Amount</th>
                    <th class="th-center" style="width: 12%;">Date</th>
                    <th class="th-right" style="width: 12%;">Amount Paid</th>
                    <th class="th-center" style="width: 10%;">Account</th>
                    <th class="th-right" style="width: 14%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $runningBalance = (float) $soaData['final_fees'];
                    $runningBalance -= (float) $soaData['enrollment_paid'];
                @endphp
                <tr>
                    <td>Paid Enrollment Fee</td>
                    <td></td>
                    <td class="cell-right"></td>
                    <td class="cell-center">{{ $soaData['enrollment_date'] }}</td>
                    <td class="cell-right highlight-yellow" title="Payment received and applied to this account">{{ number_format($soaData['enrollment_paid'], 2) }}</td>
                    <td class="cell-center">{{ $soaData['enrollment_account'] }}</td>
                    <td class="cell-right">{{ number_format($runningBalance, 2) }}</td>
                </tr>
                @php
                    $runningBalance += (float) $soaData['books_fee'];
                @endphp
                <tr>
                    <td>Books and programs</td>
                    <td></td>
                    <td class="cell-right">{{ number_format($soaData['books_fee'], 2) }}</td>
                    <td class="cell-center"></td>
                    <td class="cell-right"></td>
                    <td class="cell-center"></td>
                    <td class="cell-right">{{ number_format($runningBalance, 2) }}</td>
                </tr>
                @php
                    $runningBalance -= (float) $soaData['books_paid'];
                @endphp
                <tr>
                    <td>Paid Books</td>
                    <td></td>
                    <td class="cell-right"></td>
                    <td class="cell-center">{{ $soaData['books_date'] }}</td>
                    <td class="cell-right highlight-yellow" title="Payment received and applied to this account">{{ number_format($soaData['books_paid'], 2) }}</td>
                    <td class="cell-center">{{ $soaData['books_account'] }}</td>
                    <td class="cell-right">{{ number_format($runningBalance, 2) }}</td>
                </tr>

                {{-- REQUIRED PAYMENT MONTHLY SECTION --}}
                <tr class="row-section-header">
                    <td colspan="7">Required Payment Monthly</td>
                </tr>

                @php
                    $sched = collect($soaData['monthly_schedule']);
                @endphp

                <tr class="row-section-header" style="background:#ffffff; font-size:10px;">
                    <td colspan="7">Year: 2026</td>
                </tr>

                @foreach (['July', 'August', 'September', 'October', 'November', 'December'] as $monthName)
                    @php
                        $mRow = $sched->first(fn($m) => str_contains(strtoupper($m->month ?? ''), strtoupper($monthName)));
                        $mFee = (float) ($mRow->fee ?? ($mRow->original ?? $soaData['monthly_rate']));
                        $mPaid = (float) ($mRow->paid ?? ($mRow->verified ?? 0));
                        $isPaidMonth = $mPaid > 0.01;
                        if ($isPaidMonth) {
                            $runningBalance -= $mPaid;
                        }
                    @endphp
                    <tr>
                        <td></td>
                        <td>{{ $monthName }}</td>
                        <td class="cell-right {{ ($monthName === 'July' && ! $isPaidMonth) ? 'highlight-yellow' : '' }}" @if($monthName === 'July' && ! $isPaidMonth) title="Required payment for this month" @endif>{{ number_format($mFee, 2) }}</td>
                        <td class="cell-center">{{ $isPaidMonth ? ($monthName === 'July' ? '3-Jul-26' : ($monthName === 'August' ? '15-Aug-26' : '15-Sep-26')) : '' }}</td>
                        <td class="cell-right {{ $isPaidMonth ? 'highlight-yellow' : '' }}" @if($isPaidMonth) title="Payment received and applied to this account" @endif>
                            {{ $isPaidMonth ? number_format($mPaid, 2) : '-' }}
                        </td>
                        <td class="cell-center">{{ $isPaidMonth ? '10539' : '' }}</td>
                        <td class="cell-right">{{ $isPaidMonth ? number_format($runningBalance, 2) : '' }}</td>
                    </tr>
                @endforeach

                <tr class="row-section-header" style="background:#ffffff; font-size:10px;">
                    <td colspan="7">Year: 2027</td>
                </tr>

                @foreach (['January', 'February', 'March'] as $monthName)
                    @php
                        $mRow = $sched->first(fn($m) => str_contains(strtoupper($m->month ?? ''), strtoupper($monthName)));
                        $mFee = (float) ($mRow->fee ?? ($mRow->original ?? $soaData['monthly_rate']));
                        $mPaid = (float) ($mRow->paid ?? ($mRow->verified ?? 0));
                        $isPaidMonth = $mPaid > 0.01;
                        if ($isPaidMonth) {
                            $runningBalance -= $mPaid;
                        }
                    @endphp
                    <tr>
                        <td></td>
                        <td>{{ $monthName }}</td>
                        <td class="cell-right">{{ number_format($mFee, 2) }}</td>
                        <td class="cell-center">{{ $isPaidMonth ? '15-Jan-27' : '' }}</td>
                        <td class="cell-right {{ $isPaidMonth ? 'highlight-yellow' : '' }}" @if($isPaidMonth) title="Payment received and applied to this account" @endif>
                            {{ $isPaidMonth ? number_format($mPaid, 2) : '-' }}
                        </td>
                        <td class="cell-center">{{ $isPaidMonth ? '10539' : '' }}</td>
                        <td class="cell-right">{{ $isPaidMonth ? number_format($runningBalance, 2) : '' }}</td>
                    </tr>
                @endforeach

                {{-- TO BE PAID / PAID LABEL ROW --}}
                <tr style="border-top: 2px solid #475569;">
                    <td colspan="4" style="border:none;"></td>
                    <td class="cell-center" style="font-weight:bold; font-size:10px; border: 1px solid #475569;">TO BE PAID</td>
                    <td class="cell-center highlight-yellow" style="font-weight:bold; font-size:10px; border: 1px solid #475569;">PAID</td>
                    <td style="border:none;"></td>
                </tr>
                <tr>
                    <td colspan="6" style="font-weight:bold; border-right:none;">Total Amount to pay</td>
                    <td class="cell-right highlight-blue" style="font-size:11px;">{{ number_format($soaData['total_remaining'] > 0 ? $soaData['total_remaining'] : $runningBalance, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="font-weight:bold; border-right:none;">Due Monthly Payment (9 Months)</td>
                    <td class="cell-right highlight-yellow" style="border-left:none;" title="Monthly amount due for this student">{{ number_format($soaData['monthly_rate'], 2) }}</td>
                    <td colspan="4" style="border-left:none;"></td>
                </tr>
            </tbody>
        </table>

        {{-- DISCREPANCY NOTE --}}
        <div class="discrepancy-note">
            Note: Any discrepancies please inform the office. &nbsp;
            <span class="discrepancy-red">ANY DISCREPANCY PLEASE INFORM, WE WILL CORRECT</span>
        </div>

        {{-- SHUKRAN BAR --}}
        <div class="shukran-bar">
            Shukran. JazakAllahu khayran
        </div>

        {{-- LEGAL FOOTER --}}
        <div class="legal-footer">
            <div class="legal-left">
                Mayor's Permit No. B-86418-8<br>
                SEC Registration No. CN200826457
            </div>
            <div class="legal-right">
                DepED Recognition No. R-XI-019, s. 2016<br>
                DepED Recognition No. R-XI-005, s. 2016
            </div>
        </div>
    </div>
</div>

</body>
</html>
