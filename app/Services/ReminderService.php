<?php

namespace App\Services;

use App\Mail\PaymentReminderMail;
use App\Models\EnrollmentApplicant;
use App\Models\ReminderCampaign;
use App\Models\ReminderRecipient;
use App\Jobs\SendPaymentReminderJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ReminderService
{
    /**
     * Collect all parent/guardian emails from approved enrollment records,
     * validate, deduplicate, and create a DRAFT campaign with PENDING recipients.
     *
     * Returns the created campaign.
     */
    public function prepareRecipients(string $name, string $schoolYear, int $sentByUserId): ReminderCampaign
    {
        // ── 1. Pull all approved enrollment records ───────────────────────────
        $applicants = EnrollmentApplicant::where('status', 'approved')
            ->select([
                'id',
                'first_name', 'last_name',
                'father_first_name', 'father_last_name', 'father_email',
                'mother_first_name', 'mother_last_name', 'mother_email',
                'guardian_email',
                'parent_email',
            ])
            ->get();

        // ── 2. Collect every email with an optional display name ──────────────
        $rawEmails   = [];   // ['email' => 'display name']
        $totalSource = 0;

        foreach ($applicants as $applicant) {
            $sources = [
                $applicant->father_email  => $this->buildName($applicant->father_first_name, $applicant->father_last_name),
                $applicant->mother_email  => $this->buildName($applicant->mother_first_name, $applicant->mother_last_name),
                $applicant->guardian_email => 'Guardian',
                $applicant->parent_email  => $this->buildName($applicant->father_first_name ?? $applicant->mother_first_name, $applicant->father_last_name ?? $applicant->mother_last_name),
            ];

            foreach ($sources as $email => $displayName) {
                if (!empty($email)) {
                    $totalSource++;
                    $normalized = strtolower(trim($email));
                    // Only store first name found for this email (first parent wins)
                    if (!isset($rawEmails[$normalized])) {
                        $rawEmails[$normalized] = $displayName;
                    }
                }
            }
        }

        // ── 3. Validate email format ──────────────────────────────────────────
        $validEmails   = [];
        $invalidCount  = 0;

        foreach ($rawEmails as $email => $name_) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[$email] = $name_;
            } else {
                $invalidCount++;
            }
        }

        $totalUnique      = count($validEmails);
        $totalDuplicates  = $totalSource - $totalUnique - $invalidCount;

        // ── 4. Create campaign record ─────────────────────────────────────────
        $campaign = ReminderCampaign::create([
            'name'                    => $name,
            'school_year'             => $schoolYear,
            'status'                  => ReminderCampaign::STATUS_DRAFT,
            'sent_by'                 => $sentByUserId,
            'total_sources'           => $totalSource,
            'total_unique'            => $totalUnique,
            'total_duplicates_removed'=> max(0, $totalDuplicates),
            'total_invalid'           => $invalidCount,
            'total_pending'           => $totalUnique,
        ]);

        // ── 5. Insert recipients using insertOrIgnore ─────────────────────────
        // The UNIQUE(campaign_id, normalized_email) constraint prevents duplicates
        // even if this method is called again for the same campaign.
        $now   = now()->toDateTimeString();
        $rows  = [];

        foreach ($validEmails as $email => $displayName) {
            $rows[] = [
                'campaign_id'      => $campaign->id,
                'normalized_email' => $email,
                'parent_name'      => $displayName ?: null,
                'status'           => ReminderRecipient::STATUS_PENDING,
                'attempts'         => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        // Batch insert (ignores rows that violate the unique constraint)
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('reminder_recipients')->insertOrIgnore($chunk);
        }

        return $campaign->fresh();
    }

    /**
     * Send a test email to a given address.
     * Does NOT change any campaign or recipient records.
     * Does NOT add the test address to the real recipient list.
     */
    public function sendTestEmail(string $testEmail, ?string $recipientName = null, ?string $billingMonth = null): void
    {
        $testRef = strtoupper(substr(md5(uniqid('', true)), 0, 4));
        $name = $recipientName ?: strtoupper(explode('@', $testEmail)[0]);

        $mailable = new PaymentReminderMail(
            recipientName: $name,
            billingMonth: $billingMonth ?? now()->format('Y-m'),
            dispatchRef: "Ref #{$testRef}"
        );

        Mail::to(trim($testEmail))->send($mailable);
    }

    /**
     * Start the campaign: set to PROCESSING and dispatch queue jobs.
     * Only dispatches recipients that are PENDING or RETRY.
     */
    public function startCampaign(ReminderCampaign $campaign): void
    {
        if (!$campaign->canStart()) {
            return;
        }

        $campaign->update([
            'status'     => ReminderCampaign::STATUS_PROCESSING,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        $this->dispatchPendingJobs($campaign);
    }

    /**
     * Resume a PAUSED campaign: dispatch only PENDING and RETRY recipients.
     * SENT recipients are never touched.
     */
    public function resumeCampaign(ReminderCampaign $campaign): void
    {
        if (!$campaign->canResume()) {
            return;
        }

        $campaign->update([
            'status'        => ReminderCampaign::STATUS_PROCESSING,
            'paused_reason' => null,
        ]);

        $this->dispatchPendingJobs($campaign);
    }

    /**
     * Pause the campaign (typically called from a controller action or SMTP handler).
     */
    public function pauseCampaign(ReminderCampaign $campaign, string $reason = ''): void
    {
        $campaign->pause($reason);
    }

    // ── Internal Helpers ──────────────────────────────────────────────────────

    /**
     * Dispatch SendPaymentReminderJob for every PENDING and RETRY recipient.
     * Chunked to avoid memory issues with large lists.
     */
    private function dispatchPendingJobs(ReminderCampaign $campaign): void
    {
        $campaign->recipients()
            ->whereIn('status', [
                ReminderRecipient::STATUS_PENDING,
                ReminderRecipient::STATUS_RETRY,
            ])
            ->select('id')
            ->chunkById(200, function ($recipients) {
                foreach ($recipients as $recipient) {
                    SendPaymentReminderJob::dispatch($recipient->id);
                }
            });
    }

    private function buildName(?string $firstName, ?string $lastName): string
    {
        return trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
    }
}
