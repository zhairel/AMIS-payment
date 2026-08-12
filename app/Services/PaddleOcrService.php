<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PaddleOcrService
{
    /**
     * Try the local PaddleOCR engine. A missing/incompatible Paddle install is
     * an expected condition: the browser automatically falls back to
     * Tesseract OCR without interrupting the parent.
     */
    public function scanReceipt(string $filePath): array
    {
        $empty = ['success' => false, 'status' => 'unavailable', 'engine' => 'PaddleOCR'];

        if (!config('services.paddle_ocr.enabled', true) || !is_file($filePath)) {
            return $empty;
        }

        $python = (string) config('services.paddle_ocr.python', 'python3');
        $script = base_path('scripts/paddle_receipt_ocr.py');
        if (!is_file($script)) {
            return $empty;
        }

        try {
            $process = new Process([$python, $script, $filePath]);
            $process->setTimeout((float) config('services.paddle_ocr.timeout', 75))->run();
            if (!$process->isSuccessful()) {
                Log::warning('PaddleOCR python script returned non-zero code.', [
                    'error' => mb_substr($process->getErrorOutput(), 0, 1000),
                ]);
                return $empty;
            }

            $result = json_decode($process->getOutput(), true);
            if (!is_array($result) || empty($result['raw_text'])) {
                return $empty;
            }

            return array_merge($empty, $result, [
                'success' => true,
                'status' => 'processed',
                'engine' => $result['engine'] ?? 'PaddleOCR PP-OCRv6',
            ]);
        } catch (\Throwable $e) {
            Log::warning('PaddleOCR bridge unavailable; switching to next OCR engine.', [
                'error' => $e->getMessage(),
            ]);
            return $empty;
        }
    }
}
