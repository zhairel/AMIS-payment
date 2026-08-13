<?php

namespace App\Jobs;

use App\Models\ReceiptSubmission;
use App\Services\Receipts\ReceiptOcrPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessReceiptSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $receiptSubmissionId,
        public readonly ?int $ignorePaymentSubmissionId = null,
    )
    {
        $this->onQueue('receipts');
    }

    public function handle(ReceiptOcrPipeline $pipeline): void
    {
        $receipt = ReceiptSubmission::findOrFail($this->receiptSubmissionId);
        if (in_array($receipt->status, [
            ReceiptSubmission::PENDING_VERIFICATION, ReceiptSubmission::NEEDS_REVIEW,
            ReceiptSubmission::REUPLOAD_REQUIRED, ReceiptSubmission::APPROVED, ReceiptSubmission::REJECTED,
        ], true)) {
            return;
        }
        $pipeline->process($receipt, $this->ignorePaymentSubmissionId);
    }

    public function failed(?\Throwable $exception): void
    {
        $receipt = ReceiptSubmission::find($this->receiptSubmissionId);
        if (! $receipt) {
            return;
        }
        $receipt->forceFill([
            'review_reason' => 'Automatic OCR processing failed. Finance must review the original receipt manually.',
            'processing_completed_at' => now(),
        ])->save();
        $receipt->transitionTo(ReceiptSubmission::NEEDS_REVIEW, 'processing_failed', null, [], $exception?->getMessage());
    }
}
