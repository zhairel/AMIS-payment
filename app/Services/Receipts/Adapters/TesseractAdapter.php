<?php

namespace App\Services\Receipts\Adapters;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class TesseractAdapter implements OcrEngineAdapterInterface
{
    public function getEngineName(): string
    {
        return 'Tesseract';
    }

    public function checkAvailability(): array
    {
        $url = config('services.receipt_ocr.url');
        if (! empty($url)) {
            try {
                $response = Http::timeout(3)->get(rtrim($url, '/').'/health');
                if ($response->successful()) {
                    return [
                        'available' => true,
                        'version' => 'Tesseract Microservice (Docker)',
                        'reason' => null,
                    ];
                }
            } catch (Throwable $e) {
                // Fall back to CLI check below
            }
        }

        $python = config('services.receipt_ocr.python', config('services.paddle_ocr.python', 'python3'));
        $script = base_path('scripts/ocr_engine_runner.py');
        $process = new Process([$python, $script, 'check_env']);
        $process->run();

        if ($process->isSuccessful()) {
            $data = json_decode($process->getOutput(), true);

            return $data['engines']['tesseract'] ?? ['available' => false, 'reason' => 'Check failed'];
        }

        return ['available' => false, 'reason' => 'Python script check failed: '.$process->getErrorOutput()];
    }

    public function scan(string $filePath): array
    {
        $url = config('services.receipt_ocr.url');
        if (! empty($url)) {
            try {
                $response = Http::timeout(60)
                    ->attach('receipt', file_get_contents($filePath), basename($filePath))
                    ->post(rtrim($url, '/').'/scan', [
                        'engine' => 'tesseract',
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    if (is_array($result)) {
                        return $result;
                    }
                }
            } catch (Throwable $e) {
                // Fall back to CLI process below
            }
        }

        $python = config('services.receipt_ocr.python', config('services.paddle_ocr.python', 'python3'));
        $script = base_path('scripts/ocr_engine_runner.py');

        $process = new Process([$python, $script, 'tesseract', $filePath]);
        $process->setTimeout(60)->run();

        if (! $process->isSuccessful()) {
            return [
                'engine' => 'Tesseract',
                'status' => 'FAILED',
                'raw_text' => '',
                'regions' => 0,
                'confidence' => null,
                'duration_ms' => 0,
                'error' => 'Process error: '.$process->getErrorOutput(),
            ];
        }

        $result = json_decode($process->getOutput(), true);

        return is_array($result) ? $result : [
            'engine' => 'Tesseract',
            'status' => 'FAILED',
            'raw_text' => '',
            'regions' => 0,
            'confidence' => null,
            'duration_ms' => 0,
            'error' => 'Invalid JSON returned from python runner',
        ];
    }
}
