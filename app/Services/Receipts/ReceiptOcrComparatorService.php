<?php

namespace App\Services\Receipts;

use App\Services\Receipts\Adapters\DocTrAdapter;
use App\Services\Receipts\Adapters\PaperlessNgxAdapter;
use App\Services\Receipts\Adapters\TesseractAdapter;
use Symfony\Component\Process\Process;

class ReceiptOcrComparatorService
{
    public function __construct(
        private readonly ReceiptFieldNormalizer $normalizer,
        private readonly DocTrAdapter $docTr = new DocTrAdapter(),
        private readonly TesseractAdapter $tesseract = new TesseractAdapter(),
        private readonly PaperlessNgxAdapter $paperless = new PaperlessNgxAdapter(),
    ) {}

    public function checkEnvironmentDiagnostics(): array
    {
        $python = config('services.paddle_ocr.python', base_path('.venv-ocr/bin/python'));
        $script = base_path('scripts/ocr_engine_runner.py');
        $process = new Process([$python, $script, 'check_env']);
        $process->run();

        $envData = [
            'python_executable' => $python,
            'python_version' => 'Python 3.12.13',
            'engines' => [
                'doctr' => ['available' => false, 'reason' => 'Diagnostic check script failed'],
                'tesseract' => ['available' => false, 'reason' => 'Diagnostic check script failed'],
            ],
        ];

        if ($process->isSuccessful()) {
            $parsedEnv = json_decode($process->getOutput(), true);
            if (is_array($parsedEnv) && isset($parsedEnv['engines'])) {
                $envData = $parsedEnv;
            }
        }

        // Add real Paperless-ngx REST API connection diagnostics
        $envData['engines']['paperless'] = $this->paperless->checkAvailability();

        return $envData;
    }

    public function compareAllEngines(string $filePath, array $expectedValues = []): array
    {
        $adapters = [
            'doctr' => $this->docTr,
            'tesseract' => $this->tesseract,
            'paperless' => $this->paperless,
        ];

        $results = [];

        foreach ($adapters as $key => $adapter) {
            $engineName = $adapter->getEngineName();
            $rawResult = $adapter->scan($filePath);

            $status = $rawResult['status'] ?? 'FAILED';
            $rawText = (string) ($rawResult['raw_text'] ?? '');
            $rawLength = mb_strlen($rawText);
            $attempted = ($status !== 'NOT_AVAILABLE');

            $parsed = $this->normalizer->fromOcr(['raw_text' => $rawText]);

            $groundTruth = $this->evaluateGroundTruth($parsed, $expectedValues);

            $results[$key] = [
                'key' => $key,
                'engine' => $engineName,
                'status' => $status,
                'attempted' => $attempted,
                'raw_text' => $rawText,
                'raw_text_length' => $rawLength,
                'regions' => $rawResult['regions'] ?? 0,
                'confidence' => $rawResult['confidence'] ?? null,
                'duration_ms' => $rawResult['duration_ms'] ?? 0,
                'error' => $rawResult['error'] ?? null,
                'paperless_document_id' => $rawResult['paperless_document_id'] ?? null,
                'cleanup_status' => $rawResult['cleanup_status'] ?? null,
                'parsed' => $parsed,
                'ground_truth' => $groundTruth,
            ];
        }

        return [
            'environment' => $this->checkEnvironmentDiagnostics(),
            'expected_values' => $expectedValues,
            'engines' => $results,
        ];
    }

    private function evaluateGroundTruth(array $parsed, array $expected): array
    {
        if (empty($expected['provider']) && empty($expected['reference']) && empty($expected['date']) && empty($expected['amount'])) {
            return [
                'has_expected' => false,
                'score_label' => 'No expected ground truth set',
                'correct_count' => 0,
                'total_evaluated' => 0,
                'details' => [],
            ];
        }

        $correct = 0;
        $total = 0;
        $details = [];

        // 1. Provider
        if (! empty($expected['provider'])) {
            $total++;
            $exp = mb_strtolower(trim($expected['provider']));
            $act = mb_strtolower(trim($parsed['provider'] ?? ''));
            $match = str_contains($act, $exp) || str_contains($exp, $act);
            if ($match) {
                $correct++;
            }
            $details['provider'] = ['expected' => $expected['provider'], 'actual' => $parsed['provider'], 'match' => $match];
        }

        // 2. Reference
        if (! empty($expected['reference'])) {
            $total++;
            $exp = $this->normalizer->normalizeReference($expected['reference']);
            $act = $parsed['normalized_reference'] ?? $this->normalizer->normalizeReference($parsed['reference_number'] ?? '');
            $match = ($exp && $act && $exp === $act);
            if ($match) {
                $correct++;
            }
            $details['reference'] = ['expected' => $expected['reference'], 'actual' => $parsed['reference_number'], 'match' => $match];
        }

        // 3. Date
        if (! empty($expected['date'])) {
            $total++;
            $exp = trim($expected['date']);
            $act = trim($parsed['transaction_date'] ?? '');
            $match = ($exp !== '' && $act !== '' && str_contains($act, $exp));
            if ($match) {
                $correct++;
            }
            $details['date'] = ['expected' => $expected['date'], 'actual' => $parsed['transaction_date'], 'match' => $match];
        }

        // 4. Amount
        if (! empty($expected['amount'])) {
            $total++;
            $exp = (float) preg_replace('/[^0-9.]/', '', $expected['amount']);
            $act = is_numeric($parsed['amount'] ?? null) ? (float) $parsed['amount'] : null;
            $match = ($act !== null && abs($exp - $act) < 0.01);
            if ($match) {
                $correct++;
            }
            $details['amount'] = ['expected' => $expected['amount'], 'actual' => $parsed['amount'], 'match' => $match];
        }

        return [
            'has_expected' => true,
            'score_label' => "{$correct}/{$total} Correct",
            'correct_count' => $correct,
            'total_evaluated' => $total,
            'details' => $details,
        ];
    }
}
