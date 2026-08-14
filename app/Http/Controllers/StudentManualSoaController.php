<?php

namespace App\Http\Controllers;

use App\Models\StudentManualSoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentManualSoaController extends Controller
{
    public function view(Request $request, StudentManualSoa $soa)
    {
        $user = $request->user();
        $isAuthorized = strcasecmp($soa->family_email, (string) $user->email) === 0
            || in_array($user->role, ['admin', 'staff', 'finance'], true)
            || $user->students()->where('students.student_number', $soa->student_identifier)->exists()
            || $user->students()->where('students.id', $soa->student_identifier)->exists();

        abort_unless($isAuthorized, 403, 'Unauthorized to access this Statement of Account.');
        abort_unless(Storage::disk('local')->exists($soa->file_path), 404, 'Statement of Account document not found on storage.');

        return Storage::disk('local')->response(
            $soa->file_path,
            $soa->original_filename,
            [
                'Content-Type' => $soa->mime_type ?? 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$soa->original_filename.'"',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }

    public function download(Request $request, StudentManualSoa $soa)
    {
        $user = $request->user();
        $isAuthorized = strcasecmp($soa->family_email, (string) $user->email) === 0
            || in_array($user->role, ['admin', 'staff', 'finance'], true)
            || $user->students()->where('students.student_number', $soa->student_identifier)->exists()
            || $user->students()->where('students.id', $soa->student_identifier)->exists();

        abort_unless($isAuthorized, 403, 'Unauthorized to access this Statement of Account.');
        abort_unless(Storage::disk('local')->exists($soa->file_path), 404, 'Statement of Account document not found on storage.');

        return Storage::disk('local')->download($soa->file_path, $soa->original_filename);
    }

    public function officialStudentSoa(Request $request, string $studentIdentifier)
    {
        $user = $request->user();
        $cleanId = Str::upper(trim($studentIdentifier));

        // 1. Check Demo Children
        $demoService = app(\App\Services\DemoPaymentScheduleService::class);
        $dbDemoChildren = $user ? $user->paymentDemoChildren()->orderBy('id')->get() : collect();
        if ($dbDemoChildren->isNotEmpty()) {
            $demoChildrenAll = $dbDemoChildren;
        } else {
            $demoChildrenAll = collect([
                1 => (object)[
                    'id' => 1,
                    'user_id' => $user?->id,
                    'demo_student_number' => 'AFPS-DEMO-2026-001-2',
                    'display_name' => 'Ahmad Z. Lingasa',
                    'first_name' => 'AHMAD',
                    'last_name' => 'LINGASA',
                    'gender' => 'Male',
                    'grade_level' => 'Grade 1',
                    'school_year' => '2026-2027',
                    'tuition_fee' => 36500.00,
                    'miscellaneous_fee' => 1900.00,
                    'books_fee' => 5900.00,
                    'discount_percentage' => 15.0,
                    'discount_amount' => 5475.00,
                    'total_balance' => 34430.00,
                    'remaining_balance' => 34430.00,
                    'enrollment_fee_paid' => 3000.00,
                    'installment_months' => 9,
                    'monthly_tuition' => 3803.33,
                ],
                2 => (object)[
                    'id' => 2,
                    'user_id' => $user?->id,
                    'demo_student_number' => 'AFPS-DEMO-2026-002-2',
                    'display_name' => 'Maryam Z. Lingasa',
                    'first_name' => 'MARYAM',
                    'last_name' => 'LINGASA',
                    'gender' => 'Female',
                    'grade_level' => 'Grade 3',
                    'school_year' => '2026-2027',
                    'tuition_fee' => 36500.00,
                    'miscellaneous_fee' => 1900.00,
                    'books_fee' => 5900.00,
                    'discount_percentage' => 15.0,
                    'discount_amount' => 5475.00,
                    'total_balance' => 35635.00,
                    'remaining_balance' => 35635.00,
                    'enrollment_fee_paid' => 3000.00,
                    'installment_months' => 9,
                    'monthly_tuition' => 3926.11,
                ],
                3 => (object)[
                    'id' => 3,
                    'user_id' => $user?->id,
                    'demo_student_number' => 'AFPS-DEMO-2026-003-2',
                    'display_name' => 'Yusuf Z. Lingasa',
                    'first_name' => 'YUSUF',
                    'last_name' => 'LINGASA',
                    'gender' => 'Male',
                    'grade_level' => 'Grade 5',
                    'school_year' => '2026-2027',
                    'tuition_fee' => 36500.00,
                    'miscellaneous_fee' => 1900.00,
                    'books_fee' => 5900.00,
                    'discount_percentage' => 15.0,
                    'discount_amount' => 5475.00,
                    'total_balance' => 36995.00,
                    'remaining_balance' => 36995.00,
                    'enrollment_fee_paid' => 3000.00,
                    'installment_months' => 9,
                    'monthly_tuition' => 4077.22,
                ],
            ]);
        }

        preg_match('/(?:^|[^0-9])0*(1|2|3|4|5|6|7|8|9)(?:[^0-9]|$)/', preg_replace('/202[0-9]/', '', $cleanId), $mId);
        $targetSeq = $mId[1] ?? null;

        $targetDemo = $demoChildrenAll->first(function ($child, $seq) use ($cleanId, $targetSeq) {
            return Str::upper((string) ($child->demo_student_number ?? '')) === $cleanId
                || (string) $child->id === $cleanId
                || ($targetSeq !== null && (string) $seq === (string) $targetSeq)
                || Str::contains($cleanId, Str::upper($child->first_name ?? ''))
                || Str::contains(Str::upper($child->display_name ?? ''), $cleanId);
        });

        if ($targetDemo) {
            $breakdown = $demoService->installmentsFor($targetDemo, $demoChildrenAll, $user);
            $scheduleCollection = collect($breakdown)->map(function ($item) {
                return (object)[
                    'month' => $item['month'] ?? '',
                    'fee' => (float) ($item['original'] ?? 0),
                    'paid' => (float) ($item['verified'] ?? 0),
                    'remaining' => (float) ($item['remaining'] ?? 0),
                    'status' => $item['status'] ?? 'UPCOMING',
                ];
            });

            $tuition = (float) $targetDemo->tuition_fee;
            $misc = (float) $targetDemo->miscellaneous_fee;
            $totalFees = $tuition + $misc;
            $discountPercent = (float) $targetDemo->discount_percentage;
            $discountAmount = (float) $targetDemo->discount_amount;
            $finalFees = $totalFees - $discountAmount;
            $remainingBalance = (float) $scheduleCollection->sum('remaining');

            $soaData = [
                'student_name' => mb_strtoupper((string) $targetDemo->display_name),
                'address' => 'DAVAO CITY',
                'email' => $user?->email ?? 'zhairel.lingasa@gmail.com',
                'lrn' => '123456789012',
                'category' => (str_contains($targetDemo->grade_level, 'Grade 7') || str_contains($targetDemo->grade_level, 'Grade 8') || str_contains($targetDemo->grade_level, 'Grade 9') || str_contains($targetDemo->grade_level, 'Grade 10')) ? 'Junior High' : 'Elementary',
                'grade_level' => $targetDemo->grade_level,
                'discount_privilege' => $discountPercent > 0 ? "{$discountPercent}%" : '0%',
                'discount_status' => $discountPercent > 0 ? 'Active (Sibling Discount)' : 'N/A',
                'tuition_fee' => $tuition,
                'misc_fee' => $misc,
                'total_fees' => $totalFees,
                'discount_amount' => $discountAmount,
                'final_fees' => $finalFees,
                'enrollment_paid' => (float) $targetDemo->enrollment_fee_paid,
                'enrollment_date' => '5-May-26',
                'enrollment_account' => '10539',
                'books_fee' => (float) $targetDemo->books_fee,
                'books_paid' => 1000.00,
                'books_date' => '5-May-26',
                'books_account' => '10539',
                'monthly_schedule' => $scheduleCollection,
                'monthly_rate' => (float) $targetDemo->monthly_tuition,
                'total_remaining' => $remainingBalance,
                'school_year' => $targetDemo->school_year ?? '2026-2027',
            ];

            return view('payment.official-soa', compact('soaData'));
        }

        // 2. Check Database Student Record
        if (Schema::hasTable('students')) {
            $student = \App\Models\Student::with(['account.monthlyBillings.payments', 'applicant'])
                ->where('student_number', $studentIdentifier)
                ->orWhere('id', $studentIdentifier)
                ->first();

            if ($student) {
                if ($user && $student->user_id && $student->user_id !== $user->id) {
                    abort(403, 'Unauthorized to view this Statement of Account.');
                }

                $account = $student->account;
                $applicant = $student->applicant;
                $studentName = mb_strtoupper($applicant?->full_name ?? ($student->first_name.' '.$student->last_name));
                $tuition = (float) ($account?->tuition_fee ?? 0);
                $misc = (float) ($account?->miscellaneous_fee ?? 0);
                $totalFees = (float) ($account?->total_balance ?? ($tuition + $misc));
                $discountPercent = (float) ($account?->discount_percentage ?? 0);
                $discountAmount = (float) ($account?->discount_amount ?? 0);
                $finalFees = max(0, $totalFees - $discountAmount);

                $monthlySchedule = $account?->monthlyBillings
                    ?->filter(fn ($b) => (int) $b->month_number > 0)
                    ->sortBy(fn ($b) => $b->due_date?->timestamp ?? $b->month_number)
                    ->map(function ($billing) {
                        $original = (float) $billing->amount_due;
                        $paid = $billing->status === 'paid' ? $original : min($original, (float) $billing->payments->where('status', 'verified')->sum('amount'));
                        return (object)[
                            'month' => $billing->due_date ? strtoupper($billing->due_date->format('F Y')) : strtoupper((string) $billing->month_name),
                            'fee' => $original,
                            'paid' => $paid,
                            'remaining' => max(0, $original - $paid),
                            'status' => $billing->status,
                        ];
                    })->values() ?? collect();

                $soaData = [
                    'student_name' => $studentName,
                    'address' => strtoupper($applicant?->address ?? 'DAVAO CITY'),
                    'email' => $user?->email ?? $applicant?->email ?? '',
                    'lrn' => $applicant?->lrn ?? '123456789012',
                    'category' => (str_contains($student->grade_level ?? '', 'Grade 7') || str_contains($student->grade_level ?? '', 'Grade 8') || str_contains($student->grade_level ?? '', 'Grade 9') || str_contains($student->grade_level ?? '', 'Grade 10')) ? 'Junior High' : 'Elementary',
                    'grade_level' => $student->grade_level ?? 'Grade 1',
                    'discount_privilege' => $discountPercent > 0 ? "{$discountPercent}%" : '0%',
                    'discount_status' => $discountPercent > 0 ? 'Active (Sibling Discount)' : 'N/A',
                    'tuition_fee' => $tuition,
                    'misc_fee' => $misc,
                    'total_fees' => $totalFees,
                    'discount_amount' => $discountAmount,
                    'final_fees' => $finalFees,
                    'enrollment_paid' => (float) ($account?->enrollment_fee_paid ?? 3000.00),
                    'enrollment_date' => '5-May-26',
                    'enrollment_account' => '10539',
                    'books_fee' => (float) ($account?->books_fee ?? 5900.00),
                    'books_paid' => 1000.00,
                    'books_date' => '5-May-26',
                    'books_account' => '10539',
                    'monthly_schedule' => $monthlySchedule,
                    'monthly_rate' => (float) ($account?->monthly_tuition ?? ($monthlySchedule->first()?->fee ?? 0)),
                    'total_remaining' => (float) ($account?->remaining_balance ?? $monthlySchedule->sum('remaining')),
                    'school_year' => $student->school_year ?? '2026-2027',
                ];

                return view('payment.official-soa', compact('soaData'));
            }
        }

        abort(404, 'Student account not found.');
    }
}
