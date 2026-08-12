<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAccountPayment extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (StudentAccountPayment $payment) => $payment->syncRelatedBalances());
        static::deleted(fn (StudentAccountPayment $payment) => $payment->syncRelatedBalances());
    }

    protected $fillable = [
        'payment_submission_id', 'allocation_sequence', 'allocation_source', 'student_account_id', 'student_id', 'soa_monthly_billing_id',
        'method', 'payment_mode', 'reference_no', 'transaction_date', 'transaction_at', 'or_number', 'checked_by', 'account_received',
        'amount', 'receipt_url', 'status', 'remarks',
        'paid_at', 'verified_at',
        'ocr_status', 'ocr_raw_text', 'ocr_scanned_ref', 'ocr_scanned_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'transaction_date' => 'date',
        'transaction_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function studentAccount(): BelongsTo
    {
        return $this->belongsTo(StudentAccount::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function monthlyBilling(): BelongsTo
    {
        return $this->belongsTo(SoaMonthlyBilling::class, 'soa_monthly_billing_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class, 'payment_submission_id');
    }

    /**
     * Pending receipts never reduce balances. Once Finance verifies (or
     * reverses) a payment, recalculate both the month and the whole account.
     */
    public function syncRelatedBalances(): void
    {
        $billing = $this->monthlyBilling()->with('payments')->first();
        if ($billing) {
            $verifiedAmount = (float) $billing->payments
                ->where('status', 'verified')
                ->sum(fn ($payment) => (float) $payment->amount);
            if ((int) $billing->month_number === 0) {
                $verifiedAmount = max(
                    $verifiedAmount,
                    min((float) $billing->amount_due, (float) $billing->studentAccount?->enrollment_fee_paid)
                );
            }
            $remaining = max(0, round((float) $billing->amount_due - $verifiedAmount, 2));
            $status = $remaining <= 0.01
                ? 'paid'
                : ($billing->due_date?->isPast() ? 'overdue' : 'unpaid');

            $billing->updateQuietly([
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($billing->paid_at ?? now()) : null,
            ]);
        }

        $this->studentAccount()->first()?->recalculate();
    }
}
