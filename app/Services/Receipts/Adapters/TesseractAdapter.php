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
                        'version' => 'Tesseract Microservice (Docker)',
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
                if (isset($data['engines']['tesseract']) && $data['engines']['tesseract']['available']) {
                    return $data['engines']['tesseract'];
                }
            }
        }

        $tesBin = $this->resolveTesseractBinary();
        if ($tesBin) {
            return [
                'available' => true,
                'version' => 'Tesseract CLI ('.$tesBin.')',
                'reason' => null,
            ];
        }

        return ['available' => false, 'reason' => 'Neither Tesseract microservice nor CLI binary is available.'];
    }

    public function scan(string $filePath): array
    {
        $startTime = microtime(true);

        // 1. Try OCR Microservice with fast connect timeout
        $url = config('services.receipt_ocr.url');
        $token = config('services.receipt_ocr.token');
        if (! empty($url)) {
            try {
                $client = Http::connectTimeout(3)->timeout(35);
                if (! empty($token)) {
                    $client = $client->withToken($token);
                }
                $response = $client
                    ->attach('receipt', file_get_contents($filePath), basename($filePath))
                    ->post(rtrim($url, '/').'/api/scan', [
                        'engine' => 'tesseract',
                    ]);

                // Also support legacy /scan endpoint if /api/scan is 404
                if ($response->status() === 404) {
                    $response = $client
                        ->attach('receipt', file_get_contents($filePath), basename($filePath))
                        ->post(rtrim($url, '/').'/scan', [
                            'engine' => 'tesseract',
                        ]);
                }

                if ($response->successful()) {
                    $result = $response->json();
                    if (is_array($result) && ($result['status'] ?? '') === 'SUCCESS' && filled($result['raw_text'] ?? null)) {
                        return $result;
                    }
                }
            } catch (Throwable $e) {
                // Fall back to local CLI
            }
        }

        // 2. Try Python runner
        try {
            $python = $this->resolvePythonBinary();
            $script = base_path('scripts/ocr_engine_runner.py');

            if (is_file($script)) {
                $process = new Process([$python, $script, 'tesseract', $filePath]);
                $process->setTimeout(30)->run();

                if ($process->isSuccessful()) {
                    $result = json_decode($process->getOutput(), true);
                    if (is_array($result) && ($result['status'] ?? '') === 'SUCCESS' && filled($result['raw_text'] ?? null)) {
                        return $result;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall back to direct tesseract binary
        }

        // 3. Direct System Tesseract CLI execution (Zero Python dependency)
        try {
            $tesBin = $this->resolveTesseractBinary();
            if ($tesBin) {
                $process = new Process([$tesBin, $filePath, 'stdout', '-l', 'eng', '--psm', '6']);
                $process->setTimeout(20)->run();

                if ($process->isSuccessful()) {
                    $rawText = trim($process->getOutput());
                    if (filled($rawText)) {
                        $lines = array_values(array_filter(explode("\n", $rawText), fn ($l) => filled(trim($l))));
                        $duration = (int) round((microtime(true) - $startTime) * 1000);

                        return [
                            'engine' => 'Tesseract',
                            'status' => 'SUCCESS',
                            'raw_text' => $rawText,
                            'regions' => count($lines),
                            'confidence' => 0.85,
                            'duration_ms' => $duration,
                            'error' => null,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            // All options exhausted
        }

        $duration = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'engine' => 'Tesseract',
            'status' => 'FAILED',
            'raw_text' => '',
            'regions' => 0,
            'confidence' => null,
            'duration_ms' => $duration,
            'error' => 'Tesseract scan could not extract text.',
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

    private function resolveTesseractBinary(): ?string
    {
        $venvTes = base_path('.venv-ocr/bin/tesseract');
        if (is_file($venvTes) && is_executable($venvTes)) {
            return $venvTes;
        }

        foreach (['/usr/bin/tesseract', '/usr/local/bin/tesseract'] as $bin) {
            if (is_file($bin) && is_executable($bin)) {
                return $bin;
            }
        }

        $which = new Process(['which', 'tesseract']);
        $which->run();
        if ($which->isSuccessful() && filled(trim($which->getOutput()))) {
            return trim($which->getOutput());
        }

        return null;
    }
}
