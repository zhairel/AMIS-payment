<?php

namespace App\Services\Receipts;

use App\Services\Receipts\Adapters\DocTrAdapter;
use App\Services\Receipts\Adapters\PaperlessNgxAdapter;
use App\Services\Receipts\Adapters\TesseractAdapter;
use Symfony\Component\Process\Process;
use Throwable;

class ReceiptOcrComparatorService
{
    public function __construct(
        private readonly ReceiptFieldNormalizer $normalizer,
        private readonly DocTrAdapter $docTr = new DocTrAdapter(),
        private readonly TesseractAdapter $tesseract = new TesseractAdapter(),
        private readonly PaperlessNgxAdapter $paperless = new PaperlessNgxAdapter(),
        private readonly ReceiptImagePreprocessorService $preprocessor = new ReceiptImagePreprocessorService(),
    ) {}

    public function checkEnvironmentDiagnostics(): array
    {
        $basePath = dirname(__DIR__, 3);
        try {
            if (function_exists('base_path')) {
                $basePath = base_path();
            }
        } catch (Throwable) {
        }

        $python = $basePath . '/.venv-ocr/bin/python';
        $script = $basePath . '/scripts/ocr_engine_runner.py';
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
        // 1. Run Non-Blocking Preprocessing Layer
        $preprocData = [];
        try {
            $preprocData = $this->preprocessor->preprocess($filePath);
        } catch (Throwable $e) {
            $preprocData = [
                'status' => 'SUCCESS',
                'image_type' => 'UNKNOWN',
                'temp_enhanced_path' => null,
                'error' => 'Non-blocking preprocessor exception: ' . $e->getMessage(),
            ];
        }

        $enhancedPath = $preprocData['temp_enhanced_path'] ?? null;

        $adapters = [
            'doctr' => $this->docTr,
            'tesseract' => $this->tesseract,
            'paperless' => $this->paperless,
        ];

        $results = [];
        $debugErrors = [];

        foreach ($adapters as $key => $adapter) {
            $engineName = $adapter->getEngineName();

            // Run OCR on Original Image (wrapped in independent try/catch)
            $rawResultOrig = null;
            try {
                $rawResultOrig = $adapter->scan($filePath);
            } catch (Throwable $e) {
                $rawResultOrig = [
                    'engine' => $engineName,
                    'status' => 'FAILED',
                    'raw_text' => '',
                    'regions' => 0,
                    'confidence' => null,
                    'duration_ms' => 0,
                    'error' => "Adapter exception [{$engineName}]: " . $e->getMessage(),
                ];
            }

            $statusOrig = $rawResultOrig['status'] ?? 'FAILED';
            $rawTextOrig = (string) ($rawResultOrig['raw_text'] ?? '');
            $rawLength = mb_strlen($rawTextOrig);
            $attempted = ($statusOrig !== 'NOT_AVAILABLE');

            if (! empty($rawResultOrig['error'])) {
                $debugErrors[] = "{$engineName}: " . $rawResultOrig['error'];
            }

            $parsedOrig = $this->normalizer->fromOcr(['raw_text' => $rawTextOrig]);

            // If Camera Photo and Enhanced Copy exists, run OCR on Enhanced Image as well
            $parsedEnhanced = null;
            $rawResultEnh = null;
            if ($enhancedPath && file_exists($enhancedPath) && ($preprocData['image_type'] ?? '') === 'CAMERA_PHOTO') {
                try {
                    $rawResultEnh = $adapter->scan($enhancedPath);
                    if (($rawResultEnh['status'] ?? '') === 'SUCCESS') {
                        $parsedEnhanced = $this->normalizer->fromOcr(['raw_text' => $rawResultEnh['raw_text'] ?? '']);
                    }
                } catch (Throwable $e) {
                    // Suppress enhanced variant exception safely
                }
            }

            // Choose best candidate between Original and Enhanced for this engine
            $finalParsed = $parsedOrig;
            $usedVariant = 'Original Image';
            $rawText = $rawTextOrig;
            $durationMs = $rawResultOrig['duration_ms'] ?? 0;

            if ($parsedEnhanced) {
                $origFieldsCount = $this->countDetectedFields($parsedOrig);
                $enhFieldsCount = $this->countDetectedFields($parsedEnhanced);

                if ($enhFieldsCount > $origFieldsCount || (empty($parsedOrig['reference_number']) && ! empty($parsedEnhanced['reference_number']))) {
                    $finalParsed = $parsedEnhanced;
                    $usedVariant = 'Enhanced Image';
                    $rawText = $rawResultEnh['raw_text'] ?? $rawTextOrig;
                    $durationMs += ($rawResultEnh['duration_ms'] ?? 0);
                }
            }

            $groundTruth = $this->evaluateGroundTruth($finalParsed, $expectedValues);

            $results[$key] = [
                'key' => $key,
                'engine' => $engineName,
                'status' => $statusOrig,
                'attempted' => $attempted,
                'variant_used' => $usedVariant,
                'raw_text' => $rawText,
                'raw_text_length' => mb_strlen($rawText),
                'regions' => $rawResultOrig['regions'] ?? 0,
                'confidence' => $rawResultOrig['confidence'] ?? null,
                'duration_ms' => $durationMs,
                'error' => $rawResultOrig['error'] ?? null,
                'paperless_document_id' => $rawResultOrig['paperless_document_id'] ?? null,
                'cleanup_status' => $rawResultOrig['cleanup_status'] ?? null,
                'parsed' => $finalParsed,
                'ground_truth' => $groundTruth,
            ];
        }

        // 2. Determine Overall Comparison Status (Partial Success Support)
        $successfulEngines = 0;
        foreach ($results as $res) {
            if (($res['status'] ?? '') === 'SUCCESS') {
                $successfulEngines++;
            }
        }

        if ($successfulEngines === count($results)) {
            $comparisonStatus = 'SUCCESS';
        } elseif ($successfulEngines > 0) {
            $comparisonStatus = 'PARTIAL_SUCCESS';
        } else {
            $comparisonStatus = 'FAILED';
        }

        $debugMessage = ! empty($debugErrors) ? implode(' | ', $debugErrors) : null;

        // 3. Build Field-Level Multi-Engine Consensus
        $consensus = $this->buildFieldConsensus($results);

        // 4. Clean up temporary enhanced file safely
        if ($enhancedPath) {
            try {
                $this->preprocessor->cleanupTempFile($enhancedPath);
            } catch (Throwable) {
            }
        }

        return [
            'comparison_status' => $comparisonStatus,
            'debug_message' => $debugMessage,
            'environment' => $this->checkEnvironmentDiagnostics(),
            'preprocessing' => $preprocData,
            'expected_values' => $expectedValues,
            'engines' => $results,
            'consensus' => $consensus,
        ];
    }

