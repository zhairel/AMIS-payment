<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptScanLog extends Model
{
    protected $fillable = [
        'scan_token', 'user_id', 'parent_full_name', 'student_names', 'billing_ids',
        'receiving_channel', 'receiving_account', 'payment_mode', 'reference_no',
        'transaction_date', 'transaction_at', 'detected_amount', 'expected_amount', 'ocr_engine',
        'ocr_passes', 'document_status', 'image_quality_status', 'amount_status',
        'ocr_confidence', 'date_status', 'duplicate_status', 'scan_status', 'risk_codes',
        'receipt_hash', 'perceptual_hash', 'scanned_at',
    ];

    protected $casts = [
        'student_names' => 'array',
        'billing_ids' => 'array',
        'transaction_date' => 'date',
        'transaction_at' => 'datetime',
        'detected_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'scanned_at' => 'datetime',
        'risk_codes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
