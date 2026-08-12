<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReceiptSubmission extends Model
{
    public const UPLOADED = 'UPLOADED';

    public const PROCESSING = 'PROCESSING';

    public const OCR_COMPLETED = 'OCR_COMPLETED';

    public const PENDING_VERIFICATION = 'PENDING_VERIFICATION';

    public const NEEDS_REVIEW = 'NEEDS_REVIEW';

    public const REUPLOAD_REQUIRED = 'REUPLOAD_REQUIRED';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    protected $fillable = [
        'submission_id', 'user_id', 'student_id', 'status', 'original_filename',
        'original_mime', 'original_size', 'original_receipt_path', 'processed_receipt_path',
        'receipt_hash', 'perceptual_hash', 'provider', 'reference_number',
        'normalized_reference', 'amount', 'currency', 'transaction_date',
        'transaction_time', 'sender_name', 'receiver_name', 'transaction_status',
        'quality_score', 'quality_assessment', 'primary_ocr_engine', 'ocr_confidence',
        'structured_ocr', 'uncertain_fields', 'duplicate_status', 'duplicate_results',
        'validation_results', 'review_reason', 'verified_by', 'verified_at',
        'processing_started_at', 'processing_completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'quality_assessment' => 'array',
        'structured_ocr' => 'array',
        'uncertain_fields' => 'array',
        'duplicate_results' => 'array',
        'validation_results' => 'array',
        'verified_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'submission_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ocrResults(): HasMany
    {
        return $this->hasMany(ReceiptOcrResult::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ReceiptAuditLog::class);
    }

    public function paymentSubmission(): HasOne
    {
        return $this->hasOne(PaymentSubmission::class);
    }

    public function transitionTo(string $status, string $event, ?int $userId = null, array $changes = [], ?string $notes = null): void
    {
        $from = $this->status;
        $this->forceFill(['status' => $status])->save();
        $this->auditLogs()->create([
            'user_id' => $userId,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $status,
            'changes' => $changes ?: null,
            'notes' => $notes,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
        ]);
    }
}
