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
}
