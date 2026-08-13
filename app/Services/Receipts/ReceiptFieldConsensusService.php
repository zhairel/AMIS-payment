<?php

namespace App\Services\Receipts;

use Carbon\Carbon;

class ReceiptFieldConsensusService
{
    private const FIELDS = [
        'provider', 'reference_number', 'amount', 'currency', 'transaction_date',
        'transaction_time', 'sender_name', 'receiver_name', 'transaction_status',
        'receiving_bank', 'mode',
    ];

    private const AMBIGUOUS_CRITICAL_FIELDS = [
        'provider', 'reference_number', 'amount', 'currency', 'transaction_date',
        'transaction_status',
    ];

    public function __construct(private readonly ReceiptFieldNormalizer $normalizer) {}

    /**
     * Select every field independently from successful OCR engine/variant results.
     *
     * @param  array<int|string, array<string, mixed>>  $engineResults
     * @return array{fields: array<string, mixed>, uncertain_fields: array<int, string>, field_consensus_metadata: array<string, mixed>}
     */
    public function merge(array $engineResults): array
    {
        $fields = [];
        $uncertain = [];
        $metadata = [];

        foreach (self::FIELDS as $field) {
            $candidates = $this->candidatesFor($field, $engineResults);
            if ($candidates === []) {
                $fields[$field] = null;
                $metadata[$field] = [
                    'value' => null,
                    'source_engine' => 'None',
                    'source_variant' => null,
                    'agreement_count' => 0,
                    'selection_score' => 0,
                    'matched_label' => null,
                ];

                continue;
            }

            $agreementCounts = array_count_values(array_column($candidates, 'normalized'));
            foreach ($candidates as &$candidate) {
                $candidate['agreement_count'] = $agreementCounts[$candidate['normalized']] ?? 1;
                $candidate['score'] += max(0, $candidate['agreement_count'] - 1) * 30;
            }
            unset($candidate);

            usort($candidates, static fn (array $left, array $right): int => [$right['agreement_count'], $right['score']] <=> [$left['agreement_count'], $left['score']]
            );

            $winner = $candidates[0];
            $runnerUp = $candidates[1] ?? null;
            $differentValues = count($agreementCounts) > 1;
            $ambiguous = $differentValues
                && $winner['agreement_count'] === 1
                && $runnerUp !== null
                && ($winner['score'] - $runnerUp['score']) < 8
                && in_array($field, self::AMBIGUOUS_CRITICAL_FIELDS, true);

            if ($ambiguous) {
                $fields[$field] = null;
                $uncertain[] = $field;
            } else {
                $fields[$field] = $winner['value'];
            }

            $metadata[$field] = [
                'value' => $ambiguous ? null : $winner['value'],
                'source_engine' => $winner['engine'],
                'source_variant' => $winner['variant'],
                'agreement_count' => $winner['agreement_count'],
                'selection_score' => round($winner['score'], 2),
                'matched_label' => $winner['matched_label'],
                'raw_candidate' => $winner['raw_candidate'],
                'ambiguous' => $ambiguous,
            ];
        }

        $fields['provider'] ??= 'Other / Unknown';
        $fields['currency'] ??= 'PHP';
        $fields['normalized_reference'] = $this->normalizer->normalizeReference($fields['reference_number'] ?? null);
        $fields['field_consensus_metadata'] = $metadata;
        $fields['fields'] = $metadata;
        $fields['ocr_sources'] = collect($engineResults)->mapWithKeys(function (array $result, string|int $key): array {
            $source = (string) ($result['engine_key'] ?? $key);

            return [$source => [
                'engine' => $result['engine'] ?? $source,
                'variant' => $result['variant'] ?? 'original',
                'confidence' => $result['confidence'] ?? null,
                'fields' => collect($result['parsed'] ?? [])->only(self::FIELDS)->all(),
            ]];
        })->all();

        return [
            'fields' => $fields,
            'uncertain_fields' => array_values(array_unique($uncertain)),
            'field_consensus_metadata' => $metadata,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function candidatesFor(string $field, array $engineResults): array
    {
        $candidates = [];
        foreach ($engineResults as $key => $result) {
            if (! $this->successful($result)) {
                continue;
            }

            $parsed = $result['parsed'] ?? [];
            $value = $parsed[$field] ?? null;
            if ($value === null || $value === '' || $value === 'Other / Unknown') {
                continue;
            }

            $fieldMeta = $parsed['fields'][$field] ?? [];
            $normalized = $this->normalize($field, $value);
            if ($normalized === '') {
                continue;
            }

            $candidates[] = [
                'engine' => (string) ($result['engine'] ?? $key),
                'engine_key' => (string) ($result['engine_key'] ?? $key),
                'variant' => (string) ($result['variant'] ?? 'original'),
                'value' => $value,
                'normalized' => $normalized,
                'matched_label' => $fieldMeta['matched_label'] ?? null,
                'raw_candidate' => $fieldMeta['raw_candidate'] ?? (string) $value,
                'score' => $this->candidateScore($field, $value, $result, $fieldMeta),
            ];
        }

        return $candidates;
    }

    private function successful(array $result): bool
    {
        $status = strtoupper((string) ($result['status'] ?? 'SUCCESS'));

        return ! in_array($status, ['FAILED', 'NOT_AVAILABLE', 'UNAVAILABLE'], true)
            && ! (($result['success'] ?? true) === false);
    }

    private function normalize(string $field, mixed $value): string
    {
        return match ($field) {
            'reference_number' => (string) ($this->normalizer->normalizeReference($value) ?? ''),
            'amount' => number_format((float) $value, 2, '.', ''),
            'provider', 'currency', 'transaction_status' => mb_strtoupper(preg_replace('/[^\pL\pN]+/u', '', (string) $value)),
            default => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $value))),
        };
    }

    private function candidateScore(string $field, mixed $value, array $result, array $fieldMeta): float
    {
        $confidence = is_numeric($result['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $result['confidence']))
            : 0.0;
        $score = $confidence * 40;
        $score += match ($fieldMeta['confidence'] ?? null) {
            'high' => 22,
            'medium' => 12,
            default => 0,
        };

        $label = (string) ($fieldMeta['matched_label'] ?? '');
        if ($label !== '' && ! preg_match('/pattern search|pre-extracted|provider detection|time label/i', $label)) {
            $score += 14;
        } elseif ($label !== '') {
            $score += 4;
        }

        return $score + match ($field) {
            'provider' => $value !== 'Other / Unknown' ? 18 : -30,
            'reference_number' => $this->referenceScore((string) $value, (string) ($result['raw_text'] ?? '')),
            'amount' => $this->amountScore((float) $value, $label),
            'currency' => in_array(strtoupper((string) $value), ReceiptFieldNormalizer::CURRENCIES, true) ? 12 : -15,
            'transaction_date' => $this->dateScore((string) $value),
            'transaction_time' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', (string) $value) ? 10 : -10,
            'transaction_status' => in_array(strtoupper((string) $value), ['SUCCESS', 'FAILED'], true) ? 15 : 0,
            default => 5,
        };
    }

    private function referenceScore(string $value, string $context): int
    {
        if (! $this->normalizer->isValidReferenceCandidate($value, $context)) {
            return -50;
        }

        $score = 18;
        if (preg_match('/[A-Z]/i', $value) && preg_match('/\d/', $value)) {
            $score += 7;
        }
        if (strlen($this->normalizer->normalizeReference($value) ?? '') >= 8) {
            $score += 5;
        }

        return $score;
    }

    private function amountScore(float $value, string $label): int
    {
        if ($value <= 0) {
            return -40;
        }

        $priority = [
            'transfer amount' => 18,
            'payment amount' => 17,
            'amount sent' => 16,
            'amount paid' => 15,
            'paid amount' => 14,
            'amount in destination currency' => 13,
            'transaction amount' => 12,
            'total amount sent' => 10,
            'amount' => 8,
            'total amount' => 6,
            'total' => 4,
        ];
        $normalizedLabel = mb_strtolower($label);
        foreach ($priority as $candidate => $score) {
            if (str_contains($normalizedLabel, $candidate)) {
                return 12 + $score;
            }
        }

        return 12;
    }

    private function dateScore(string $value): int
    {
        try {
            return Carbon::parse($value)->isFuture() ? -25 : 15;
        } catch (\Throwable) {
            return -25;
        }
    }
}