    private function countDetectedFields(array $parsed): int
    {
        $count = 0;
        if (! empty($parsed['provider']) && $parsed['provider'] !== 'Other / Unknown') {
            $count++;
        }
        if (! empty($parsed['reference_number'])) {
            $count++;
        }
        if (! empty($parsed['transaction_date'])) {
            $count++;
        }
        if (isset($parsed['amount']) && $parsed['amount'] !== null) {
            $count++;
        }

        return $count;
    }

    /**
     * Build Field-Level Multi-Engine Consensus Across docTR, Tesseract, and Paperless-ngx
     */
    public function buildFieldConsensus(array $engineResults): array
    {
        $fields = ['provider', 'reference_number', 'amount', 'currency', 'transaction_date', 'transaction_time'];
        $consensusFields = [];

        foreach ($fields as $field) {
            $candidates = [];
            foreach ($engineResults as $engineKey => $res) {
                $parsed = $res['parsed'] ?? [];
                $val = $parsed[$field] ?? null;
                if ($val !== null && $val !== '' && $val !== 'Other / Unknown') {
                    $candidates[] = [
                        'engine' => $res['engine'] ?? $engineKey,
                        'engine_key' => $engineKey,
                        'value' => $val,
                        'variant_used' => $res['variant_used'] ?? 'Original Image',
                        'normalized' => ($field === 'reference_number') ? $this->normalizer->normalizeReference($val) : (is_string($val) ? mb_strtolower((string) $val) : $val),
                        'field_meta' => $parsed['fields'][$field] ?? [],
                    ];
                }
            }

            if (empty($candidates)) {
                $consensusFields[$field] = [
                    'value' => null,
                    'source_engine' => 'None',
                    'confidence' => 'none',
                    'agreement_count' => 0,
                    'matched_label' => null,
                ];
                continue;
            }

            // Find Majority Agreement
            $grouped = [];
            foreach ($candidates as $cand) {
                $normKey = (string) $cand['normalized'];
                if (! isset($grouped[$normKey])) {
                    $grouped[$normKey] = [
                        'count' => 0,
                        'candidate' => $cand,
                    ];
                }
                $grouped[$normKey]['count']++;
            }

            usort($grouped, fn ($a, $b) => $b['count'] <=> $a['count']);
            $topGroup = $grouped[0];
            $bestCandidate = $topGroup['candidate'];
            $agreementCount = $topGroup['count'];

            $consensusFields[$field] = [
                'value' => $bestCandidate['value'],
                'source_engine' => $bestCandidate['engine'] . ' (' . $bestCandidate['variant_used'] . ')',
                'confidence' => $agreementCount >= 2 ? 'high' : 'medium',
                'agreement_count' => $agreementCount,
                'matched_label' => $bestCandidate['field_meta']['matched_label'] ?? null,
                'raw_candidate' => $bestCandidate['field_meta']['raw_candidate'] ?? (string) $bestCandidate['value'],
            ];
        }

        return [
            'provider' => $consensusFields['provider']['value'] ?? 'Other / Unknown',
            'reference_number' => $consensusFields['reference_number']['value'] ?? null,
            'normalized_reference' => $this->normalizer->normalizeReference($consensusFields['reference_number']['value'] ?? null),
            'amount' => $consensusFields['amount']['value'] ?? null,
            'currency' => $consensusFields['currency']['value'] ?? 'PHP',
            'transaction_date' => $consensusFields['transaction_date']['value'] ?? null,
            'transaction_time' => $consensusFields['transaction_time']['value'] ?? null,
            'field_consensus_metadata' => $consensusFields,
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
