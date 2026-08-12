<?php

namespace App\Services\Receipts;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ReceiptFieldNormalizer
{
    public const CURRENCIES = ['SAR', 'QAR', 'AED', 'KWD', 'BHD', 'OMR', 'PHP', 'USD'];

    public function fromOcr(array $ocr): array
    {
        $rawText = (string) ($ocr['raw_text'] ?? '');
        $reference = $ocr['detected_ref'] ?? $this->extractTransactionReference($rawText);
        $amount = $ocr['detected_amount'] ?? $this->extractAmount($rawText);
        $currency = $ocr['currency'] ?? $this->extractCurrency($rawText);
        [$date, $time, $dateWarning] = $this->normalizeDateTime($ocr['detected_datetime'] ?? $rawText);

        return [
            'provider' => $ocr['provider'] ?? $this->extractProvider($rawText),
            'reference_number' => $reference ? trim((string) $reference) : null,
            'normalized_reference' => $this->normalizeReference($reference),
            'amount' => is_numeric($amount) ? round((float) $amount, 2) : null,
            'currency' => $currency,
            'transaction_date' => $date,
            'transaction_time' => $time,
            'sender_name' => $ocr['detected_sender'] ?? $this->extractName($rawText, 'sender|sent\s+by|from|remitter'),
            'receiver_name' => $ocr['detected_receiver'] ?? $this->extractName($rawText, 'receiver|recipient|beneficiary|paid\s+to|to'),
            'transaction_status' => $this->extractStatus($rawText),
            'original_values' => [
                'reference_number' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_datetime' => $ocr['detected_datetime'] ?? null,
            ],
            'normalization_warnings' => array_values(array_filter([$dateWarning])),
        ];
    }

    public function normalizeReference(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $normalized = Str::upper((string) $value);
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    public function extractTransactionReference(string $text): ?string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
        $reference = $this->extractLabeledIdentifier($lines, 'reference|ref(?:erence)?');
        if ($reference) {
            return $reference;
        }
        $transaction = $this->extractLabeledIdentifier($lines, 'transaction|txn|trace|confirmation|receipt|mtcn|tracking|control');
        if ($transaction) {
            return $transaction;
        }

        $compact = preg_replace('/(?<=\d)[\s-](?=\d)/', '', $text);
        preg_match_all('/\b\d{12,24}\b/', (string) $compact, $matches);
        $fallbacks = array_values(array_filter(
            $matches[0] ?? [],
            fn (string $candidate) => ! preg_match('/^09\d{9}$/', $candidate)
        ));
        usort($fallbacks, fn (string $left, string $right) => strlen($right) <=> strlen($left));

        return $fallbacks[0] ?? null;
    }

    private function extractLabeledIdentifier(array $lines, string $labels): ?string
    {
        $pattern = '/(?:'.$labels.')\s*(?:no\.?|number|id|code|#)?\s*[:#-]?\s*(.*)$/i';
        foreach ($lines as $index => $line) {
            if (! preg_match($pattern, $line, $match)) {
                continue;
            }
            $sameLine = $this->identifierFromValue($match[1] ?? '');
            if ($sameLine) {
                return $sameLine;
            }
            $nextLine = $this->identifierFromValue($lines[$index + 1] ?? '');
            if ($nextLine) {
                return $nextLine;
            }
        }

        return null;
    }

    private function identifierFromValue(string $value): ?string
    {
        $source = Str::upper(trim($value));
        preg_match_all('/\b[A-Z0-9][A-Z0-9-]{5,39}\b/', $source, $matches);
        foreach ($matches[0] ?? [] as $token) {
            if (preg_match('/[A-Z]/', $token) && preg_match('/\d/', $token)) {
                return $token;
            }
        }
        if (preg_match('/\b(?:\d[\s-]?){8,24}\b/', $source, $match)) {
            return preg_replace('/[\s-]+/', '', $match[0]);
        }
        foreach ($matches[0] ?? [] as $token) {
            if (preg_match('/\d/', $token)) {
                return $token;
            }
        }

        return null;
    }

