<?php

namespace App\Services\Receipts\Adapters;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class DocTrAdapter implements OcrEngineAdapterInterface
{
    public function getEngineName(): string
    {
        return 'docTR';
    }

    public function checkAvailability(): array
    {
        $url = config('services.receipt_ocr.url');
        $token = config('services.receipt_ocr.token');
        if (! empty($url)) {
            try {
                $client = Http::connectTimeout(2)->timeout(3);
                if (! empty($token)) {
                    $client = $client->withToken($token);
                }
                $response = $client->get(rtrim($url, '/').'/health');
                if ($response->successful()) {
                    return [
                        'available' => true,
                        'version' => 'docTR Microservice (Docker)',
                        'reason' => null,
                    ];
                }
            } catch (Throwable $e) {
                // Fall back to CLI check below
            }
        }

        $python = $this->resolvePythonBinary();
        $script = base_path('scripts/ocr_engine_runner.py');
        if (is_file($script)) {
            $process = new Process([$python, $script, 'check_env']);
            $process->run();

            if ($process->isSuccessful()) {
                $data = json_decode($process->getOutput(), true);

                return $data['engines']['doctr'] ?? ['available' => false, 'reason' => 'Check failed'];
            }
        }

        return ['available' => false, 'reason' => 'docTR environment is not available.'];
    }

    public function scan(string $filePath): array
    {
        $startTime = microtime(true);

        // 1. Try OCR Microservice with fast connect timeout
        $url = config('services.receipt_ocr.url');
        $token = config('services.receipt_ocr.token');
        if (! empty($url)) {
            try {
                $client = Http::connectTimeout(3)->timeout(45);
                if (! empty($token)) {
                    $client = $client->withToken($token);
                }
                $response = $client
                    ->attach('receipt', file_get_contents($filePath), basename($filePath))
                    ->post(rtrim($url, '/').'/api/scan', [
                        'engine' => 'doctr',
                    ]);

                // Also support legacy /scan endpoint if /api/scan is 404
                if ($response->status() === 404) {
                    $response = $client
                        ->attach('receipt', file_get_contents($filePath), basename($filePath))
                        ->post(rtrim($url, '/').'/scan', [
                            'engine' => 'doctr',
                        ]);
                }

                if ($response->successful()) {
                    $result = $response->json();
                    if (is_array($result) && ($result['status'] ?? '') === 'SUCCESS' && filled($result['raw_text'] ?? null)) {
                        return $result;
                    }
                }
            } catch (Throwable $e) {
                // Fall back to CLI process below
            }
        }

        // 2. Try Python runner
        try {
            $python = $this->resolvePythonBinary();
            $script = base_path('scripts/ocr_engine_runner.py');

            if (is_file($script)) {
                $process = new Process([$python, $script, 'doctr', $filePath]);
                $process->setTimeout(45)->run();

                if ($process->isSuccessful()) {
                    $result = json_decode($process->getOutput(), true);
                    if (is_array($result) && ($result['status'] ?? '') === 'SUCCESS' && filled($result['raw_text'] ?? null)) {
                        return $result;
                    }
                }
            }
        } catch (Throwable $e) {
            // Failed
        }

        $duration = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'engine' => 'docTR',
            'status' => 'FAILED',
            'raw_text' => '',
            'regions' => 0,
            'confidence' => null,
            'duration_ms' => $duration,
            'error' => 'docTR execution failed.',
        ];
    }

    private function resolvePythonBinary(): string
    {
        $configured = config('services.receipt_ocr.python', config('services.paddle_ocr.python'));
        if (filled($configured) && (is_file($configured) || ! str_contains($configured, '/'))) {
            return $configured;
        }

        $venv = base_path('.venv-ocr/bin/python');
        if (is_file($venv) && is_executable($venv)) {
            return $venv;
        }

        foreach (['/usr/bin/python3', '/usr/local/bin/python3', 'python3', '/usr/bin/python', 'python'] as $bin) {
            if (! str_contains($bin, '/') || (is_file($bin) && is_executable($bin))) {
                return $bin;
            }
        }

        return 'python3';
    }
}
