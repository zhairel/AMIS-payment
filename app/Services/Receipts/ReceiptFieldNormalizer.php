<?php

namespace App\Services\Receipts;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReceiptFieldNormalizer
{
    public const CURRENCIES = ['SAR', 'PHP', 'USD', 'QAR', 'AED', 'KWD', 'BHD', 'OMR', 'EUR', 'GBP'];

    public const EXCLUDED_WORDS = [
        'NURHASAN', 'OFFICIAL', 'FOLLOWING', 'REQUESTED', 'MUNAWWARA',
        'SUCCESSFUL', 'SUCCESS', 'COMPLETED', 'PAYMENT', 'METHOD',
        'RECEIPT', 'TRANSACTION', 'REFERENCE', 'AMOUNT', 'BALANCE',
        'ACCOUNT', 'DETAILS', 'STATUS', 'REPEAT', 'SHARE', 'FAVORITES',
        'DOWNLOAD', 'MOBILE', 'NUMBER', 'TRANSFER', 'REMITTANCE',
    ];

    public function fromOcr(array $ocr): array
    {
        $rawText = trim((string) ($ocr['raw_text'] ?? ''));
        $extractionMethod = 'Alias Parser / Provider Parser';
        $warnings = [];

        // 1. Provider & Mode Detection (with Bank Disambiguation)
        [$provider, $receivingBank] = $this->extractProviderAndBank($rawText);
        $mode = $this->extractMode($rawText, $provider);

        // 2. Reference Number Extraction (39 Priority Aliases + Strict Validation)
        [$reference, $matchedRefLabel, $rawRefCandidate] = $this->extractTransactionReference($rawText, $ocr['detected_ref'] ?? null);

        // 3. Amount & Currency Extraction (18 Priority Aliases + Fee Exclusion)
        [$amount, $currency, $matchedAmountLabel, $rawAmountCandidate] = $this->extractAmountAndCurrency(
            $rawText,
            $ocr['detected_amount'] ?? null,
            $ocr['currency'] ?? null,
            $provider
        );

        // 4. Date & Time Normalization (Transaction Lines only, NO phone status bar clocks)
        [$date, $time, $matchedDateLabel, $rawDateCandidate, $dateWarning] = $this->normalizeDateTime($rawText, $ocr['detected_datetime'] ?? null);
        if ($dateWarning) {
            $warnings[] = $dateWarning;
        }

        // 5. Sender, Receiver, & Status
        $sender = $ocr['detected_sender'] ?? $this->extractName($rawText, 'sender|sent\s+by|from|remitter|account\s+holder|debit\s+from');
        $receiver = $ocr['detected_receiver'] ?? $this->extractName($rawText, 'receiver|recipient|beneficiary|paid\s+to|to|account\s+name|merchant');
        $status = $this->extractStatus($rawText);

        // 6. AI Extraction Fallback if required fields are missing
        if (! empty($rawText) && (empty($reference) || $amount === null || empty($provider) || $provider === 'Other / Unknown')) {
            $aiResult = $this->attemptAiFallback($rawText);
            if ($aiResult['success'] ?? false) {
                $extractionMethod = 'AI Fallback';
                if (empty($provider) || $provider === 'Other / Unknown') {
                    $provider = $aiResult['provider'] ?? $provider;
                    $mode = $this->extractMode($rawText, $provider);
                }
                if (empty($reference)) {
                    $reference = $aiResult['reference_number'] ?? $reference;
                    $matchedRefLabel = 'AI Extracted Reference';
                    $rawRefCandidate = $reference;
                }
                if ($amount === null && isset($aiResult['amount']) && is_numeric($aiResult['amount'])) {
                    $amount = round((float) $aiResult['amount'], 2);
                    $currency = ! empty($aiResult['currency']) ? strtoupper($aiResult['currency']) : $currency;
                    $matchedAmountLabel = 'AI Extracted Amount';
                    $rawAmountCandidate = (string) $amount;
                }
                if (empty($date) && ! empty($aiResult['transaction_date'])) {
                    $date = $aiResult['transaction_date'];
                    $matchedDateLabel = 'AI Extracted Date';
                    $rawDateCandidate = $date;
                }
                if (empty($time) && ! empty($aiResult['transaction_time'])) {
                    $time = $aiResult['transaction_time'];
                }
            }
        }

        // Structured Per-Field Metadata
        $fieldsMetadata = [
            'provider' => [
                'value' => $provider,
                'receiving_bank' => $receivingBank,
                'matched_label' => 'Provider Detection',
                'confidence' => ($provider && $provider !== 'Other / Unknown') ? 'high' : 'low',
                'raw_candidate' => $provider,
            ],
            'reference_number' => [
                'value' => $reference ? trim((string) $reference) : null,
                'normalized' => $this->normalizeReference($reference),
                'matched_label' => $matchedRefLabel ?? 'Reference Label',
                'confidence' => $reference ? 'high' : 'none',
                'raw_candidate' => $rawRefCandidate,
            ],
            'amount' => [
                'value' => is_numeric($amount) ? round((float) $amount, 2) : null,
                'currency' => $currency ?: 'PHP',
                'matched_label' => $matchedAmountLabel ?? 'Amount Label',
                'confidence' => $amount !== null ? 'high' : 'none',
                'raw_candidate' => $rawAmountCandidate,
            ],
            'transaction_date' => [
                'value' => $date,
                'matched_label' => $matchedDateLabel ?? 'Date Label',
                'confidence' => $date ? 'high' : 'none',
                'raw_candidate' => $rawDateCandidate,
            ],
            'transaction_time' => [
                'value' => $time,
                'matched_label' => $time ? 'Time Label' : null,
                'confidence' => $time ? 'high' : 'none',
                'raw_candidate' => $time,
            ],
        ];

        return [
            'provider' => $provider,
            'receiving_bank' => $receivingBank,
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
            'fields' => $fieldsMetadata,
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
     * Provider & Receiving Bank Disambiguation
     */
    public function extractProviderAndBank(string $text): array
    {
        $textLower = mb_strtolower($text);
        $provider = 'Other / Unknown';
        $receivingBank = null;

        // Disambiguate sending provider vs receiving bank
        if (preg_match('/\b(?:sent\s+via|via|from)\s+gcash\b/i', $text) || preg_match('/\bgcash\b/i', $text)) {
            $provider = 'GCash';
        } elseif (str_contains($textLower, 'anb') || str_contains($textLower, 'anb.com.sa') || str_contains($textLower, 'telemoney')) {
            $provider = 'ANB / TeleMoney Transfer';
        } elseif (str_contains($textLower, 'd360')) {
            $provider = 'D360';
        } elseif (preg_match('/\bgotyme\b/i', $text)) {
            $provider = 'GoTyme Bank';
        } elseif (preg_match('/\b(?:maya|paymaya)\b/i', $text)) {
            $provider = 'Maya';
        } elseif (preg_match('/\b(?:bdo|banco\s+de\s+oro)\b/i', $text)) {
            $provider = 'BDO';
        } elseif (preg_match('/\b(?:bpi|bank\s+of\s+the\s+philippine\s+islands)\b/i', $text)) {
            $provider = 'BPI';
        } elseif (preg_match('/\b(?:metrobank|metropolitan\s+bank)\b/i', $text)) {
            $provider = 'Metrobank';
        } elseif (preg_match('/\blandbank\b/i', $text)) {
            $provider = 'LandBank';
        } elseif (preg_match('/\bunionbank\b/i', $text)) {
            $provider = 'UnionBank';
        } elseif (preg_match('/\brcbc\b/i', $text)) {
            $provider = 'RCBC';
        } elseif (preg_match('/\bpnb\b/i', $text)) {
            $provider = 'PNB';
        } elseif (preg_match('/\bsecurity\s+bank\b/i', $text)) {
            $provider = 'Security Bank';
        } elseif (preg_match('/\bwestern\s+union\b/i', $text)) {
            $provider = 'Western Union';
        } elseif (preg_match('/\bmoneygram\b/i', $text)) {
            $provider = 'MoneyGram';
        } elseif (preg_match('/\bcebuana\b/i', $text)) {
            $provider = 'Cebuana Lhuillier';
        } elseif (preg_match('/\bpalawan(?:pay|\s+express|\s+pawnshop)?\b/i', $text)) {
            $provider = 'PalawanPay';
        } elseif (preg_match('/\b(?:wise|transferwise)\b/i', $text)) {
            $provider = 'Wise';
        } elseif (preg_match('/\binstapay\b/i', $text)) {
            $provider = 'InstaPay';
        } elseif (preg_match('/\bpesonet\b/i', $text)) {
            $provider = 'PESONet';
        }

        // Detect Receiving Bank if different from Provider
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
        foreach ($lines as $line) {
            if (preg_match('/(?:bank|destination\s+bank|transferred\s+to|recipient\s+bank)\s*[:\-]?\s*([A-Za-z0-9\s]{3,30})/i', $line, $m)) {
                $receivingBank = trim($m[1]);
                break;
            }
        }

        return [$provider, $receivingBank];
    }

    public function extractProvider(string $text): string
    {
        [$provider] = $this->extractProviderAndBank($text);
        return $provider;
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
     * 39 Priority Reference Aliases Extraction & Strict Negative Filtering
     */
    public function extractTransactionReference(string $text, ?string $preExtracted = null): array
    {
        if ($preExtracted && $this->isValidReferenceCandidate($preExtracted, $text)) {
            return [trim($preExtracted), 'Pre-extracted Reference', $preExtracted];
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));

        // 39 Priority Reference Aliases in exact user requested priority order
        $priorityReferenceAliases = [
            'transaction reference number',
            'transaction reference',
            'reference number',
            'reference no',
            'ref no',
            'ref #',
            'reference id',
            'reference',
            'transaction id',
            'transaction no',
            'transaction number',
            'transaction #',
            'transfer reference number',
            'transfer reference',
            'transfer id',
            'transfer no',
            'payment reference no',
            'payment reference',
            'payment id',
            'receipt reference',
            'receipt number',
            'receipt no',
            'confirmation number',
            'confirmation no',
            'confirmation id',
            'trace id',
            'trace number',
            'trace no',
            'instapay reference',
            'instapay ref',
            'instapay invoice no',
            'pesonet reference',
            'bank reference',
            'remittance reference',
            'remittance no',
            'order id',
            'ref id',
        ];

        foreach ($priorityReferenceAliases as $alias) {
            $pattern = '/\b'.preg_quote($alias, '/').'\s*\.?\s*[:#-]?\s*(.*)$/i';
            foreach ($lines as $index => $line) {
                if (! preg_match($pattern, $line, $match)) {
                    continue;
                }

                // Check same line value
                $candidate = $this->extractReferenceValueFromString($match[1] ?? '', $text);
                if ($candidate && $this->isValidReferenceCandidate($candidate, $text)) {
                    return [$candidate, ucwords($alias), $match[1]];
                }

                // Check next line value
                $nextLineStr = $lines[$index + 1] ?? '';
                $nextCandidate = $this->extractReferenceValueFromString($nextLineStr, $text);
                if ($nextCandidate && $this->isValidReferenceCandidate($nextCandidate, $text)) {
                    return [$nextCandidate, ucwords($alias), $nextLineStr];
                }
            }
        }

        // Compact line pattern search for 8-24 character alphanumeric transaction codes
        $compact = preg_replace('/(?<=\d)[\s-](?=\d)/', '', $text);
        preg_match_all('/\b[A-Za-z0-9-]{8,24}\b/', (string) $compact, $matches);
        foreach ($matches[0] ?? [] as $candidate) {
            if ($this->isValidReferenceCandidate($candidate, $text)) {
                return [$candidate, 'Pattern Search', $candidate];
            }
        }

        return [null, null, null];
    }

    private function extractReferenceValueFromString(string $valueStr, string $fullContext): ?string
    {
        $source = trim($valueStr);
        if ($source === '') {
            return null;
        }

        // Split into tokens
        $tokens = preg_split('/\s+/', $source);
        foreach ($tokens as $token) {
            $token = trim($token, ':,.-#');
            if ($this->isValidReferenceCandidate($token, $fullContext)) {
                return $token;
            }
        }

        return null;
    }

    public function isValidReferenceCandidate(string $candidate, string $fullContext): bool
    {
        $candidate = trim($candidate);
        $upperCandidate = Str::upper($candidate);

        // Reject if length is out of range
        if (strlen($candidate) < 6 || strlen($candidate) > 36) {
            return false;
        }

        // Must contain at least one digit or standard transaction pattern
        if (! preg_match('/\d/', $candidate) && ! preg_match('/^[A-Z0-9-]{8,}$/i', $candidate)) {
            return false;
        }

        // Reject if candidate is a pure English dictionary word / name / excluded keyword
        if (in_array($upperCandidate, self::EXCLUDED_WORDS, true)) {
            return false;
        }

        // Reject pure mobile numbers
        if (preg_match('/^(?:09|\+?639)\d{9}$/', $candidate)) {
            return false;
        }

        // Reject pure monetary floats
        if (preg_match('/^\d+\.\d{1,2}$/', $candidate)) {
            return false;
        }

        // Reject if candidate is explicitly labeled as an Account Number in context
        if (preg_match('/(?:account\s*(?:number|no\.?|#)|bank\s*account|account\s*num)\s*[:#-]?\s*'.preg_quote($candidate, '/').'/i', $fullContext)) {
            return false;
        }

        // Reject if candidate is explicitly labeled as Exchange Rate
        if (preg_match('/exchange\s*rate\s*[:#-]?\s*.*'.preg_quote($candidate, '/').'/i', $fullContext)) {
            return false;
        }

        return true;
    }

    /**
     * 18 Priority Amount Aliases Extraction & Fee Exclusion
     */
    public function extractAmountAndCurrency(string $text, ?float $preAmount = null, ?string $preCurrency = null, string $provider = ''): array
    {
        $amount = null;
        $currency = $preCurrency;
        $matchedLabel = null;
        $rawCandidate = null;

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));

        // 18 Priority Amount Aliases in exact user requested priority order
        $priorityAmountAliases = [
            'transfer amount',
            'payment amount',
            'amount sent',
            'total amount sent',
            'remittance amount',
            'transaction amount',
            'received amount',
            'amount received',
            'amount paid',
            'paid amount',
            'amount in destination currency',
            'php amount',
            'peso amount',
            'total amount',
            'amount',
            'net amount',
            'grand total',
            'total',
        ];

        foreach ($priorityAmountAliases as $alias) {
            $pattern = '/\b'.preg_quote($alias, '/').'\s*\.?\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|EUR|GBP|Php|₱|\$)?\s*([\d,]+(?:\.\d{1,2})?)/i';

            foreach ($lines as $line) {
                // Ensure line is NOT a Fee, VAT, Discount, or Balance line
                if ($this->isFeeOrExchangeRateLine($line) && $alias !== 'transfer amount' && $alias !== 'payment amount') {
                    continue;
                }

                if (preg_match($pattern, $line, $match)) {
                    $valStr = str_replace(',', '', $match[1]);
                    $val = (float) $valStr;
                    if ($val > 0) {
                        $amount = $val;
                        $matchedLabel = ucwords($alias);
                        $rawCandidate = $match[0];
                        break 2;
                    }
                }
            }
        }

        // Fallback to pre-extracted amount if still null
        if ($amount === null && $preAmount && $preAmount > 0) {
            $amount = $preAmount;
            $matchedLabel = 'Pre-extracted Amount';
            $rawCandidate = (string) $preAmount;
        }

        // Fallback standalone currency amount regex search
        if ($amount === null) {
            if (preg_match('/(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|EUR|GBP|Php|₱|\$)\s*([\d,]+(?:\.\d{1,2})?)/i', $text, $m)) {
                $val = (float) str_replace(',', '', $m[1]);
                if ($val > 0 && ! $this->isFeeOrExchangeRateLine($m[0])) {
                    $amount = $val;
                    $matchedLabel = 'Currency Pattern Match';
                    $rawCandidate = $m[0];
                }
            }
        }

        // Currency Extraction & Normalization
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

        return [$amount, $currency, $matchedLabel, $rawCandidate];
    }

