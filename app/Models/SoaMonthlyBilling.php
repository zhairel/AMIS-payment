<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoaMonthlyBilling extends Model
{
    protected $fillable = [
        'student_account_id', 'student_id',
        'month_number', 'month_name', 'due_date',
        'amount_due', 'description', 'status', 'paid_at',
    ];

    protected $casts = [
        'due_date'   => 'date',
        'paid_at'    => 'datetime',
        'amount_due' => 'decimal:2',
    ];

    public function studentAccount(): BelongsTo
    {
        return $this->belongsTo(StudentAccount::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudentAccountPayment::class, 'soa_monthly_billing_id');
    }

    /** Check if this billing is overdue */
    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_date->isPast();
    }
}
