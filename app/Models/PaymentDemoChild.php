<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDemoChild extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'demo_student_number',
        'grade_level',
        'gender',
        'school_year',
        'tuition_fee',
        'miscellaneous_fee',
        'books_fee',
        'discount_percentage',
        'discount_amount',
        'enrollment_fee_paid',
        'total_balance',
        'remaining_balance',
        'monthly_tuition',
        'installment_months',
    ];

    protected function casts(): array
    {
        return [
            'tuition_fee' => 'decimal:2',
            'miscellaneous_fee' => 'decimal:2',
            'books_fee' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'enrollment_fee_paid' => 'decimal:2',
            'total_balance' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'monthly_tuition' => 'decimal:2',
            'installment_months' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
