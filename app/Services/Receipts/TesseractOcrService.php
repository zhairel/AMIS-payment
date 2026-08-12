<?php

namespace App\Services\Receipts;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class TesseractOcrService
{
    public function scan(string $path): array
    {
        $binary = (new ExecutableFinder)->find('tesseract');
        if (! $binary || ! is_file($path)) {
            return ['success' => false, 'status' => 'unavailable', 'engine' => 'Tesseract'];
        }

        $started = microtime(true);
        $process = new Process([$binary, $path, 'stdout', '-l', 'eng', '--psm', '6', 'tsv']);
        $process->setTimeout((float) config('services.tesseract.timeout', 60))->run();
        if (! $process->isSuccessful()) {
            return ['success' => false, 'status' => 'failed', 'engine' => 'Tesseract'];
        }

        $text = [];
        $scores = [];
        foreach (preg_split('/\R/', $process->getOutput()) as $index => $line) {
            if ($index === 0 || trim($line) === '') {
                continue;
            }
            $columns = str_getcsv($line, "\t");
            if (count($columns) < 12 || trim($columns[11]) === '') {
                continue;
            }
            $text[] = $columns[11];
            if (is_numeric($columns[10]) && (float) $columns[10] >= 0) {
                $scores[] = (float) $columns[10] / 100;
            }
        }

        return [
            'success' => count($text) > 0, 'status' => count($text) ? 'processed' : 'failed',
            'engine' => 'Tesseract', 'raw_text' => implode(' ', $text),
            'confidence' => count($scores) ? array_sum($scores) / count($scores) : null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }
}