    private function extractAmount(string $text): ?float
    {
        $labels = 'amount\s*(?:sent|paid|transferred)?|send\s*amount|transfer\s*amount|principal\s*amount|total\s*amount';
        if (preg_match('/(?:'.$labels.')\s*[:#-]?\s*(?:SAR|QAR|AED|KWD|BHD|OMR|PHP|USD|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/iu', $text, $match)) {
            return (float) str_replace(',', '', $match[1]);
        }
        if (preg_match('/(?:SAR|QAR|AED|KWD|BHD|OMR|PHP|USD|Php|₱|\$)\s*([\d,]+(?:\.\d{1,2})?)/iu', $text, $match)) {
            return (float) str_replace(',', '', $match[1]);
        }

        return null;
    }

    private function extractCurrency(string $text): ?string
    {
        if (preg_match('/\b(SAR|QAR|AED|KWD|BHD|OMR|PHP|USD)\b/i', $text, $match)) {
            return strtoupper($match[1]);
        }
        if (str_contains($text, '₱')) {
            return 'PHP';
        }

        return null;
    }

    private function extractProvider(string $text): ?string
    {
        foreach ([
            'Western Union' => '/\bwestern\s+union\b/i', 'MoneyGram' => '/\bmoneygram\b/i',
            'GCash' => '/\bgcash\b/i', 'Maya' => '/\b(?:maya|paymaya)\b/i',
            'BDO' => '/\bbdo\b/i', 'BPI' => '/\bbpi\b/i', 'Cebuana Lhuillier' => '/\bcebuana\b/i',
            'PalawanPay' => '/\bpalawan(?:pay|\s+pawnshop)?\b/i',
        ] as $provider => $pattern) {
            if (preg_match($pattern, $text)) {
                return $provider;
            }
        }

        return null;
    }

    private function extractName(string $text, string $labels): ?string
    {
        if (preg_match('/(?:'.$labels.')\s*[:#-]?\s*([\pL][\pL .,\'-]{2,80})/iu', $text, $match)) {
            return trim(preg_split('/\s{2,}|\R|\b(?:amount|reference|date|status)\b/i', $match[1])[0]);
        }

        return null;
    }

    private function extractStatus(string $text): ?string
    {
        if (preg_match('/\b(failed|cancelled|canceled|reversed|declined|unsuccessful)\b/i', $text, $match)) {
            return ucfirst(strtolower($match[1]));
        }
        if (preg_match('/\b(successful|successfully|completed|paid|approved)\b/i', $text, $match)) {
            return ucfirst(strtolower($match[1]));
        }

        return null;
    }

    private function normalizeDateTime(string $value): array
    {
        $dateText = null;
        $timeText = null;
        $warning = null;

        if (preg_match('/\b(20\d{2})[\/-](\d{1,2})[\/-](\d{1,2})\b/', $value, $m)) {
            $dateText = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } elseif (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})\b/', $value, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            if ($first <= 12 && $second <= 12) {
                $warning = 'AMBIGUOUS_DATE_ORDER';
            }
            $day = $first > 12 ? $first : $second;
            $month = $first > 12 ? $second : $first;
            $dateText = sprintf('%04d-%02d-%02d', $m[3], $month, $day);
        } elseif (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+20\d{2}\b/i', $value, $m)) {
            try {
                $dateText = Carbon::parse($m[0])->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        if ($dateText) {
            try {
                Carbon::createFromFormat('Y-m-d', $dateText);
            } catch (\Throwable) {
                $dateText = null;
                $warning = 'INVALID_DATE';
            }
        }
        if (preg_match('/\b(\d{1,2}):(\d{2})(?:\s*([AP]M))?\b/i', $value, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $period = strtoupper($m[3] ?? '');
            if ($period === 'AM' && $hour === 12) {
                $hour = 0;
            }
            if ($period === 'PM' && $hour < 12) {
                $hour += 12;
            }
            if ($hour <= 23 && $minute <= 59) {
                $timeText = sprintf('%02d:%02d:00', $hour, $minute);
            }
        }

        return [$dateText, $timeText, $warning];
    }
}
