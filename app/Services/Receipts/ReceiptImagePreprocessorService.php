<?php

namespace App\Services\Receipts;

use Symfony\Component\Process\Process;
use Throwable;

class ReceiptImagePreprocessorService
{
    public function preprocess(string $imagePath): array
    {
        $basePath = dirname(__DIR__, 3);
        try {
            if (function_exists('base_path')) {
                $basePath = base_path();
            }
        } catch (\Throwable) {
        }

        $python = $basePath . '/.venv-ocr/bin/python';
        $script = $basePath . '/scripts/receipt_preprocessor.py';

        try {
            $process = new Process([$python, $script, $imagePath]);
            $process->setTimeout(30)->run();

            if ($process->isSuccessful()) {
                $data = json_decode($process->getOutput(), true);
                if (is_array($data) && ($data['status'] ?? '') === 'SUCCESS') {
                    return $data;
                }
            }

            return [
                'status' => 'FAILED',
                'image_type' => 'UNKNOWN',
                'temp_enhanced_path' => null,
                'document_detected' => false,
                'crop_applied' => false,
                'perspective_corrected' => false,
                'rotation_applied' => 0,
                'deskew_angle' => 0.0,
                'blur_score' => 0,
                'blur_status' => 'UNKNOWN',
                'glare_detected' => false,
                'quality_score' => 100,
                'preprocessing_status' => 'FALLBACK',
                'reupload_required' => false,
                'user_message' => null,
                'error' => 'Preprocessor script error: ' . $process->getErrorOutput(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'FAILED',
                'image_type' => 'UNKNOWN',
                'temp_enhanced_path' => null,
                'document_detected' => false,
                'crop_applied' => false,
                'perspective_corrected' => false,
                'rotation_applied' => 0,
                'deskew_angle' => 0.0,
                'blur_score' => 0,
                'blur_status' => 'UNKNOWN',
                'glare_detected' => false,
                'quality_score' => 100,
                'preprocessing_status' => 'FALLBACK',
                'reupload_required' => false,
                'user_message' => null,
                'error' => 'Preprocessor exception: ' . $e->getMessage(),
            ];
        }
    }

    public function cleanupTempFile(?string $tempPath): void
    {
        if ($tempPath && file_exists($tempPath) && str_contains($tempPath, 'amis_ocr_prep_')) {
            @unlink($tempPath);
        }
    }
}
