<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyAdvanceCredit extends Model
{
    protected $fillable = [
        'user_id', 'payment_submission_id', 'original_amount',
        'remaining_amount', 'status', 'verified_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentSubmission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
