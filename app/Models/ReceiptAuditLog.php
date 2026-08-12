<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'receipt_submission_id', 'user_id', 'event', 'from_status', 'to_status',
        'changes', 'notes', 'ip_address', 'user_agent',
    ];

    protected $casts = ['changes' => 'array'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ReceiptSubmission::class, 'receipt_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
