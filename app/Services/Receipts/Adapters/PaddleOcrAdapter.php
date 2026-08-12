<?php

namespace App\Services\Receipts\Adapters;

use Symfony\Component\Process\Process;

class PaddleOcrAdapter implements OcrEngineAdapterInterface
{
    public function getEngineName(): string
    {
        return 'PaddleOCR PP-OCRv6';
    }

    public function checkAvailability(): array
    {
        $python = config('services.paddle_ocr.python', 'python3');
        $script = base_path('scripts/ocr_engine_runner.py');
        $process = new Process([$python, $script, 'check_env']);
        $process->run();

        if ($process->isSuccessful()) {
            $data = json_decode($process->getOutput(), true);
            return $data['engines']['paddleocr'] ?? ['available' => false, 'reason' => 'Check failed'];
        }

        return ['available' => false, 'reason' => 'Python script check failed: ' . $process->getErrorOutput()];
    }

    public function scan(string $filePath): array
    {
        $python = config('services.paddle_ocr.python', 'python3');
        $script = base_path('scripts/ocr_engine_runner.py');

        $process = new Process([$python, $script, 'paddleocr', $filePath]);
        $process->setTimeout(60)->run();

        if (! $process->isSuccessful()) {
            return [
                'engine' => 'PaddleOCR PP-OCRv6',
                'status' => 'FAILED',
                'raw_text' => '',
                'regions' => 0,
                'confidence' => null,
                'duration_ms' => 0,
                'error' => 'Process error: ' . $process->getErrorOutput(),
            ];
        }

        $result = json_decode($process->getOutput(), true);

        return is_array($result) ? $result : [
            'engine' => 'PaddleOCR PP-OCRv6',
            'status' => 'FAILED',
            'raw_text' => '',
            'regions' => 0,
            'confidence' => null,
            'duration_ms' => 0,
            'error' => 'Invalid JSON returned from python runner',
        ];
    }
}
