<?php

namespace App\Services\Enrollment;

use App\Models\EnrollmentApplicant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnrollmentNotificationService
{
    public function sendSubmissionConfirmation(string $email, EnrollmentApplicant $applicant): void
    {
        try {
            $name = $applicant->first_name.' '.$applicant->last_name;
            $grade = $applicant->grade_level;
            $refNo = 'AMIS-'.str_pad($applicant->id, 5, '0', STR_PAD_LEFT);
            $submitted = strtoupper(now()->format('F')).now()->format(' j, Y \a\t g:i A');

            $html = '<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#059669,#047857);padding:32px;text-align:center;">
        <img src="'.asset('images/AMIS_Logo.png').'" alt="AMIS" width="64" height="64" style="margin-bottom:12px;">
        <div style="color:rgba(255,255,255,0.8);font-size:13px;margin-bottom:4px;">Al Munawwara Islamic School</div>
        <h1 style="color:#fff;font-size:20px;margin:0;">Al Munawwara Islamic School</h1>
        <p style="color:rgba(255,255,255,0.75);font-size:13px;margin:4px 0 0;">Online Pre-Enrollment System</p>
    </td></tr>
    <tr><td style="padding:32px 40px;">
        <div style="text-align:center;margin-bottom:24px;">
            <div style="display:inline-block;background:#f0fdf4;border:2px solid #bbf7d0;border-radius:999px;padding:8px 20px;font-size:13px;font-weight:700;color:#059669;">Application Submitted</div>
        </div>
        <h2 style="color:#111827;font-size:20px;margin:0 0 8px;">Assalamualaikum, '.htmlspecialchars($name).'!</h2>
        <p style="color:#6b7280;font-size:14px;line-height:1.7;margin:0 0 24px;">
            Your enrollment application for <strong>'.htmlspecialchars($grade).'</strong> has been successfully submitted. Our admissions team will review your application and get back to you shortly.
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;padding:20px;margin-bottom:24px;">
            <tr><td style="padding:6px 0;font-size:13px;color:#6b7280;">Reference No.</td><td style="padding:6px 0;font-size:13px;font-weight:700;color:#111827;text-align:right;">'.$refNo.'</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:#6b7280;">Grade Level</td><td style="padding:6px 0;font-size:13px;font-weight:700;color:#111827;text-align:right;">'.htmlspecialchars($grade).'</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:#6b7280;">Submitted</td><td style="padding:6px 0;font-size:13px;font-weight:700;color:#111827;text-align:right;">'.$submitted.'</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:#6b7280;">Status</td><td style="padding:6px 0;text-align:right;"><span style="background:#fef9c3;color:#854d0e;font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;">Pending Review</span></td></tr>
        </table>
        <p style="color:#6b7280;font-size:13px;line-height:1.7;margin:0;">
            You can track your application status by logging in to the enrollment portal at <a href="'.config('app.url').'" style="color:#059669;font-weight:600;">'.config('app.url').'</a>.
        </p>
    </td></tr>
    <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
        <p style="color:#9ca3af;font-size:11px;margin:0;">&copy; '.date('Y').' Al Munawwara Islamic School. All rights reserved.</p>
    </td></tr>
</table>
</td></tr>
</table>
</body></html>';

            Mail::html($html, function ($message) use ($email, $name) {
                $message->to($email, $name)
                    ->replyTo('noreply@amis.edu.ph', 'AMIS Support')
                    ->subject('AMIS Enrollment - Application Submitted Successfully');
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send enrollment confirmation email: '.$e->getMessage());
        }
    }
}
