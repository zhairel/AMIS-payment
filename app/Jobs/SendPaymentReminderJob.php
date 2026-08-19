<?php

namespace App\Jobs;

use App\Mail\PaymentReminderMail;
use App\Models\ReminderCampaign;
use App\Models\ReminderRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * We handle retries manually with custom backoff and re-dispatch.
     * Laravel's built-in retry is disabled (tries = 1).
     */
    public int $tries   = 1;
    public int $timeout = 90;

    /**
     * Exponential backoff in seconds, indexed by current attempt number (1-based).
     * Attempt 1 failed → wait 15 min before attempt 2, etc.
     */
    private const BACKOFF = [
        1 => 900,    // 15 minutes
        2 => 1800,   // 30 minutes
        3 => 3600,   // 1 hour
        4 => 10800,  // 3 hours
        5 => 21600,  // 6 hours
    ];

    private const MAX_ATTEMPTS = 5;

    public function __construct(public readonly int $recipientId)
    {
    }

    public function handle(): void
    {
        // ── STEP 1: Pessimistic lock — check & claim the recipient ────────────
        $shouldSend = false;

        DB::transaction(function () use (&$shouldSend) {
            /** @var ReminderRecipient|null $recipient */
            $recipient = ReminderRecipient::lockForUpdate()->find($this->recipientId);

            if (!$recipient) {
                return; // Record deleted — nothing to do
            }

            // STRICT RULE: If already SENT, NEVER send again.
            if ($recipient->status === ReminderRecipient::STATUS_SENT) {
                Log::info("[ReminderJob] Recipient #{$this->recipientId} ({$recipient->normalized_email}) already SENT. Skipping.");
                return;
            }

            // Mark PROCESSING under the lock
            $recipient->update([
                'status'          => ReminderRecipient::STATUS_PROCESSING,
                'last_attempt_at' => now(),
                'attempts'        => $recipient->attempts + 1,
            ]);

            $shouldSend = true;
        });

        if (!$shouldSend) {
            return;
        }

        // ── STEP 2: Load fresh record outside the transaction ─────────────────
        /** @var ReminderRecipient $recipient */
        $recipient = ReminderRecipient::find($this->recipientId);

        if (!$recipient || $recipient->status !== ReminderRecipient::STATUS_PROCESSING) {
            return; // Race condition guard
        }

        // ── STEP 3: Send the email ────────────────────────────────────────────
        try {
            $billingMonth = $recipient->campaign?->billing_month ?? now()->format('Y-m');
            $mailable = new PaymentReminderMail(
                recipientName: $recipient->parent_name ?: 'Valued Family',
                billingMonth: $billingMonth
            );

            Mail::to($recipient->normalized_email)->send($mailable);

            // ── SUCCESS ───────────────────────────────────────────────────────
            $recipient->update([
                'status'     => ReminderRecipient::STATUS_SENT,
                'sent_at'    => now(),
                'last_error' => null,
            ]);

            Log::info("[ReminderJob] ✓ SENT to {$recipient->normalized_email} (recipient #{$this->recipientId})");

        } catch (\Throwable $e) {
            // ── FAILURE ───────────────────────────────────────────────────────
            $this->handleFailure($recipient, $e);
        }

        // ── STEP 4: Update campaign live stats ────────────────────────────────
        try {
            $recipient->refresh();
            $campaign = $recipient->campaign;
            $campaign->syncStats();
            $campaign->markFinishedIfDone();
        } catch (\Throwable $e) {
            Log::warning("[ReminderJob] Stats sync failed: " . $e->getMessage());
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function handleFailure(ReminderRecipient $recipient, \Throwable $e): void
    {
        $message    = $e->getMessage();
        $isTemp     = $this->isTemporarySmtpError($e);
        $attempt    = $recipient->attempts;
        $errorSnip  = substr($message, 0, 500);

        Log::warning("[ReminderJob] Failed sending to {$recipient->normalized_email} (attempt {$attempt}): {$message}");

        if ($isTemp && $attempt < self::MAX_ATTEMPTS) {
            // ── Temporary error → RETRY with backoff ──────────────────────────
            $delay = self::BACKOFF[$attempt] ?? 21600;

            $recipient->update([
                'status'        => ReminderRecipient::STATUS_RETRY,
                'last_error'    => $errorSnip,
                'next_retry_at' => now()->addSeconds($delay),
            ]);

            // Re-dispatch this job with delay
            static::dispatch($this->recipientId)->delay(now()->addSeconds($delay));

            Log::info("[ReminderJob] Scheduled RETRY #{$attempt} for {$recipient->normalized_email} in {$delay}s");

            // Pause campaign if this is an SMTP rate-limit / quota error
            if ($this->isSmtpLimitError($e)) {
                try {
                    $campaign = $recipient->campaign;
                    $campaign->pause('SMTP limit reached: ' . substr($message, 0, 200));
                    Log::warning("[ReminderJob] Campaign #{$campaign->id} PAUSED — SMTP limit reached.");
                } catch (\Throwable $ex) {
                    Log::error("[ReminderJob] Could not pause campaign: " . $ex->getMessage());
                }
            }

        } else {
            // ── Max attempts reached or permanent error → FAILED ──────────────
            $recipient->update([
                'status'        => ReminderRecipient::STATUS_FAILED,
                'last_error'    => $errorSnip,
                'next_retry_at' => null,
            ]);

            Log::error("[ReminderJob] PERMANENTLY FAILED for {$recipient->normalized_email} after {$attempt} attempt(s).");
        }
    }

    /**
     * Returns true for temporary / transient SMTP errors that should be retried.
     */
    private function isTemporarySmtpError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        $temporaryPatterns = [
            '421', '450', '451', '452',
            'rate limit', 'too many', 'temporarily',
            'quota exceeded', 'quota',
            'connection timed out', 'timed out',
            'connection refused', 'network',
            'service unavailable', 'try again',
            'temporarily unavailable', 'deferred',
            'greylist', 'try later',
        ];

        foreach ($temporaryPatterns as $pattern) {
            if (str_contains($msg, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true specifically for SMTP sending-limit / quota errors
     * (should trigger campaign PAUSE, not just recipient RETRY).
     */
    private function isSmtpLimitError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'rate limit')
            || str_contains($msg, 'too many')
            || str_contains($msg, 'quota')
            || str_contains($msg, '421')
            || str_contains($msg, '452')
            || str_contains($msg, 'daily sending limit')
            || str_contains($msg, 'hourly sending limit');
    }
}