    private function isFeeOrExchangeRateLine(string $line): bool
    {
        return (bool) preg_match('/\b(?:fee|charge|service\s*fee|vat|tax|discount|exchange\s*rate|balance|account\s*number)\b/i', $line);
    }

    /**
     * Date & Time Normalization (Transaction Lines only, NO phone status bar clocks)
     */
    public function normalizeDateTime(string $text, ?string $preDatetime = null): array
    {
        $dateText = null;
        $timeText = null;
        $matchedDateLabel = null;
        $rawDateCandidate = null;
        $warning = null;

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));

        // Date priority labels
        $dateLabels = [
            'transaction date & time',
            'transfer date & time',
            'payment date & time',
            'date & time',
            'transaction date',
            'transfer date',
            'payment date',
            'paid on',
            'sent on',
            'completed on',
            'date',
        ];

        // Search lines containing explicit date labels first
        foreach ($dateLabels as $label) {
            $pattern = '/\b'.preg_quote($label, '/').'\s*\.?\s*[:#-]?\s*(.*)$/i';
            foreach ($lines as $line) {
                if (preg_match($pattern, $line, $m)) {
                    $matchedDateLabel = ucwords($label);
                    $rawDateCandidate = $m[1] ?: $line;
                    [$parsedDate, $parsedTime] = $this->parseDateAndExtractTime($line);
                    if ($parsedDate) {
                        $dateText = $parsedDate;
                    }
                    if ($parsedTime) {
                        $timeText = $parsedTime;
                    }
                    if ($dateText) {
                        break 2;
                    }
                }
            }
        }

        // Global Date Fallback if no label matched
        if (! $dateText) {
            $source = $preDatetime ?: $text;

            if (preg_match('/\b(20\d{2})[\/-](\d{1,2})[\/-](\d{1,2})\b/', $source, $m)) {
                $dateText = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                $rawDateCandidate = $m[0];
            } elseif (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})\b/', $source, $m)) {
                $first = (int) $m[1];
                $second = (int) $m[2];
                if ($first <= 12 && $second <= 12) {
                    $warning = 'AMBIGUOUS_DATE_ORDER';
                }
                $day = $first > 12 ? $first : $second;
                $month = $first > 12 ? $second : $first;
                $dateText = sprintf('%04d-%02d-%02d', $m[3], $month, $day);
                $rawDateCandidate = $m[0];
            } elseif (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+20\d{2}\b/i', $source, $m)) {
                try {
                    $dateText = Carbon::parse($m[0])->format('Y-m-d');
                    $rawDateCandidate = $m[0];
                } catch (\Throwable) {
                }
            } elseif (preg_match('/\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+20\d{2}\b/i', $source, $m)) {
                try {
                    $dateText = Carbon::parse($m[0])->format('Y-m-d');
                    $rawDateCandidate = $m[0];
                } catch (\Throwable) {
                }
            }
        }

        // Time Extraction - strictly from transaction lines (skip status bar clock at top line of screenshot)
        if (! $timeText) {
            // Skip line 0 if it looks like a single phone status bar clock e.g. "2:28 PM"
            $searchLines = count($lines) > 1 ? array_slice($lines, 1) : $lines;
            foreach ($searchLines as $line) {
                if (preg_match('/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AP]M)?\b/i', $line, $m)) {
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
                        break;
                    }
                }
            }
        }

        return [$dateText, $timeText, $matchedDateLabel, $rawDateCandidate, $warning];
    }

    private function parseDateAndExtractTime(string $line): array
    {
        $parsedDate = null;
        $parsedTime = null;

        if (preg_match('/\b(20\d{2})[\/-](\d{1,2})[\/-](\d{1,2})\b/', $line, $m)) {
            $parsedDate = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } elseif (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})\b/', $line, $m)) {
            $parsedDate = sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        } elseif (preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+20\d{2}\b/i', $line, $m)) {
            try {
                $parsedDate = Carbon::parse($m[0])->format('Y-m-d');
            } catch (\Throwable) {
            }
        } elseif (preg_match('/\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+20\d{2}\b/i', $line, $m)) {
            try {
                $parsedDate = Carbon::parse($m[0])->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        if (preg_match('/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AP]M)?\b/i', $line, $m)) {
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
                $parsedTime = sprintf('%02d:%02d:00', $hour, $minute);
            }
        }

        return [$parsedDate, $parsedTime];
    }

    private function extractName(string $text, string $labels): ?string
    {
        if (preg_match('/(?:'.$labels.')\s*[:#-]?\s*([\pL][\pL .,\'-]{2,80})/iu', $text, $match)) {
            $candidate = trim(preg_split('/\s{2,}|\R|\b(?:amount|reference|date|status|repeat|share|favorites|download|method)\b/i', $match[1])[0]);
            if (! in_array(Str::upper($candidate), self::EXCLUDED_WORDS, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractStatus(string $text): ?string
    {
        if (preg_match('/\b(failed|cancelled|canceled|reversed|declined|unsuccessful)\b/i', $text, $match)) {
            return 'FAILED';
        }
        if (preg_match('/\b(successful|successfully|completed|paid|approved|success|sent)\b/i', $text, $match)) {
            return 'SUCCESS';
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
