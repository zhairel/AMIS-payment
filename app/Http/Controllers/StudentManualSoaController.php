<?php

namespace App\Http\Controllers;

use App\Models\StudentManualSoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                'Content-Type' => $soa->mime_type,
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $soa->original_filename).'"',
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

        // Check if demo child
        $demoChildren = [
            'AMIS-2026-DEMO-01' => ['name' => 'AHMAD Z. LINGASA', 'grade' => 'Grade 1', 'monthly' => 3803.33],
            'AMIS-2026-DEMO-02' => ['name' => 'MARYAM Z. LINGASA', 'grade' => 'Grade 3', 'monthly' => 3926.11],
            'AMIS-2026-DEMO-03' => ['name' => 'YUSUF Z. LINGASA', 'grade' => 'Grade 5', 'monthly' => 4077.22],
        ];

        if (isset($demoChildren[$studentIdentifier])) {
            $child = $demoChildren[$studentIdentifier];
            $tuition = 36500.00;
            $misc = 1900.00;
            $totalFees = $tuition + $misc;
            $discountPercent = 15.0;
            $discountAmount = round($tuition * 0.15, 2);
            $finalFees = $totalFees - $discountAmount;

            $demoService = app(\App\Services\DemoPaymentScheduleService::class);
            $childrenCollection = collect([
                (object)[
                    'id' => $studentIdentifier,
                    'student_number' => $studentIdentifier,
                    'first_name' => explode(' ', $child['name'])[0],
                    'last_name' => 'LINGASA',
                    'full_name' => $child['name'],
                    'grade_level' => $child['grade'],
                    'enrollment_fee_paid' => 3000.00,
                    'installment_months' => 9,
                    'school_year' => '2026-2027',
                    'monthly_tuition' => $child['monthly'],
                    'remaining_balance' => $child['monthly'] * 9,
                ]
            ]);

            $schedule = $demoService->build($childrenCollection, $user);

            $soaData = [
                'student_name' => $child['name'],
                'address' => 'DAVAO CITY',
                'email' => $user->email,
                'lrn' => '123456789012',
                'category' => 'Elementary',
                'grade_level' => $child['grade'],
                'discount_privilege' => '15%',
                'discount_status' => 'Active (Sibling Discount)',
                'tuition_fee' => $tuition,
                'misc_fee' => $misc,
                'total_fees' => $totalFees,
                'discount_amount' => $discountAmount,
                'final_fees' => $finalFees,
                'enrollment_paid' => 3000.00,
                'enrollment_date' => '5-May-26',
                'enrollment_account' => '10539',
                'books_fee' => 5900.00,
                'books_paid' => 1000.00,
                'books_date' => '5-May-26',
                'books_account' => '10539',
                'monthly_schedule' => [],
                'monthly_rate' => $child['monthly'],
                'total_remaining' => $child['monthly'] * 9,
                'school_year' => '2026-2027',
            ];

            return view('payment.official-soa', compact('soaData'));
        }

        abort(404, 'Student account not found.');
    }
}
