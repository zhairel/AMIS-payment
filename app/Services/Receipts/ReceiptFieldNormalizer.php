<?php

namespace App\Services\Receipts;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReceiptFieldNormalizer
{
    public const CURRENCIES = ['SAR', 'PHP', 'USD', 'QAR', 'AED', 'KWD', 'BHD', 'OMR', 'EUR', 'GBP'];

    public function fromOcr(array $ocr): array
    {
        $rawText = trim((string) ($ocr['raw_text'] ?? ''));
        $extractionMethod = 'Alias Parser / Provider Parser';
        $warnings = [];

        // 1. Provider & Mode Detection
        $provider = $ocr['provider'] ?? $this->extractProvider($rawText);
        $mode = $this->extractMode($rawText, $provider);

        // 2. Reference Number Extraction & Negative Filtering
        $reference = $this->extractTransactionReference($rawText, $ocr['detected_ref'] ?? null);

        // 3. Amount & Currency Extraction
        [$amount, $currency] = $this->extractAmountAndCurrency($rawText, $ocr['detected_amount'] ?? null, $ocr['currency'] ?? null, $provider);

        // 4. Date & Time Normalization
        [$date, $time, $dateWarning] = $this->normalizeDateTime($rawText, $ocr['detected_datetime'] ?? null);
        if ($dateWarning) {
            $warnings[] = $dateWarning;
        }

        // 5. People & Status
        $sender = $ocr['detected_sender'] ?? $this->extractName($rawText, 'sender|sent\s+by|from|remitter');
        $receiver = $ocr['detected_receiver'] ?? $this->extractName($rawText, 'receiver|recipient|beneficiary|paid\s+to|to');
        $status = $this->extractStatus($rawText);

        // 6. AI Extraction Fallback if required fields are missing
        if (!empty($rawText) && (empty($reference) || $amount === null || empty($provider) || $provider === 'Other / Unknown')) {
            $aiResult = $this->attemptAiFallback($rawText);
            if ($aiResult['success'] ?? false) {
                $extractionMethod = 'AI Fallback';
                if (empty($provider) || $provider === 'Other / Unknown') {
                    $provider = $aiResult['provider'] ?? $provider;
                    $mode = $this->extractMode($rawText, $provider);
                }
                if (empty($reference)) {
                    $reference = $aiResult['reference_number'] ?? $reference;
                }
                if ($amount === null && isset($aiResult['amount']) && is_numeric($aiResult['amount'])) {
                    $amount = round((float) $aiResult['amount'], 2);
                    $currency = !empty($aiResult['currency']) ? strtoupper($aiResult['currency']) : $currency;
                }
                if (empty($date) && !empty($aiResult['transaction_date'])) {
                    $date = $aiResult['transaction_date'];
                }
                if (empty($time) && !empty($aiResult['transaction_time'])) {
                    $time = $aiResult['transaction_time'];
                }
            }
        }

        return [
            'provider' => $provider,
            'mode' => $mode,
            'reference_number' => $reference ? trim((string) $reference) : null,
            'normalized_reference' => $this->normalizeReference($reference),
            'amount' => is_numeric($amount) ? round((float) $amount, 2) : null,
            'currency' => $currency ?: 'PHP',
            'transaction_date' => $date,
            'transaction_time' => $time,
            'sender_name' => $sender,
            'receiver_name' => $receiver,
            'transaction_status' => $status,
            'extraction_method' => $extractionMethod,
            'parser_result' => [
                'provider' => $provider ?: 'Other / Unknown',
                'mode' => $mode,
                'reference' => $reference ? trim((string) $reference) : null,
                'date' => $date,
                'time' => $time,
                'amount' => is_numeric($amount) ? round((float) $amount, 2) : null,
                'currency' => $currency ?: 'PHP',
            ],
            'original_values' => [
                'reference_number' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_datetime' => $ocr['detected_datetime'] ?? null,
            ],
            'normalization_warnings' => array_values(array_unique($warnings)),
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

    /**
     * Provider & Mode Detection
     */
    public function extractProvider(string $text): string
    {
        $textLower = mb_strtolower($text);

        if (str_contains($textLower, 'anb') || str_contains($textLower, 'anb.com.sa') || str_contains($textLower, 'telemoney')) {
            return 'ANB / TeleMoney Transfer';
        }
        if (str_contains($textLower, 'd360')) {
            return 'D360';
        }
        if (preg_match('/\bgcash\b/i', $text)) {
            return 'GCash';
        }
        if (preg_match('/\b(?:maya|paymaya)\b/i', $text)) {
            return 'Maya';
        }
        if (preg_match('/\b(?:bdo|banco\s+de\s+oro)\b/i', $text)) {
            return 'BDO';
        }
        if (preg_match('/\b(?:bpi|bank\s+of\s+the\s+philippine\s+islands)\b/i', $text)) {
            return 'BPI';
        }
        if (preg_match('/\b(?:metrobank|metropolitan\s+bank)\b/i', $text)) {
            return 'Metrobank';
        }
        if (preg_match('/\blandbank\b/i', $text)) {
            return 'LandBank';
        }
        if (preg_match('/\bunionbank\b/i', $text)) {
            return 'UnionBank';
        }
        if (preg_match('/\brcbc\b/i', $text)) {
            return 'RCBC';
        }
        if (preg_match('/\bpnb\b/i', $text)) {
            return 'PNB';
        }
        if (preg_match('/\bsecurity\s+bank\b/i', $text)) {
            return 'Security Bank';
        }
        if (preg_match('/\bwestern\s+union\b/i', $text)) {
            return 'Western Union';
        }
        if (preg_match('/\bmoneygram\b/i', $text)) {
            return 'MoneyGram';
        }
        if (preg_match('/\bcebuana\b/i', $text)) {
            return 'Cebuana Lhuillier';
        }
        if (preg_match('/\bpalawan(?:pay|\s+express|\s+pawnshop)?\b/i', $text)) {
            return 'PalawanPay';
        }
        if (preg_match('/\b(?:wise|transferwise)\b/i', $text)) {
            return 'Wise';
        }
        if (preg_match('/\binstapay\b/i', $text)) {
            return 'InstaPay';
        }
        if (preg_match('/\bpesonet\b/i', $text)) {
            return 'PESONet';
        }
        if (preg_match('/\b(?:bank\s+transfer|transfer\s+type\s+bank)\b/i', $text)) {
            return 'Bank Transfer';
        }
        if (preg_match('/\bremittance\b/i', $text)) {
            return 'Remittance';
        }

        return 'Other / Unknown';
    }

    private function extractMode(string $text, string $provider): ?string
    {
        if (str_contains($provider, 'TeleMoney')) {
            return 'TeleMoney Transfer';
        }
        if (preg_match('/(?:transfer\s+type|payment\s+mode|mode\s+of\s+payment|method)\s*[:\-]?\s*([A-Za-z0-9\s]{3,30})/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Transaction / Reference No. Extraction & Negative Filtering
     */
    public function extractTransactionReference(string $text, ?string $preExtracted = null): ?string
    {
        // 1. Validate pre-extracted candidate if clean
        if ($preExtracted && $this->isValidReference($preExtracted, $text)) {
            return trim($preExtracted);
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));

        // 2. Primary Reference Labels search
        $refLabels = 'reference\s*number|reference\s*no\.?|reference\s*id|ref\s*no\.?|ref\s*#|ref\s*number';
        $refMatch = $this->searchLinesForLabel($lines, $refLabels, $text);
        if ($refMatch) {
            return $refMatch;
        }

        // 3. Secondary Identifier Labels search
        $secLabels = 'transaction\s*id|transaction\s*no\.?|transaction\s*number|txn\s*id|txn\s*no\.?|transfer\s*id|transfer\s*no\.?|mtcn|control\s*number|control\s*no\.?|tracking\s*number|tracking\s*no\.?|confirmation\s*number|confirmation\s*no\.?|receipt\s*number|receipt\s*no\.?|remittance\s*number|remittance\s*no\.?';
        $secMatch = $this->searchLinesForLabel($lines, $secLabels, $text);
        if ($secMatch) {
            return $secMatch;
        }

        // 4. Compact line fallback (search standalone 8-24 digit / alphanumeric codes)
        $compact = preg_replace('/(?<=\d)[\s-](?=\d)/', '', $text);
        preg_match_all('/\b[A-Z0-9]{8,24}\b/', (string) $compact, $matches);
        foreach ($matches[0] ?? [] as $candidate) {
            if ($this->isValidReference($candidate, $text)) {
                return $candidate;
            }
        }

        return null;
    }

    private function searchLinesForLabel(array $lines, string $labels, string $fullContext): ?string
    {
        $pattern = '/(?:'.$labels.')\s*[:#-]?\s*(.*)$/i';
        foreach ($lines as $index => $line) {
            if (! preg_match($pattern, $line, $match)) {
                continue;
            }
            $sameLine = $this->identifierFromValue($match[1] ?? '', $fullContext);
            if ($sameLine && $this->isValidReference($sameLine, $fullContext)) {
                return $sameLine;
            }
            $nextLine = $this->identifierFromValue($lines[$index + 1] ?? '', $fullContext);
            if ($nextLine && $this->isValidReference($nextLine, $fullContext)) {
                return $nextLine;
            }
        }

        return null;
    }

    private function identifierFromValue(string $value, string $fullContext): ?string
    {
        $source = Str::upper(trim($value));
        preg_match_all('/\b[A-Z0-9][A-Z0-9-]{5,39}\b/', $source, $matches);

        foreach ($matches[0] ?? [] as $token) {
            if ($this->isValidReference($token, $fullContext)) {
                return $token;
            }
        }

        return null;
    }

    private function isValidReference(string $candidate, string $fullContext): bool
    {
        $candidate = trim($candidate);

        // Reject if candidate is empty or too short / long
        if (strlen($candidate) < 6 || strlen($candidate) > 36) {
            return false;
        }

        // Reject if it's a mobile number (09xxxxxxxx or +639xxxxxxxxx or 639xxxxxxxxx)
        if (preg_match('/^(?:09|\+?639)\d{9}$/', $candidate)) {
            return false;
        }

        // Reject if it's a pure float/currency amount (e.g. 260.32, 4000.11)
        if (preg_match('/^\d+\.\d{1,2}$/', $candidate)) {
            return false;
        }

        // Reject if it is explicitly labeled as an Account Number in context
        if (preg_match('/(?:account\s*(?:number|no\.?|#)|bank\s*account|account\s*num)\s*[:#-]?\s*'.preg_quote($candidate, '/').'/i', $fullContext)) {
            return false;
        }

        // Reject if candidate matches exact exchange rate values
        if (preg_match('/exchange\s*rate\s*[:#-]?\s*.*'.preg_quote($candidate, '/').'/i', $fullContext)) {
            return false;
        }

        return true;
    }

    /**
     * Amount & Currency Extraction with Label Prioritization
     */
    public function extractAmountAndCurrency(string $text, ?float $preAmount = null, ?string $preCurrency = null, string $provider = ''): array
    {
        $amount = null;
        $currency = $preCurrency;

        // Clean single line version
        $cleanText = preg_replace('/\s+/', ' ', $text);

        // Priority Amount Patterns
        $amountPatterns = [
            // Priority 1: Amount in Destination Currency / Destination Amount / Receive Amount
            '/(?:amount\s+in\s+destination\s+currency|destination\s+amount|receive\s+amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // Priority 2: Total Amount
            '/(?:total\s+amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // Priority 3: Amount Sent / You Sent / Sent Amount
            '/(?:amount\s+sent|you\s+sent|sent\s+amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // Priority 4: Transfer Amount
            '/(?:transfer\s+amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // Priority 5: Amount Paid / Paid Amount / Principal Amount / Remittance Amount
            '/(?:amount\s+paid|paid\s+amount|principal\s+amount|remittance\s+amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // Priority 6: Generic Amount
            '/(?:amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i',
        ];

        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $match)) {
                $candidate = (float) str_replace(',', '', $match[1]);
                if ($candidate > 0 && ! $this->isFeeOrExchangeRate($cleanText, $match[1])) {
                    $amount = $candidate;
                    break;
                }
            }
        }

        // Fallback to pre-extracted or regex standalone currency match
        if ($amount === null && $preAmount && $preAmount > 0) {
            $amount = $preAmount;
        }

        if ($amount === null) {
            if (preg_match('/(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)\s*([\d,]+(?:\.\d{1,2})?)/i', $cleanText, $m)) {
                $candidate = (float) str_replace(',', '', $m[1]);
                if ($candidate > 0 && ! $this->isFeeOrExchangeRate($cleanText, $m[1])) {
                    $amount = $candidate;
                }
            }
        }

        // Extract Currency
        if (empty($currency)) {
            if (preg_match('/\b(SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|EUR|GBP)\b/i', $text, $m)) {
                $currency = strtoupper($m[1]);
            } elseif (str_contains($text, '₱') || preg_match('/\bPhp\b/i', $text)) {
                $currency = 'PHP';
            } elseif (str_contains($text, '$')) {
                $currency = 'USD';
            } elseif (str_contains($text, 'SR') || str_contains($text, 'Riyal') || str_contains($text, 'anb.com.sa') || str_contains($provider, 'ANB') || str_contains($provider, 'D360')) {
                $currency = 'SAR';
            } else {
                $currency = 'PHP';
            }
        }

        return [$amount, $currency];
    }

    private function isFeeOrExchangeRate(string $fullText, string $numericStr): bool
    {
        // Avoid matching numbers explicitly labeled as Fee, VAT, Tax, or Exchange Rate
        if (preg_match('/(?:fee\s*amount|service\s*fee|vat|tax|exchange\s*rate)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*'.preg_quote($numericStr, '/').'/i', $fullText)) {
            return true;
        }
        return false;
    }

    /**
     * Date & Time Normalization (No Fake Times)
     */
    public function normalizeDateTime(string $text, ?string $preDatetime = null): array
    {
        $dateText = null;
        $timeText = null;
        $warning = null;

        $source = $preDatetime ?: $text;

        // Date extraction
        if (preg_match('/\b(20\d{2})[\/-](\d{1,2})[\/-](\d{1,2})\b/', $source, $m)) {
            $dateText = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } elseif (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})\b/', $source, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            if ($first <= 12 && $second <= 12) {
                $warning = 'AMBIGUOUS_DATE_ORDER';
            }
            $day = $first > 12 ? $first : $second;
            $month = $first > 12 ? $second : $first;
            $dateText = sprintf('%04d-%02d-%02d', $m[3], $month, $day);
        } elseif (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+20\d{2}\b/i', $source, $m)) {
            try {
                $dateText = Carbon::parse($m[0])->format('Y-m-d');
            } catch (\Throwable) {
            }
        } elseif (preg_match('/\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+20\d{2}\b/i', $source, $m)) {
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

        // Time extraction - ONLY if explicitly present in text!
        if (preg_match('/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AP]M)?\b/i', $text, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $period = strtoupper($m[4] ?? '');
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
        if (preg_match('/\b(successful|successfully|completed|paid|approved|success)\b/i', $text, $match)) {
            return ucfirst(strtolower($match[1]));
        }

        return null;
    }

    /**
     * AI Extraction Fallback Layer via Gemini API or Google Vision Annotations
     */
    private function attemptAiFallback(string $rawText): array
    {
        $apiKey = null;
        if (function_exists('config')) {
            try {
                $apiKey = config('services.google_vision.key') ?: config('services.gemini.key');
            } catch (\Throwable) {
            }
        }

        if (empty($apiKey)) {
            return ['success' => false];
        }

        try {
            $prompt = "You are an expert financial receipt parser. Extract payment fields from this receipt OCR text:\n\n"
                . $rawText . "\n\n"
                . "Return ONLY valid JSON matching this exact schema:\n"
                . "{\n"
                . '  "provider": string or null,' . "\n"
                . '  "reference_number": string or null,' . "\n"
                . '  "amount": float or null,' . "\n"
                . '  "currency": string or null,' . "\n"
                . '  "transaction_date": "YYYY-MM-DD" or null,' . "\n"
                . '  "transaction_time": "HH:MM:SS" or null' . "\n"
                . "}\n"
                . "Rules:\n"
                . "1. Do NOT invent missing values.\n"
                . "2. reference_number MUST NOT be an account number, mobile number, or fee.\n"
                . "3. If time is missing from text, set transaction_time to null.\n";

            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $jsonText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $parsed = json_decode($jsonText, true);
                if (is_array($parsed)) {
                    return array_merge(['success' => true], $parsed);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Receipt AI Fallback exception: ' . $e->getMessage());
        }

        return ['success' => false];
    }
}
