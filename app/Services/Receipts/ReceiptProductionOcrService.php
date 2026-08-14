<?php

namespace App\Services\Receipts;

use App\Services\Receipts\Adapters\DocTrAdapter;
use App\Services\Receipts\Adapters\OcrEngineAdapterInterface;
use App\Services\Receipts\Adapters\TesseractAdapter;

class ReceiptProductionOcrService
{
    public function __construct(
        private readonly ReceiptImagePreprocessorService $preprocessor,
        private readonly TesseractAdapter $tesseract,
        private readonly DocTrAdapter $docTr,
        private readonly ReceiptFieldNormalizer $normalizer,
        private readonly ReceiptValidationService $validator,
        private readonly ReceiptFieldConsensusService $consensus,
    ) {}

    /**
     * Production OCR policy: Tesseract first, then docTR only when the complete
     * Tesseract result is unreliable. Paperless and all other engines are
     * intentionally excluded.
     *
     * @return array<string, mixed>
     */
    public function analyze(string $originalPath): array
    {
        $preprocessing = $this->preprocessor->preprocess($originalPath);
        $enhancedPath = $preprocessing['temp_enhanced_path'] ?? null;
        $useEnhanced = ($preprocessing['image_type'] ?? null) === 'CAMERA_PHOTO'
            && is_string($enhancedPath)
            && is_file($enhancedPath);
        $preferredPath = $useEnhanced ? $enhancedPath : $originalPath;
        $preferredVariant = $useEnhanced ? 'ocr_enhanced' : 'original';
        $attempts = [];
        $tesseractAttempts = [];

        try {
            $this->logInfo('[OCR] Engine selected: Tesseract');
            $tesseractAttempts[] = $this->run($this->tesseract, 'tesseract', $preferredPath, $preferredVariant);
            $attempts = $tesseractAttempts;
            $tesseractResult = $this->mergeAttempts($tesseractAttempts);
            $tesseractValidation = $this->validator->validate(
                $tesseractResult['fields'],
                $tesseractResult['uncertain_fields']
            );

            if ($useEnhanced && $this->validator->needsFallback(
                $tesseractResult['fields'],
                $tesseractResult['confidence'],
                $tesseractValidation,
                $this->fallbackContext($attempts, $preprocessing)
            )) {
                $tesseractAttempts[] = $this->run($this->tesseract, 'tesseract', $originalPath, 'original');
                $attempts = $tesseractAttempts;
                $tesseractResult = $this->mergeAttempts($tesseractAttempts);
                $tesseractValidation = $this->validator->validate(
                    $tesseractResult['fields'],
                    $tesseractResult['uncertain_fields']
                );
            }

            $fallbackUsed = $this->validator->needsFallback(
                $tesseractResult['fields'],
                $tesseractResult['confidence'],
                $tesseractValidation,
                $this->fallbackContext($attempts, $preprocessing)
            );

            if ($fallbackUsed) {
                $this->logInfo('[OCR] docTR fallback triggered');
                $docTrAttempt = $this->run($this->docTr, 'doctr', $preferredPath, $preferredVariant);
                $attempts[] = $docTrAttempt;
                $final = $this->mergeAttempts([
                    $this->aggregateEngineResult('Tesseract', 'tesseract', $tesseractResult, $tesseractAttempts),
                    $docTrAttempt,
                ]);
            } else {
                $final = $tesseractResult;
            }

            $rawCombined = collect($attempts)->pluck('raw_text')->filter()->implode("\n");
            $this->logInfo('[OCR] Raw text extracted', ['raw_text' => $rawCombined]);
            $this->logInfo('[OCR] Parsed amount: ' . ($final['fields']['amount'] ?? 'null'));
            $this->logInfo('[OCR] Parsed reference: ' . ($final['fields']['reference_number'] ?? 'null'));
            $this->logInfo('[OCR] Parsed date: ' . ($final['fields']['transaction_date'] ?? 'null'));

            return [
                'fields' => $final['fields'],
                'uncertain_fields' => $final['uncertain_fields'],
                'attempts' => $attempts,
                'confidence' => $final['confidence'],
                'fallback_used' => $fallbackUsed,
                'preprocessing' => $preprocessing,
                'ocr_status' => $this->ocrStatus($attempts, $final['fields'], $final['uncertain_fields']),
            ];
        } finally {
            $this->preprocessor->cleanupTempFile(is_string($enhancedPath) ? $enhancedPath : null);
        }
    }

