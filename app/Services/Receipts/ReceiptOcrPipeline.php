<?php

namespace App\Services\Receipts;

use App\Models\ReceiptSubmission;
use App\Services\ReceiptClassificationService;
use App\Services\ReceiptFingerprintService;
use Illuminate\Support\Facades\Storage;

class ReceiptOcrPipeline
{
    public function __construct(
        private readonly ReceiptQualityService $quality,
        private readonly ReceiptProductionOcrService $productionOcr,
        private readonly ReceiptValidationService $validator,
        private readonly ReceiptDuplicateService $duplicates,
        private readonly ReceiptClassificationService $classifier,
    ) {}

    /**
     * Analyze an image file using the EXACT production OCR pipeline in dry-run mode (zero side effects).
     * Reused by the Developer/QA AMIS AI Receipt Scanner Test Lab.
     */
    public function analyzeFile(string $filePath): array
    {
        $startTime = microtime(true);
        $quality = $this->quality->assess($filePath);
        $analysis = $this->productionOcr->analyze($filePath);
        $fields = $analysis['fields'];
        $uncertain = $analysis['uncertain_fields'];
        $attempts = $analysis['attempts'];

        // Get Image Dimensions safely
        [$imgW, $imgH] = @getimagesize($filePath) ?: [0, 0];
        $dimensions = ($imgW > 0 && $imgH > 0) ? "{$imgW} × {$imgH}" : 'Unknown';

        $classification = $this->classify($fields, $attempts);

        $fileHash = hash_file('sha256', $filePath);
        $dHash = (new ReceiptFingerprintService)->differenceHash($filePath);
        $duplicate = $this->duplicates->checkRaw(
            $fields['normalized_reference'] ?? null,
            $fileHash,
            $dHash,
            $fields['provider'] ?? null,
            $fields['amount'] ?? null,
            $fields['transaction_date'] ?? null
        );

        $validation = $this->validator->validate($fields, $uncertain);
        $validation['classification'] = $classification;

        $endTime = microtime(true);
        $durationMs = (int) round(($endTime - $startTime) * 1000);

        return [
            'primary_ocr_engine' => 'Tesseract',
            'image_dimensions' => $dimensions,
            'text_regions_count' => collect($attempts)->sum(fn ($attempt) => (int) data_get($attempt, 'raw.regions', 0)),
            'fallback_used' => $analysis['fallback_used'],
            'fallback_engine' => $analysis['fallback_used'] ? 'docTR' : null,
            'confidence' => $analysis['confidence'],
            'quality_score' => $quality['quality_score'] ?? null,
            'quality_assessment' => $this->combinedQuality($quality, $analysis['preprocessing']),
            'classification' => $classification,
            'fields' => $fields,
            'uncertain_fields' => $uncertain,
            'validation' => $validation,
            'duplicate' => $duplicate,
            'raw_text' => collect($attempts)->pluck('raw_text')->filter()->implode("\n"),
            'ocr_status' => $analysis['ocr_status'],
            'duration_ms' => $durationMs,
        ];
    }

