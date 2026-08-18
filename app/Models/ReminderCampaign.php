<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderCampaign extends Model
{
    // ── Status Constants ──────────────────────────────────────────────────────
    const STATUS_DRAFT               = 'DRAFT';
    const STATUS_PROCESSING          = 'PROCESSING';
    const STATUS_PAUSED              = 'PAUSED';
    const STATUS_COMPLETED           = 'COMPLETED';
    const STATUS_PARTIALLY_COMPLETED = 'PARTIALLY_COMPLETED';
    const STATUS_FAILED              = 'FAILED';

    protected $fillable = [
        'name',
        'school_year',
        'status',
        'paused_reason',
        'sent_by',
        'total_sources',
        'total_unique',
        'total_duplicates_removed',
        'total_invalid',
        'total_sent',
        'total_pending',
        'total_retry',
        'total_failed',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReminderRecipient::class, 'campaign_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Recalculate live counters from the recipients table.
     * Call after every job completes.
     */
    public function syncStats(): void
    {
        $counts = $this->recipients()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $this->update([
            'total_sent'    => $counts[ReminderRecipient::STATUS_SENT]       ?? 0,
            'total_pending' => $counts[ReminderRecipient::STATUS_PENDING]    ?? 0,
            'total_retry'   => $counts[ReminderRecipient::STATUS_RETRY]      ?? 0,
            'total_failed'  => ($counts[ReminderRecipient::STATUS_FAILED]    ?? 0)
                             + ($counts[ReminderRecipient::STATUS_PROCESSING] ?? 0),
        ]);
    }

    /**
     * Mark the campaign COMPLETED or PARTIALLY_COMPLETED when all jobs are done.
     * Call after syncStats().
     */
    public function markFinishedIfDone(): void
    {
        // Reload fresh data
        $this->refresh();

        $stillRunning = $this->recipients()
            ->whereIn('status', [
                ReminderRecipient::STATUS_PENDING,
                ReminderRecipient::STATUS_PROCESSING,
                ReminderRecipient::STATUS_RETRY,
            ])
            ->exists();

        if ($stillRunning) {
            return;
        }

        $hasFailed  = $this->total_failed  > 0;
        $hasSent    = $this->total_sent    > 0;

        $newStatus = match (true) {
            $hasSent && $hasFailed  => self::STATUS_PARTIALLY_COMPLETED,
            $hasSent && !$hasFailed => self::STATUS_COMPLETED,
            !$hasSent               => self::STATUS_FAILED,
        };

        $this->update([
            'status'       => $newStatus,
            'completed_at' => now(),
        ]);
    }

    /**
     * Pause the campaign (e.g. on SMTP limit).
     */
    public function pause(string $reason = ''): void
    {
        if ($this->status === self::STATUS_PROCESSING) {
            $this->update([
                'status'        => self::STATUS_PAUSED,
                'paused_reason' => $reason,
            ]);
        }
    }

    /**
     * Whether the campaign can still be started.
     */
    public function canStart(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Whether the campaign can be resumed.
     */
    public function canResume(): bool
    {
        return in_array($this->status, [self::STATUS_PAUSED, self::STATUS_PARTIALLY_COMPLETED]);
    }

    /**
     * Whether the campaign is in a terminal (finished) state.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_PARTIALLY_COMPLETED,
            self::STATUS_FAILED,
        ]);
    }

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT               => 'Draft',
            self::STATUS_PROCESSING          => 'Sending…',
            self::STATUS_PAUSED              => 'Paused – SMTP Limit',
            self::STATUS_COMPLETED           => 'Completed',
            self::STATUS_PARTIALLY_COMPLETED => 'Partially Completed',
            self::STATUS_FAILED              => 'Failed',
            default                          => $this->status,
        };
    }

    /**
     * Tailwind badge colour class for the current status.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT               => 'bg-slate-100 text-slate-700',
            self::STATUS_PROCESSING          => 'bg-blue-100 text-blue-700',
            self::STATUS_PAUSED              => 'bg-amber-100 text-amber-700',
            self::STATUS_COMPLETED           => 'bg-emerald-100 text-emerald-700',
            self::STATUS_PARTIALLY_COMPLETED => 'bg-yellow-100 text-yellow-800',
            self::STATUS_FAILED              => 'bg-red-100 text-red-700',
            default                          => 'bg-slate-100 text-slate-600',
        };
    }
}