    private function logInfo(string $message, array $context = []): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class) && \Illuminate\Support\Facades\Log::getFacadeRoot()) {
                \Illuminate\Support\Facades\Log::info($message, $context);
            }
        } catch (\Throwable) {
        }
    }

    /** @return array<string, mixed> */
    private function run(OcrEngineAdapterInterface $adapter, string $engineKey, string $path, string $variant): array
    {
        try {
            $raw = $adapter->scan($path);
        } catch (\Throwable $exception) {
            $raw = [
                'engine' => $adapter->getEngineName(),
                'status' => 'FAILED',
                'raw_text' => '',
                'confidence' => null,
                'duration_ms' => 0,
                'error' => $exception->getMessage(),
            ];
        }

        $status = strtoupper((string) ($raw['status'] ?? 'FAILED'));
        $success = $status === 'SUCCESS' && filled($raw['raw_text'] ?? null);
        $raw['success'] = $success;

        return [
            'engine' => $adapter->getEngineName(),
            'engine_key' => $engineKey.'_'.$variant,
            'variant' => $variant,
            'status' => $status,
            'success' => $success,
            'raw_text' => (string) ($raw['raw_text'] ?? ''),
            'confidence' => is_numeric($raw['confidence'] ?? null) ? (float) $raw['confidence'] : null,
            'duration_ms' => $raw['duration_ms'] ?? null,
            'error' => $raw['error'] ?? null,
            'raw' => $raw,
            'parsed' => $this->normalizer->fromOcr($raw),
        ];
    }

    /** @return array{fields: array<string, mixed>, uncertain_fields: array<int, string>, confidence: ?float} */
    private function mergeAttempts(array $attempts): array
    {
        $merged = $this->consensus->merge($attempts);
        $confidences = collect($attempts)
            ->where('success', true)
            ->pluck('confidence')
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        return [
            'fields' => $merged['fields'],
            'uncertain_fields' => $merged['uncertain_fields'],
            'confidence' => $confidences->isEmpty() ? null : (float) $confidences->avg(),
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateEngineResult(string $engine, string $engineKey, array $merged, array $attempts): array
    {
        return [
            'engine' => $engine,
            'engine_key' => $engineKey,
            'variant' => 'best_safe_variant',
            'status' => collect($attempts)->contains('success', true) ? 'SUCCESS' : 'FAILED',
            'success' => collect($attempts)->contains('success', true),
            'raw_text' => collect($attempts)->pluck('raw_text')->filter()->implode("\n"),
            'confidence' => $merged['confidence'],
            'parsed' => $merged['fields'],
        ];
    }

    /** @return array<string, mixed> */
    private function fallbackContext(array $attempts, array $preprocessing): array
    {
        return [
            'raw_text' => collect($attempts)->pluck('raw_text')->filter()->implode("\n"),
            'image_type' => $preprocessing['image_type'] ?? null,
            'blur_status' => $preprocessing['blur_status'] ?? null,
            'glare_detected' => (bool) ($preprocessing['glare_detected'] ?? false),
        ];
    }

    private function ocrStatus(array $attempts, array $fields, array $uncertain): string
    {
        if (! collect($attempts)->contains('success', true)) {
            return 'OCR_FAILED';
        }

        $criticalComplete = filled($fields['provider'] ?? null)
            && ($fields['provider'] ?? null) !== 'Other / Unknown'
            && filled($fields['normalized_reference'] ?? null)
            && is_numeric($fields['amount'] ?? null)
            && filled($fields['transaction_date'] ?? null)
            && filled($fields['transaction_status'] ?? null);

        return $criticalComplete && $uncertain === [] ? 'OCR_SUCCESS' : 'OCR_PARTIAL';
    }
}
