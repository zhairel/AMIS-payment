<?php

namespace App\Services\Receipts\Adapters;

use Symfony\Component\Process\Process;

class OcrMyPdfAdapter implements OcrEngineAdapterInterface
{
    public function getEngineName(): string
    {
        return 'OCRmyPDF Pipeline';
    }

    public function checkAvailability(): array
    {
        $python = config('services.paddle_ocr.python', base_path('.venv-ocr/bin/python'));
        $script = base_path('scripts/ocr_engine_runner.py');
        $process = new Process([$python, $script, 'check_env']);
        $process->run();

        if ($process->isSuccessful()) {
            $data = json_decode($process->getOutput(), true);
            return $data['engines']['ocrmypdf'] ?? ['available' => false, 'reason' => 'Check failed'];
        }

        return ['available' => false, 'reason' => 'Python script check failed: ' . $process->getErrorOutput()];
    }

    public function scan(string $filePath): array
    {
        $python = config('services.paddle_ocr.python', base_path('.venv-ocr/bin/python'));
        $script = base_path('scripts/ocr_engine_runner.py');

        $process = new Process([$python, $script, 'ocrmypdf', $filePath]);
        $process->setTimeout(90)->run();

        if (! $process->isSuccessful()) {
            return [
                'engine' => 'OCRmyPDF Pipeline',
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
            'engine' => 'OCRmyPDF Pipeline',
            'status' => 'FAILED',
            'raw_text' => '',
            'regions' => 0,
            'confidence' => null,
            'duration_ms' => 0,
            'error' => 'Invalid JSON returned from python runner',
        ];
    }
}
