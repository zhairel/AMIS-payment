<?php

namespace App\Services\Receipts\Adapters;

use Symfony\Component\Process\Process;

class EasyOcrAdapter implements OcrEngineAdapterInterface
{
    public function getEngineName(): string
    {
        return 'EasyOCR';
    }

    public function checkAvailability(): array
    {
        $python = config('services.paddle_ocr.python', 'python3');
        $script = base_path('scripts/ocr_engine_runner.py');
        $process = new Process([$python, $script, 'check_env']);
        $process->run();

        if ($process->isSuccessful()) {
            $data = json_decode($process->getOutput(), true);
            return $data['engines']['easyocr'] ?? ['available' => false, 'reason' => 'Check failed'];
        }

        return ['available' => false, 'reason' => 'Python script check failed: ' . $process->getErrorOutput()];
    }

    public function scan(string $filePath): array
    {
        $python = config('services.paddle_ocr.python', 'python3');
        $script = base_path('scripts/ocr_engine_runner.py');

        $process = new Process([$python, $script, 'easyocr', $filePath]);
        $process->setTimeout(60)->run();

        if (! $process->isSuccessful()) {
            return [
                'engine' => 'EasyOCR',
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
            'engine' => 'EasyOCR',
            'status' => 'FAILED',
            'raw_text' => '',
            'regions' => 0,
            'confidence' => null,
            'duration_ms' => 0,
            'error' => 'Invalid JSON returned from python runner',
        ];
    }
}
