<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ReceiptOcrPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminReceiptTestController extends Controller
{
    public function index()
    {
        return view('admin.receipt_test_lab');
    }

    public function process(
        Request $request,
        ReceiptOcrPipeline $pipeline
    ): JsonResponse {
        $startTime = microtime(true);
        $stage = 'FILE_PREPARATION';

        try {
            // Stage 1: IMAGE_VALIDATION
            $stage = 'IMAGE_VALIDATION';
            $request->validate([
                'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            ]);

            $file = $request->file('image');
            $originalFilename = Str::limit($file->getClientOriginalName(), 255, '');
            $sizeBytes = $file->getSize();

            $testId = (string) Str::uuid();
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png');
            $testPath = $file->storeAs("private/receipt-tests/{$testId}", "test.{$extension}", 'local');
            $fullPath = Storage::disk('local')->path($testPath);

            if (!file_exists($fullPath)) {
                return $this->failureResponse('PREPROCESSING', 'Uploaded test image file could not be stored in test storage.', 'File missing at path: ' . $testPath, $startTime, $originalFilename ?? 'image.png', $sizeBytes ?? 0);
            }

            // Stage 2: OCR_REQUEST & PIPELINE ANALYSIS
            $stage = 'OCR_REQUEST';
            $analysis = $pipeline->analyzeFile($fullPath);

            // Stage 3: FIELD_PARSING & NORMALIZATION
            $stage = 'FIELD_PARSING';
            $fields = $analysis['fields'] ?? [];
            $classification = $analysis['classification'] ?? [];
            $duplicate = $analysis['duplicate'] ?? [];
            $validation = $analysis['validation'] ?? [];
            $quality = $analysis['quality_assessment'] ?? [];

            // Safe extraction of standardized fields
            $provider = !empty($fields['provider']) ? (string) $fields['provider'] : 'Other / Unknown';
            $referenceNumber = !empty($fields['reference_number']) ? (string) $fields['reference_number'] : null;
            $transactionDate = !empty($fields['transaction_date']) ? (string) $fields['transaction_date'] : null;
            $transactionTime = !empty($fields['transaction_time']) ? (string) $fields['transaction_time'] : null;
            $amount = (isset($fields['amount']) && is_numeric($fields['amount'])) ? (float) $fields['amount'] : null;
            $currency = !empty($fields['currency']) ? (string) $fields['currency'] : 'PHP';

            // Stage 4: FINAL_VALIDATION & CLASSIFICATION
            $stage = 'FINAL_VALIDATION';
            $status = 'passed';
            $label = 'VALID RECEIPT';
            $message = 'Receipt successfully scanned and verified.';

            $errorMessages = collect($validation['errors'] ?? [])
                ->map(fn ($err) => is_array($err) ? ($err['message'] ?? '') : (string) $err)
                ->filter()
                ->values()
                ->all();

            if (($classification['type'] ?? '') === 'not_receipt') {
                $status = 'not_a_receipt';
                $label = 'NOT A RECEIPT';
                $message = 'No valid payment transaction information was detected in this image.';
                $provider = 'Other / Unknown';
                $referenceNumber = null;
                $transactionDate = null;
                $transactionTime = null;
                $amount = null;
            } elseif (($duplicate['status'] ?? 'UNIQUE') !== 'UNIQUE') {
                $status = 'duplicate';
                $label = 'DUPLICATE RECEIPT';
                $message = 'This receipt or transaction reference has already been submitted.';
            } elseif (($quality['readability'] ?? '') === 'unreadable') {
                $status = 'unreadable';
                $label = 'UNREADABLE';
                $message = 'The receipt image is too unclear to read reliably.';
            } elseif (!($validation['valid'] ?? true)) {
                $status = 'needs_review';
                $label = (empty($referenceNumber) || $amount === null) ? 'MISSING IMPORTANT DETAILS' : 'NEEDS REVIEW';
                $message = !empty($errorMessages) ? implode(' ', $errorMessages) : 'Some receipt details require manual verification.';
            } elseif (($classification['type'] ?? '') === 'uncertain') {
                $status = 'needs_review';
                $label = 'NEEDS REVIEW';
                $message = $classification['message'] ?? 'Some receipt details could not be confirmed.';
            }

            $endTime = microtime(true);
            $durationMs = (int) round(($endTime - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'test_id' => $testId,
                'original_filename' => $originalFilename,
                'size_formatted' => $this->formatSize($sizeBytes),
                'preview_url' => route('admin.receipt_test.preview', $testId),
                'status' => $status,
                'label' => $label,
                'message' => $message,
                'provider' => $provider,
                'reference_number' => $referenceNumber,
                'transaction_date' => $transactionDate,
                'transaction_time' => $transactionTime,
                'amount' => $amount,
                'currency' => $currency,
                'confidence' => ($analysis['confidence'] ?? null) !== null ? round((float) $analysis['confidence'] * 100) : null,
                'processing_time_ms' => $durationMs,
                'technical_details' => [
                    'ocr_engine' => ($analysis['primary_ocr_engine'] ?? 'PaddleOCR PP-OCRv6') . (($analysis['fallback_used'] ?? false) ? " (Fallback: " . ($analysis['fallback_engine'] ?? 'docTR / Tesseract') . ")" : ''),
                    'image_dimensions' => $analysis['image_dimensions'] ?? 'Unknown',
                    'text_regions_detected' => $analysis['text_regions_count'] ?? 0,
                    'raw_text' => $analysis['raw_text'] ?? 'No text detected',
                    'confidence' => $analysis['confidence'] ?? null,
                    'quality' => $quality,
                    'ai_classification' => $classification,
                    'detected_provider' => $fields['provider'] ?? null,
                    'parsed_fields' => $fields,
                    'parser_result' => $fields['parser_result'] ?? [
                        'provider' => $provider,
                        'mode' => $fields['mode'] ?? null,
                        'reference' => $referenceNumber,
                        'date' => $transactionDate,
                        'time' => $transactionTime,
                        'amount' => $amount,
                        'currency' => $currency,
                    ],
                    'extraction_method' => $fields['extraction_method'] ?? 'Alias Parser / Provider Parser',
                    'normalization_warnings' => $fields['normalization_warnings'] ?? [],
                    'duplicate_lookup' => $duplicate,
                    'validation' => $validation,
                    'processing_duration_ms' => $durationMs,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->failureResponse($stage, 'OCR scan error at stage [' . $stage . ']: ' . $e->getMessage(), $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(), $startTime, $originalFilename ?? 'test.png', $sizeBytes ?? 0);
        }
    }

    private function failureResponse(string $stage, string $message, string $debugMessage, float $startTime, string $filename = 'test_image.png', int $bytes = 0): JsonResponse
    {
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        return response()->json([
            'success' => false,
            'status' => 'failed',
            'label' => 'FAILED',
            'stage' => $stage,
            'message' => $message,
            'debug_message' => $debugMessage,
            'original_filename' => $filename,
            'size_formatted' => $this->formatSize($bytes),
            'provider' => 'Other / Unknown',
            'reference_number' => null,
            'transaction_date' => null,
            'transaction_time' => null,
            'amount' => null,
            'currency' => 'PHP',
            'processing_time_ms' => $durationMs,
            'technical_details' => [
                'stage' => $stage,
                'safe_error' => $message,
                'debug_message' => $debugMessage,
            ],
        ], 200);
    }

    public function preview(string $testId)
    {
        $files = Storage::disk('local')->files("private/receipt-tests/{$testId}");
        if (empty($files)) {
            abort(404);
        }

        $path = $files[0];
        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        return number_format($bytes / 1024, 0) . ' KB';
    }
}
