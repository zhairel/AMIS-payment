<?php

namespace App\Services\Receipts;

use App\Services\GoogleVisionService;
use App\Services\PaddleOcrService;
use App\Services\ReceiptClassificationService;
use App\Services\ReceiptFingerprintService;
use Illuminate\Support\Facades\Storage;

class ReceiptOcrPipeline
{
    public function __construct(
        private readonly ReceiptQualityService $quality,
        private readonly ReceiptPreprocessor $preprocessor,
        private readonly PaddleOcrService $paddle,
        private readonly TesseractOcrService $tesseract,
        private readonly ReceiptFieldNormalizer $normalizer,
        private readonly ReceiptValidationService $validator,
        private readonly ReceiptDuplicateService $duplicates,
        private readonly GoogleVisionService $vision = new GoogleVisionService(),
    ) {}

    /**
     * Analyze an image file using the EXACT production OCR pipeline in dry-run mode (zero side effects).
     * Reused by the Developer/QA AMIS AI Receipt Scanner Test Lab.
     */
    public function analyzeFile(string $filePath): array
    {
        $startTime = microtime(true);
        $processedPath = $this->preprocessor->process($filePath, 'test-lab');
        $ocrPath = $processedPath ? Storage::disk('local')->path($processedPath) : $filePath;
        $quality = $this->quality->assess($ocrPath);

        // Get Image Dimensions safely
        [$imgW, $imgH] = @getimagesize($filePath) ?: [0, 0];
        $dimensions = ($imgW > 0 && $imgH > 0) ? "{$imgW} × {$imgH}" : "Unknown";

        // 1. Primary Engine Attempt (PaddleOCR PP-OCRv6 -> docTR / EasyOCR -> GoogleVision -> Tesseract)
        $primaryEngine = $primary['engine'] ?? 'PaddleOCR PP-OCRv6';
        $primary = $this->paddle->scanReceipt($ocrPath);
        if ($primary['success'] ?? false) {
            $primaryEngine = $primary['engine'] ?? 'PaddleOCR PP-OCRv6';
        } else {
            $primary = $this->vision->scanReceipt($ocrPath);
            if ($primary['success'] ?? false) {
                $primaryEngine = 'GoogleVision';
            } else {
                $primaryEngine = 'Tesseract';
                $primary = $this->tesseract->scan($ocrPath);
            }
        }

        $primaryFields = $this->normalizer->fromOcr($primary);
        $primaryValidation = $this->validator->validate($primaryFields);

        $fields = $primaryFields;
        $uncertain = [];
        $fallbackUsed = false;
        $fallbackEngine = null;

        // 2. Secondary Engine Fallback if needed
        if ($primaryEngine !== 'Tesseract' && $this->validator->needsFallback($primaryFields, $primary['confidence'] ?? null, $primaryValidation)) {
            $fallback = $this->tesseract->scan($ocrPath);
            $fallbackFields = $this->normalizer->fromOcr($fallback);
            $fallbackUsed = true;
            $fallbackEngine = 'Tesseract';
            [$fields, $uncertain] = $this->reconcile($primaryFields, $fallbackFields, (bool) ($fallback['success'] ?? false));
        }

        // 3. Classification via ReceiptClassificationService
        $classification = (new ReceiptClassificationService)->classify(array_merge($primary, $fields));

        // 4. Duplicate Check via ReceiptDuplicateService (read-only query)
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

        // 5. Validation via ReceiptValidationService
        $validation = $this->validator->validate($fields, $uncertain);

        $endTime = microtime(true);
        $durationMs = (int) round(($endTime - $startTime) * 1000);

        return [
            'primary_ocr_engine' => $primaryEngine,
            'image_dimensions' => $dimensions,
            'text_regions_count' => $primary['text_regions_count'] ?? (empty($primary['raw_text']) ? 0 : count(explode("\n", trim($primary['raw_text'])))),
            'fallback_used' => $fallbackUsed,
            'fallback_engine' => $fallbackEngine,
            'confidence' => $primary['confidence'] ?? null,
            'quality_score' => $quality['quality_score'] ?? null,
            'quality_assessment' => $quality,
            'classification' => $classification,
            'fields' => $fields,
            'uncertain_fields' => $uncertain,
            'validation' => $validation,
            'duplicate' => $duplicate,
            'raw_text' => $primary['raw_text'] ?? ($fields['raw_text'] ?? null),
            'duration_ms' => $durationMs,
        ];
    }

