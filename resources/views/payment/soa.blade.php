<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOA – {{ $student->student_number ?? 'N/A' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
        }

        .page {
            width: min(100%, 800px);
            margin: 0 auto;
            padding: 28px 36px 36px;
        }

        .soa-table-scroll {
            width: 100%;
            overflow-x: auto;
            overscroll-behavior-inline: contain;
            -webkit-overflow-scrolling: touch;
        }

        /* ── TOP BORDER LINES ── */
        .top-lines { border-top: 3px solid #111; border-bottom: 1px solid #111; padding: 2px 0; margin-bottom: 2px; }
        .top-lines2 { border-bottom: 3px solid #111; margin-bottom: 14px; }

        /* ── HEADER ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2px;
            padding: 6px 0;
        }
        .school-name-en {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 0.5px;
            color: #111;
        }
        .logo img { width: 64px; height: 64px; object-fit: contain; }
        .school-name-ar {
            font-size: 16px;
            font-weight: 700;
            color: #2d7d32;
            direction: rtl;
            font-family: 'Arial', sans-serif;
        }

        /* ── SOA TITLE ── */
        .soa-title {
            text-align: center;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 1px;
            padding: 6px 0 10px;
            border-bottom: 1px solid #ccc;
            margin-bottom: 14px;
        }

        /* ── INFO SECTION ── */
        .info-section {
            display: flex;
            gap: 0;
            margin-bottom: 18px;
        }
        .info-left {
            flex: 1;
            padding-right: 20px;
            border-right: 1px solid #ccc;
        }
        .info-right { flex: 1.4; padding-left: 20px; }

        .info-left p { margin-bottom: 3px; font-size: 10.5px; line-height: 1.5; }
        .info-left .label { font-weight: 700; }
        .info-left .verse-block {
            margin-top: 10px;
            font-size: 9.5px;
            color: #333;
            line-height: 1.6;
        }
        .info-left .verse-source { color: #2d7d32; font-style: italic; font-weight: 700; margin-bottom: 4px; }
        .info-left .verse-ref { color: #2d7d32; font-style: italic; font-weight: 700; text-align: right; margin-top: 4px; }

        .info-right table { width: 100%; border-collapse: collapse; }
        .info-right td { padding: 2px 4px; font-size: 10.5px; vertical-align: top; }
        .info-right td:first-child { font-weight: 700; white-space: nowrap; width: 130px; }

        /* ── FEE BREAKDOWN TABLE ── */
        .fee-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .fee-table th {
            background: #c8d8c8;
            border: 1px solid #666;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 700;
            text-align: center;
        }
        .fee-table td {
            border: 1px solid #aaa;
            padding: 3px 8px;
            font-size: 10.5px;
        }
        .fee-table td.desc { font-weight: 600; }
        .fee-table td.num { text-align: right; }
        .fee-table tr.total-row td { font-weight: 700; background: #f0f4f0; }
        .fee-table tr.final-row td { font-weight: 900; background: #e0ece0; }

        /* ── PAYMENT SCHEDULE TABLE ── */
        .schedule-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .schedule-table th {
            background: #c8d8c8;
            border: 1px solid #666;
            padding: 4px 6px;
            font-size: 10px;
            font-weight: 700;
            text-align: left;
        }
        .schedule-table th.center { text-align: center; }
        .schedule-table td {
            border: 1px solid #ccc;
            padding: 3px 6px;
            font-size: 10.5px;
        }
        .schedule-table td.num { text-align: right; }
        .schedule-table td.center { text-align: center; }
        .schedule-table tr.section-label td {
            font-weight: 700;
            background: #f5f5f5;
            border-top: 1.5px solid #999;
        }
        .schedule-table tr.year-label td {
            font-weight: 700;
            padding-left: 8px;
            background: #fafafa;
        }
        .schedule-table tr.summary-row td { font-weight: 700; background: #f0f4f0; }
        .schedule-table tr.totals-row td { font-weight: 900; background: #e0ece0; font-size: 11px; }

        .paid-highlight { background: #ffff00; font-weight: 900; }
        .balance-col { font-weight: 700; }

        /* ── FOOTER ── */
        .footer-note {
            font-size: 10px;
            color: #333;
            margin-top: 14px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .footer-note .discrepancy {
            color: #cc0000;
            font-weight: 700;
            text-transform: uppercase;
        }
        .footer-thanks {
            text-align: center;
            margin-top: 10px;
        }
        .footer-thanks .yellow-bar {
            background: #ffff00;
            display: inline-block;
            padding: 3px 60px;
            margin-bottom: 4px;
        }
        .footer-thanks .shukran {
            font-size: 12px;
            font-weight: 700;
        }

        /* ── PRINT BUTTON ── */
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #047857;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 999;
        }
        .print-btn:hover { background: #065f46; }

        @media screen and (max-width: 700px) {
            body {
                background: #f3f7f5;
                font-size: 12px;
                overflow-x: hidden;
            }

            .page {
                width: 100%;
                padding: 78px 14px 28px;
                background: #fff;
            }

            .print-btn {
                top: 12px;
                right: 12px;
                left: 12px;
                width: calc(100% - 24px);
                min-height: 48px;
            }

            .header {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px 12px;
                align-items: center;
            }

            .logo {
                grid-column: 2;
                grid-row: 1 / span 2;
            }

            .logo img {
                width: 52px;
                height: 52px;
            }

            .school-name-en,
            .school-name-ar {
                font-size: 13px;
                line-height: 1.25;
                overflow-wrap: anywhere;
            }

            .school-name-ar {
                grid-column: 1;
                text-align: left;
            }

            .soa-title {
                font-size: 12px;
                line-height: 1.4;
            }

            .info-section {
                display: grid;
                gap: 16px;
            }

            .info-left,
            .info-right {
                min-width: 0;
                padding: 0;
                border: 0;
            }

            .info-left {
                padding-bottom: 14px;
                border-bottom: 1px solid #ccc;
            }

            .info-right td:first-child {
                width: 112px;
                white-space: normal;
            }

            .fee-table,
            .schedule-table {
                min-width: 660px;
            }

            .footer-note {
                display: grid;
                gap: 8px;
            }

            .footer-thanks .yellow-bar {
                padding-inline: 28px;
            }
        }

        @media print {
            .print-btn { display: none !important; }
            body { margin: 0; }
            .page { width: 800px; padding: 12px 20px; }
            .soa-table-scroll { overflow: visible; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>

<div class="page">

    {{-- TOP BORDER --}}
    <div class="top-lines"></div>
    <div class="top-lines2"></div>

    {{-- HEADER --}}
    <div class="header">
        <div class="school-name-en">AL MUNAWWARA ISLAMIC SCHOOL</div>
        <div class="logo">
            <img src="{{ asset('images/MA_Logo.png') }}" alt="AMIS Logo">
        </div>
        <div class="school-name-ar">المدرسة المنورة الإسلامية</div>
    </div>

    <div class="top-lines"></div>
    <div class="top-lines2"></div>

    {{-- SOA TITLE --}}
    <div class="soa-title">STATEMENT OF ACCOUNT SY {{ $account->school_year }}</div>

    {{-- INFO SECTION --}}
    @php
        $applicant  = $student->applicant;
        $schoolYear = $account->school_year;
        $startYear  = (int) explode('-', $schoolYear)[0];
        $tuition    = (float) $account->tuition_fee;
        $misc       = (float) $account->miscellaneous_fee;
        $books      = (float) $account->books_fee;
        $discPct    = (float) $account->discount_percentage;
        $discAmt    = (float) $account->discount_amount;
        $discType   = $account->discount_type;
        $netTuition = $tuition - $discAmt;
        $grossTotal = $netTuition + $misc + $books;
        $enrollPaid = (float) $account->enrollment_fee_paid;
        $totalBalance = (float) $account->total_balance;
        $monthly    = (float) $account->monthly_tuition;
        $fullName   = $applicant ? trim($applicant->first_name . ' ' . ($applicant->middle_name ? $applicant->middle_name . ' ' : '') . $applicant->last_name) : ($student->user->name ?? 'N/A');
        $address    = $applicant ? trim(($applicant->street_address ?? '') . ', ' . ($applicant->city ?? '') . ', ' . ($applicant->country ?? '')) : '—';
    @endphp

    <div class="info-section">
        <div class="info-left">
            <p><span class="label">Address:</span></p>
            <p>Bugac Ma-a Road, Davao City</p>
            <p style="margin-top:6px;"><span class="label">Email Add:</span></p>
            <p>almunawwaraislamicschool@gmail.com</p>

            <div class="verse-block">
                <p class="verse-source">Sahih International</p>
                <p>"Whoever does righteousness, whether male or female, while he is a believer - We will surely cause him to live a good life, and We will surely give them their reward [in the Hereafter] according to the best of what they do."</p>
                <p class="verse-ref">Qur'an 16:97</p>
            </div>
        </div>

        <div class="info-right">
            <table>
                <tr><td>Name of Student</td><td>{{ strtoupper($fullName) }}</td></tr>
                <tr><td>Address</td><td>{{ $address }}</td></tr>
                <tr><td>IBN</td><td>{{ $student->student_number ?? '—' }}</td></tr>
                <tr><td>Category</td><td>{{ $applicant->learning_mode ?? '—' }}</td></tr>
                <tr><td>Grade Level</td><td>{{ $student->grade_level }}</td></tr>
                @if($discPct > 0)
                <tr><td>Discount Privilege</td><td>{{ number_format($discPct, 0) }}%</td></tr>
                <tr><td>Discount Status</td><td>{{ $account->sibling_order ? \Illuminate\Support\Number::ordinal($account->sibling_order) . ' child' : ucfirst($discType ?? 'Sibling') }}</td></tr>
                @endif
            </table>

            {{-- Fee Breakdown --}}
            <div class="soa-table-scroll" style="margin-top: 12px;">
            <table class="fee-table">
                <thead>
                    <tr>
                        <th style="text-align:left;">DESCRIPTION</th>
                        <th>AMOUNT</th>
                        <th colspan="2">DISCOUNT</th>
                        <th>NET</th>
                    </tr>
                    <tr>
                        <th></th><th></th>
                        <th>%</th><th>AMOUNT</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="desc">Tuition Fees</td>
                        <td class="num">{{ number_format($tuition, 2) }}</td>
                        <td class="num">{{ $discPct > 0 ? number_format($discPct, 0).'%' : '-' }}</td>
                        <td class="num">{{ $discAmt > 0 ? number_format($discAmt, 2) : '-' }}</td>
                        <td class="num">{{ number_format($netTuition, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="desc">Miscellaneous</td>
                        <td class="num">{{ number_format($misc, 2) }}</td>
                        <td class="num">-</td>
                        <td class="num">-</td>
                        <td class="num">{{ number_format($misc, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td class="desc">Total Fees</td>
                        <td class="num">{{ number_format($tuition + $misc, 2) }}</td>
                        <td></td><td></td>
                        <td class="num">{{ number_format($netTuition + $misc, 2) }}</td>
                    </tr>
                    <tr class="final-row">
                        <td class="desc">Final Fees</td>
                        <td></td><td></td>
                        <td class="num">-</td>
                        <td class="num">{{ number_format($netTuition + $misc, 2) }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    {{-- PAYMENT SCHEDULE TABLE --}}
    @php
        $billings = $account->monthlyBillings->sortBy('month_number');
        $year2026months = $billings->filter(fn($b) => $b->due_date->year == $startYear);
        $year2027months = $billings->filter(fn($b) => $b->due_date->year == $startYear + 1);
    @endphp

    <div class="soa-table-scroll">
    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width:220px;">Description</th>
                <th class="center" style="width:80px;">Month</th>
                <th class="center" style="width:80px;">Amount</th>
                <th class="center" style="width:75px;">Date</th>
                <th class="center" style="width:80px;">Amount Paid</th>
                <th class="center" style="width:65px;">OR</th>
                <th class="center" style="width:80px;">Balance</th>
            </tr>
        </thead>
        <tbody>
            {{-- Enrollment Fee Row --}}
            @php
                $payments = $account->payments ?? collect();
                $verifiedPayments = $student->account ? $student->account->payments()->where('status','verified')->orderBy('paid_at')->get() : collect();
                $runningBalance = $totalBalance;
            @endphp
            <tr>
                <td>Paid Enrollment Fee</td>
                <td></td>
                <td></td>
                <td class="center">—</td>
                <td class="center paid-highlight">{{ number_format($enrollPaid, 2) }}</td>
                <td class="center">—</td>
                <td class="num balance-col">{{ number_format($totalBalance, 2) }}</td>
            </tr>

            {{-- Books Row --}}
            <tr>
                <td>Books LMS</td>
                <td></td>
                <td class="num">{{ number_format($books, 2) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="num balance-col">{{ number_format($totalBalance + $books, 2) }}</td>
            </tr>

            {{-- Required Monthly Payment header --}}
            <tr class="section-label">
                <td colspan="7">Required Payment Monthly</td>
            </tr>

            {{-- Year 2026 months --}}
            @if($year2026months->count())
            <tr class="year-label">
                <td>Year: {{ $startYear }}</td>
                @php $first2026 = $year2026months->first(); @endphp
                <td class="center">{{ mb_strtoupper($first2026->month_name) }}</td>
                <td class="num">{{ number_format($first2026->amount_due, 2) }}</td>
                <td class="center">{{ $first2026->paid_at ? strtoupper($first2026->paid_at->format('d-M-y')) : '' }}</td>
                <td class="center {{ $first2026->status === 'paid' ? 'paid-highlight' : '' }}">{{ $first2026->status === 'paid' ? number_format($first2026->amount_due, 2) : '' }}</td>
                <td class="center">—</td>
                <td class="num balance-col">{{ $first2026->status === 'paid' ? '—' : '-' }}</td>
            </tr>
            @foreach($year2026months->skip(1) as $billing)
            <tr>
                <td></td>
                <td class="center">{{ mb_strtoupper($billing->month_name) }}</td>
                <td class="num">{{ number_format($billing->amount_due, 2) }}</td>
                <td class="center">{{ $billing->paid_at ? strtoupper($billing->paid_at->format('d-M-y')) : '' }}</td>
                <td class="center {{ $billing->status === 'paid' ? 'paid-highlight' : '' }}">{{ $billing->status === 'paid' ? number_format($billing->amount_due, 2) : '' }}</td>
                <td class="center">—</td>
                <td class="num balance-col">{{ $billing->status === 'paid' ? '—' : '-' }}</td>
            </tr>
            @endforeach
            @endif

            {{-- Year 2027 months --}}
            @if($year2027months->count())
            <tr class="year-label">
                <td>Year: {{ $startYear + 1 }}</td>
                @php $first2027 = $year2027months->first(); @endphp
                <td class="center">{{ mb_strtoupper($first2027->month_name) }}</td>
                <td class="num">{{ number_format($first2027->amount_due, 2) }}</td>
                <td class="center">{{ $first2027->paid_at ? strtoupper($first2027->paid_at->format('d-M-y')) : '' }}</td>
                <td class="center {{ $first2027->status === 'paid' ? 'paid-highlight' : '' }}">{{ $first2027->status === 'paid' ? number_format($first2027->amount_due, 2) : '' }}</td>
                <td class="center">—</td>
                <td class="num balance-col">{{ $first2027->status === 'paid' ? '—' : '-' }}</td>
            </tr>
            @foreach($year2027months->skip(1) as $billing)
            <tr>
                <td></td>
                <td class="center">{{ mb_strtoupper($billing->month_name) }}</td>
                <td class="num">{{ number_format($billing->amount_due, 2) }}</td>
                <td class="center">{{ $billing->paid_at ? strtoupper($billing->paid_at->format('d-M-y')) : '' }}</td>
                <td class="center {{ $billing->status === 'paid' ? 'paid-highlight' : '' }}">{{ $billing->status === 'paid' ? number_format($billing->amount_due, 2) : '' }}</td>
                <td class="center">—</td>
                <td class="num balance-col">{{ $billing->status === 'paid' ? '—' : '-' }}</td>
            </tr>
            @endforeach
            @endif

            {{-- TO BE PAID / PAID header --}}
            <tr class="summary-row">
                <td colspan="4"></td>
                <td class="center" style="background:#c8d8c8;">TO BE PAID</td>
                <td class="center" style="background:#c8d8c8;">PAID</td>
                <td></td>
            </tr>

            {{-- Totals --}}
            <tr class="totals-row">
                <td>Total Amount to pay</td>
                <td></td><td></td><td></td>
                <td></td>
                <td></td>
                <td class="num">{{ number_format($totalBalance, 2) }}</td>
            </tr>
            <tr class="totals-row">
                <td>Due Monthly Payment ({{ $billings->count() }} Months)</td>
                <td></td>
                <td class="num paid-highlight">{{ number_format($monthly, 2) }}</td>
                <td colspan="4"></td>
            </tr>
        </tbody>
    </table>
    </div>

    {{-- FOOTER --}}
    <div class="footer-note">
        <span>Note: Any discrepancies please inform the office.</span>
        <span class="discrepancy">ANY DISCREPANCY PLEASE INFORM, WE WILL CORRECT</span>
    </div>

    <div class="footer-thanks">
        <div class="yellow-bar">&nbsp;</div><br>
        <span class="shukran">Shukran. JazakAllahu khayran</span>
    </div>

</div>
</body>
</html>
