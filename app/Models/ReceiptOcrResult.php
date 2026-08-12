<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptOcrResult extends Model
{
    protected $fillable = [
        'receipt_submission_id', 'engine', 'attempt_number', 'source_variant',
        'status', 'raw_text', 'raw_json', 'structured_json', 'confidence',
        'warnings', 'duration_ms',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'structured_json' => 'array',
        'warnings' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ReceiptSubmission::class, 'receipt_submission_id');
    }
}