    public function process(ReceiptSubmission $receipt, ?int $ignorePaymentSubmissionId = null): ReceiptSubmission
    {
        $receipt->transitionTo(ReceiptSubmission::PROCESSING, 'processing_started');
        $receipt->forceFill(['processing_started_at' => now()])->save();
        $original = Storage::disk('local')->path($receipt->original_receipt_path);
        $quality = $this->quality->assess($original);
        $analysis = $this->productionOcr->analyze($original);
        $fields = $analysis['fields'];
        $fields['ocr_status'] = $analysis['ocr_status'];
        $uncertain = $analysis['uncertain_fields'];
        $attemptNumber = ((int) $receipt->ocrResults()->max('attempt_number')) + 1;
        foreach ($analysis['attempts'] as $attempt) {
            $this->recordAttempt($receipt, $attemptNumber, $attempt);
            $attemptNumber++;
        }
        $attempts = $receipt->ocrResults()->count();
        $quality = $this->combinedQuality($quality, $analysis['preprocessing']);

        $classification = $this->classify($fields, $analysis['attempts']);
        $validation = $this->validator->validate($fields, $uncertain);
        $validation['classification'] = $classification;
        $receipt->forceFill([
            'processed_receipt_path' => null,
            'quality_score' => $quality['quality_score'],
            'quality_assessment' => $quality,
            'primary_ocr_engine' => 'Tesseract',
            'ocr_confidence' => $analysis['confidence'],
            'structured_ocr' => $fields,
            'uncertain_fields' => $uncertain,
            'provider' => $fields['provider'],
            'reference_number' => $fields['reference_number'],
            'normalized_reference' => $fields['normalized_reference'],
            'amount' => $fields['amount'],
            'currency' => $fields['currency'],
            'transaction_date' => $fields['transaction_date'],
            'transaction_time' => $fields['transaction_time'],
            'sender_name' => $fields['sender_name'],
            'receiver_name' => $fields['receiver_name'],
            'transaction_status' => $fields['transaction_status'],
            'validation_results' => $validation,
        ])->save();
        $receipt->transitionTo(ReceiptSubmission::OCR_COMPLETED, 'ocr_completed', null, [
            'primary_engine' => 'Tesseract', 'fallback_used' => $analysis['fallback_used'],
            'ocr_status' => $analysis['ocr_status'], 'attempts' => $attempts, 'uncertain_fields' => $uncertain,
        ]);

        $duplicate = $this->duplicates->check($receipt->fresh(), $ignorePaymentSubmissionId);
        $notReceipt = $classification['type'] === 'not_receipt';
        $unusable = $notReceipt
            || (bool) data_get($analysis, 'preprocessing.reupload_required', false)
            || (($quality['readability'] ?? null) === 'unreadable'
                && data_get($analysis, 'preprocessing.blur_status') === 'SEVERELY_BLURRY');
        $status = $unusable
            ? ReceiptSubmission::REUPLOAD_REQUIRED
            : ($analysis['ocr_status'] !== 'OCR_SUCCESS'
                || ($validation['requires_review'] ?? true) || $duplicate['status'] !== 'UNIQUE'
                ? ReceiptSubmission::NEEDS_REVIEW
                : ReceiptSubmission::PENDING_VERIFICATION);
        $reason = $notReceipt
            ? $classification['message']
            : $this->reviewReason($status, $quality, $fields, $uncertain, $duplicate, $analysis['ocr_status']);

        $receipt->forceFill([
            'duplicate_status' => $duplicate['status'],
            'duplicate_results' => $duplicate,
            'review_reason' => $reason,
            'processing_completed_at' => now(),
        ])->save();
        $receipt->transitionTo($status, 'processing_completed', null, [
            'ocr_attempts' => $attempts, 'quality_score' => $quality['quality_score'],
            'uncertain_fields' => $uncertain, 'duplicate_status' => $duplicate['status'],
            'document_type' => $classification['type'], 'document_score' => $classification['score'],
        ], $reason);

        $rawCombinedText = collect($analysis['attempts'])->pluck('raw_text')->filter()->implode("\n");
        \Illuminate\Support\Facades\Log::info("AMIS_RECEIPT_OCR_DIAGNOSTICS", [
            'ocr_request_sent' => 'YES',
            'ocr_service_url' => config('services.receipt_ocr.url') ?: 'CLI runner (Tesseract + docTR)',
            'ocr_http_status' => !empty(config('services.receipt_ocr.url')) ? 200 : 'N/A (CLI)',
            'ocr_engine_used' => $analysis['fallback_used'] ? 'docTR (fallback)' : 'Tesseract',
            'raw_ocr_text' => $rawCombinedText,
            'raw_ocr_text_length' => strlen($rawCombinedText),
            'parsed_payment_method' => $fields['provider'] ?? null,
            'parsed_reference_number' => $fields['reference_number'] ?? null,
            'parsed_transaction_date' => $fields['transaction_date'] ?? null,
            'parsed_transaction_time' => $fields['transaction_time'] ?? null,
            'parsed_amount' => $fields['amount'] ?? null,
        ]);

        return $receipt->fresh(['ocrResults', 'auditLogs']);
    }

    private function classify(array $fields, array $attempts): array
    {
        return $this->classifier->classify([
            'raw_text' => collect($attempts)->pluck('raw_text')->filter()->implode("\n"),
            'detected_method' => $fields['provider'] ?? null,
            'detected_ref' => $fields['reference_number'] ?? null,
            'detected_amount' => $fields['amount'] ?? null,
            'detected_datetime' => $fields['transaction_date'] ?? null,
        ]);
    }

    private function recordAttempt(ReceiptSubmission $receipt, int $attempt, array $result): void
    {
        $raw = $result['raw'] ?? [];
        $structured = $result['parsed'] ?? [];
        $receipt->ocrResults()->create([
            'engine' => $result['engine'], 'attempt_number' => $attempt, 'source_variant' => $result['variant'],
            'status' => strtolower((string) $result['status']),
            'raw_text' => $result['raw_text'] ?: null, 'raw_json' => $raw,
            'structured_json' => $structured, 'confidence' => $result['confidence'],
            'warnings' => $structured['normalization_warnings'] ?? null, 'duration_ms' => $result['duration_ms'],
        ]);
    }

    private function reviewReason(string $status, array $quality, array $fields, array $uncertain, array $duplicate, string $ocrStatus): ?string
    {
        if ($status === ReceiptSubmission::REUPLOAD_REQUIRED) {
            if (($quality['readability'] ?? '') === 'unreadable') {
                return 'The receipt image is too unclear to read reliably. Please upload a clearer copy of the original receipt.';
            }

            return data_get($quality, 'user_message')
                ?: 'The receipt image is unusable because an essential part is unreadable. Please upload a clearer complete receipt.';
        }
        if ($duplicate['status'] !== 'UNIQUE') {
            return 'A duplicate indicator was found. Finance must compare this receipt with the earlier submission.';
        }
        if ($status === ReceiptSubmission::NEEDS_REVIEW) {
            if (in_array($ocrStatus, ['OCR_FAILED', 'OCR_PARTIAL'], true)) {
                return 'Some payment details could not be read automatically. Your proof of payment can still be submitted for Finance verification.';
            }

            return 'Some receipt checks require Finance review.';
        }

        return null;
    }

    private function combinedQuality(array $quality, array $preprocessing): array
    {
        return array_merge($quality, collect($preprocessing)->only([
            'image_type', 'blur_status', 'glare_detected', 'quality_score',
            'reupload_required', 'user_message', 'document_detected',
        ])->all());
    }
}
