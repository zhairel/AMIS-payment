<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ReceiptOcrComparatorService;
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

    public function checkEnv(ReceiptOcrComparatorService $comparator): JsonResponse
    {
        return response()->json($comparator->checkEnvironmentDiagnostics());
    }

    public function compare(
        Request $request,
        ReceiptOcrComparatorService $comparator
    ): JsonResponse {
        $request->validate([
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'test_id' => ['nullable', 'string'],
            'expected_provider' => ['nullable', 'string'],
            'expected_reference' => ['nullable', 'string'],
            'expected_date' => ['nullable', 'string'],
            'expected_amount' => ['nullable', 'string'],
            'expected' => ['nullable', 'array'],
        ]);

        $fullPath = null;
        $file = $request->file('image') ?: $request->file('receipt');

        if ($file) {
            $testId = (string) Str::uuid();
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png');
            $testPath = $file->storeAs("private/receipt-tests/{$testId}", "test.{$extension}", 'local');
            $fullPath = Storage::disk('local')->path($testPath);
        } elseif ($request->filled('test_id')) {
            $files = Storage::disk('local')->files("private/receipt-tests/" . $request->input('test_id'));
            if (! empty($files)) {
                $fullPath = Storage::disk('local')->path($files[0]);
            }
        }

        if (empty($fullPath) || ! file_exists($fullPath)) {
            return response()->json([
                'success' => false,
                'comparison_status' => 'FAILED',
                'message' => 'No valid test receipt image provided for comparison.',
            ], 400);
        }

        $expectedValues = [
            'provider' => $request->input('expected_provider') ?: $request->input('expected.provider'),
            'reference' => $request->input('expected_reference') ?: $request->input('expected.reference'),
            'date' => $request->input('expected_date') ?: $request->input('expected.date'),
            'amount' => $request->input('expected_amount') ?: $request->input('expected.amount'),
        ];

        $comparison = $comparator->compareAllEngines($fullPath, $expectedValues);
        $status = $comparison['comparison_status'] ?? 'SUCCESS';

        return response()->json([
            'success' => ($status === 'SUCCESS' || $status === 'PARTIAL_SUCCESS'),
            'comparison_status' => $status,
            'debug_message' => $comparison['debug_message'] ?? null,
            'comparison' => $comparison,
            'engines' => $comparison['engines'] ?? [],
            'consensus' => $comparison['consensus'] ?? null,
        ]);
    }

    public function process(
        Request $request,
        ReceiptOcrPipeline $pipeline
    ): JsonResponse {
        $startTime = microtime(true);
        $stage = 'FILE_PREPARATION';

        try {
            $stage = 'IMAGE_VALIDATION';
            $file = $request->file('image') ?: $request->file('receipt');
            if (! $file || ! $file->isValid()) {
                return $this->failureResponse('IMAGE_VALIDATION', 'No valid image file was uploaded.', 'Missing image/receipt input', $startTime, 'image.png', 0);
            }
            $originalFilename = Str::limit($file->getClientOriginalName(), 255, '');
            $sizeBytes = $file->getSize();

            $testId = (string) Str::uuid();
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png');
            $testPath = $file->storeAs("private/receipt-tests/{$testId}", "test.{$extension}", 'local');
            $fullPath = Storage::disk('local')->path($testPath);

            if (! file_exists($fullPath)) {
                return $this->failureResponse('PREPROCESSING', 'Uploaded test image file could not be stored in test storage.', 'File missing at path: ' . $testPath, $startTime, $originalFilename ?? 'image.png', $sizeBytes ?? 0);
            }

            $ocrResult = $pipeline->processReceipt($fullPath);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            if (($ocrResult['status'] ?? '') === 'SUCCESS') {
                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Receipt verification completed successfully.',
                    'verification' => [
                        'provider' => $ocrResult['provider'] ?? 'Other / Unknown',
                        'mode' => $ocrResult['mode'] ?? null,
                        'reference_number' => $ocrResult['reference_number'] ?? null,
                        'normalized_reference' => $ocrResult['normalized_reference'] ?? null,
                        'amount' => $ocrResult['amount'] ?? null,
                        'currency' => $ocrResult['currency'] ?? 'PHP',
                        'transaction_date' => $ocrResult['transaction_date'] ?? null,
                        'transaction_time' => $ocrResult['transaction_time'] ?? null,
                        'sender_name' => $ocrResult['sender_name'] ?? null,
                        'receiver_name' => $ocrResult['receiver_name'] ?? null,
                        'transaction_status' => $ocrResult['transaction_status'] ?? 'SUCCESS',
                    ],
                    'technical_details' => [
                        'test_id' => $testId,
                        'original_filename' => $originalFilename,
                        'size_bytes' => $sizeBytes,
                        'total_processing_time_ms' => $durationMs,
                        'pipeline_stage' => 'COMPLETED',
                        'raw_ocr_length' => mb_strlen($ocrResult['raw_text'] ?? ''),
                        'extraction_method' => $ocrResult['extraction_method'] ?? 'Standard Pipeline',
                        'parsed_fields' => $ocrResult,
                    ],
                ]);
            }

            return $this->failureResponse(
                'OCR_SCAN',
                $ocrResult['message'] ?? 'Failed to extract financial details from receipt.',
                $ocrResult['error'] ?? 'Unreadable receipt OCR',
                $startTime,
                $originalFilename,
                $sizeBytes
            );
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'FAILED',
                'message' => 'Receipt parsing failed during ' . $stage,
                'technical_details' => [
                    'pipeline_stage' => $stage,
                    'error_type' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'total_processing_time_ms' => $durationMs,
                ],
            ], 500);
        }
    }

    private function failureResponse(string $stage, string $message, string $debugError, float $startTime, string $filename, int $sizeBytes): JsonResponse
    {
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'status' => 'FAILED',
            'message' => $message,
            'technical_details' => [
                'pipeline_stage' => $stage,
                'error_type' => 'ValidationOrExtractionFailure',
                'error_message' => $debugError,
                'original_filename' => $filename,
                'size_bytes' => $sizeBytes,
                'total_processing_time_ms' => $durationMs,
            ],
        ], 400);
    }
}