    public function process(ReceiptSubmission $receipt): ReceiptSubmission
    {
        $receipt->transitionTo(ReceiptSubmission::PROCESSING, 'processing_started');
        $receipt->forceFill(['processing_started_at' => now()])->save();
        $original = Storage::disk('local')->path($receipt->original_receipt_path);
        $processedPath = $this->preprocessor->process($original, $receipt->submission_id);
        $ocrPath = $processedPath ? Storage::disk('local')->path($processedPath) : $original;
        $quality = $this->quality->assess($ocrPath);

        $attemptNumber = ((int) $receipt->ocrResults()->max('attempt_number')) + 1;
        $paddleStarted = microtime(true);
        $primary = $this->paddle->scanReceipt($ocrPath);
        $primaryFields = $this->normalizer->fromOcr($primary);
        $primaryValidation = $this->validator->validate($primaryFields);
        $this->recordAttempt($receipt, $attemptNumber, 'PaddleOCR', $processedPath ? 'processed' : 'original', $primary, $primaryFields, (int) round((microtime(true) - $paddleStarted) * 1000));

        $fields = $primaryFields;
        $uncertain = [];
        if ($this->validator->needsFallback($primaryFields, $primary['confidence'] ?? null, $primaryValidation)) {
            $fallback = $this->tesseract->scan($ocrPath);
            $attemptNumber++;
            $fallbackFields = $this->normalizer->fromOcr($fallback);
            $this->recordAttempt($receipt, $attemptNumber, 'Tesseract', $processedPath ? 'processed' : 'original', $fallback, $fallbackFields, $fallback['duration_ms'] ?? null);
            [$fields, $uncertain] = $this->reconcile($primaryFields, $fallbackFields, (bool) ($fallback['success'] ?? false));
        }
        $attempts = $receipt->ocrResults()->count();

        $validation = $this->validator->validate($fields, $uncertain);
        $receipt->forceFill([
            'processed_receipt_path' => $processedPath,
            'quality_score' => $quality['quality_score'],
            'quality_assessment' => $quality,
            'primary_ocr_engine' => 'PaddleOCR',
            'ocr_confidence' => $primary['confidence'] ?? null,
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
            'primary_engine' => 'PaddleOCR', 'attempts' => $attempts, 'uncertain_fields' => $uncertain,
        ]);

        $duplicate = $this->duplicates->check($receipt->fresh());
        $criticalUnreadable = empty($fields['normalized_reference']) || in_array('reference_number', $uncertain, true)
            || empty($fields['amount']) || in_array('amount', $uncertain, true);
        $unusable = ($quality['readability'] ?? null) === 'unreadable';
        $status = ($unusable || $criticalUnreadable)
            ? ReceiptSubmission::REUPLOAD_REQUIRED
            : (($validation['requires_review'] ?? true) || $duplicate['status'] !== 'UNIQUE'
                ? ReceiptSubmission::NEEDS_REVIEW
                : ReceiptSubmission::PENDING_VERIFICATION);
        $reason = $this->reviewReason($status, $quality, $fields, $uncertain, $duplicate);

        $receipt->forceFill([
            'duplicate_status' => $duplicate['status'],
            'duplicate_results' => $duplicate,
            'review_reason' => $reason,
            'processing_completed_at' => now(),
        ])->save();
        $receipt->transitionTo($status, 'processing_completed', null, [
            'ocr_attempts' => $attempts, 'quality_score' => $quality['quality_score'],
            'uncertain_fields' => $uncertain, 'duplicate_status' => $duplicate['status'],
        ], $reason);

        return $receipt->fresh(['ocrResults', 'auditLogs']);
    }

    private function recordAttempt(ReceiptSubmission $receipt, int $attempt, string $engine, string $variant, array $raw, array $structured, ?int $duration): void
    {
        $receipt->ocrResults()->create([
            'engine' => $engine, 'attempt_number' => $attempt, 'source_variant' => $variant,
            'status' => $raw['status'] ?? (($raw['success'] ?? false) ? 'processed' : 'unavailable'),
            'raw_text' => $raw['raw_text'] ?? null, 'raw_json' => $raw,
            'structured_json' => $structured, 'confidence' => $raw['confidence'] ?? null,
            'warnings' => $structured['normalization_warnings'] ?? null, 'duration_ms' => $duration,
        ]);
    }

    private function reconcile(array $primary, array $fallback, bool $fallbackAvailable): array
    {
        if (! $fallbackAvailable) {
            return [$primary, []];
        }
        $merged = $primary;
        $uncertain = [];
        foreach (['provider', 'reference_number', 'normalized_reference', 'amount', 'currency', 'transaction_date', 'transaction_time', 'sender_name', 'receiver_name', 'transaction_status'] as $field) {
            $first = $primary[$field] ?? null;
            $second = $fallback[$field] ?? null;
            if ($first === null || $first === '') {
                $merged[$field] = $second;
            } elseif ($second !== null && $second !== '' && ! $this->same($field, $first, $second)) {
                $critical = in_array($field, ['reference_number', 'normalized_reference', 'amount', 'transaction_date'], true);
                if ($critical) {
                    $logicalField = $field === 'normalized_reference' ? 'reference_number' : $field;
                    $uncertain[] = $logicalField;
                    $merged[$field] = null;
                }
            }
        }
        $merged['ocr_sources'] = ['paddleocr' => $primary, 'tesseract' => $fallback];

        return [$merged, array_values(array_unique($uncertain))];
    }

    private function same(string $field, mixed $first, mixed $second): bool
    {
        if ($field === 'amount') {
            return abs((float) $first - (float) $second) < .01;
        }

        return mb_strtoupper(preg_replace('/\s+/', '', (string) $first)) === mb_strtoupper(preg_replace('/\s+/', '', (string) $second));
    }

    private function reviewReason(string $status, array $quality, array $fields, array $uncertain, array $duplicate): ?string
    {
        if ($status === ReceiptSubmission::REUPLOAD_REQUIRED) {
            if (($quality['readability'] ?? '') === 'unreadable') {
                return 'The receipt image is too unclear to read reliably. Please upload a clearer copy of the original receipt.';
            }
            if (in_array('reference_number', $uncertain, true)) {
                return 'The OCR engines disagree on the transaction/reference number. Please upload a clearer copy; AMIS will not guess financial details.';
            }
            if (empty($fields['normalized_reference'])) {
                return 'Transaction or reference number could not be read reliably. Please upload a clearer copy of the original receipt.';
            }

            return 'A critical financial field could not be read reliably. Please upload a clearer original receipt.';
        }
        if ($duplicate['status'] !== 'UNIQUE') {
            return 'A duplicate indicator was found. Finance must compare this receipt with the earlier submission.';
        }
        if ($status === ReceiptSubmission::NEEDS_REVIEW) {
            return 'Some OCR or validation checks require Finance review.';
        }

        return null;
    }
}
