<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderRecipient extends Model
{
    // ── Status Constants ──────────────────────────────────────────────────────
    const STATUS_PENDING    = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_SENT       = 'SENT';       // TERMINAL — never changes again
    const STATUS_RETRY      = 'RETRY';
    const STATUS_FAILED     = 'FAILED';     // TERMINAL after max attempts

    protected $fillable = [
        'campaign_id',
        'normalized_email',
        'parent_name',
        'status',
        'attempts',
        'last_attempt_at',
        'sent_at',
        'next_retry_at',
        'last_error',
        'smtp_message_id',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'sent_at'         => 'datetime',
        'next_retry_at'   => 'datetime',
        'attempts'        => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReminderCampaign::class, 'campaign_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAlreadySent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING    => 'bg-slate-100 text-slate-600',
            self::STATUS_PROCESSING => 'bg-blue-100 text-blue-700',
            self::STATUS_SENT       => 'bg-emerald-100 text-emerald-700',
            self::STATUS_RETRY      => 'bg-amber-100 text-amber-700',
            self::STATUS_FAILED     => 'bg-red-100 text-red-700',
            default                 => 'bg-slate-100 text-slate-600',
        };
    }
}
