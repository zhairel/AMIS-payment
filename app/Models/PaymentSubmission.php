<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentSubmission extends Model
{
    protected $fillable = [
        'submission_number', 'user_id', 'receipt_submission_id', 'client_token', 'method', 'payment_mode', 'account_received',
        'reference_no', 'reference_normalized', 'transaction_date', 'transaction_at', 'receipt_hash', 'perceptual_hash',
        'total_amount', 'receipt_url', 'status', 'remarks',
        'ocr_status', 'ocr_confidence', 'risk_status', 'risk_flags', 'risk_checked_at', 'ocr_raw_text', 'ocr_scanned_ref',
        'ocr_scanned_amount', 'submitted_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'ocr_scanned_amount' => 'decimal:2',
        'transaction_date' => 'date',
        'transaction_at' => 'datetime',
        'risk_flags' => 'array',
        'risk_checked_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receiptSubmission(): BelongsTo
    {
        return $this->belongsTo(ReceiptSubmission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudentAccountPayment::class);
    }

    public function advanceCredit(): HasOne
    {
        return $this->hasOne(FamilyAdvanceCredit::class);
    }

    public function getEffectiveStatusAttribute(): string
    {
        if (in_array($this->status, ['verified', 'rejected'], true)) {
            return $this->status;
        }

        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        if ($payments->isNotEmpty() && $payments->every(fn ($payment) => $payment->status === 'verified')) {
            return 'verified';
        }
        if ($payments->contains('status', 'rejected')) {
            return 'rejected';
        }

        return 'pending';
    }
}
