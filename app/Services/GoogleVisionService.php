<?php

namespace App\Services;

use App\Services\Receipts\ReceiptFieldNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleVisionService
{
    /**
     * Perform Document Text Detection (OCR) on an image file path.
     * Returns all extractable fields from a Philippine payment receipt.
     *
     * @param string $filePath Absolute path to the image file
     * @return array
     */
    public function scanReceipt(string $filePath): array
    {
        $apiKey = config('services.google_vision.key');

        if (empty($apiKey)) {
            Log::warning('Google Vision OCR skipped: GOOGLE_VISION_KEY is not configured in .env');
            return $this->emptyResult('skipped');
        }

        if (!file_exists($filePath)) {
            Log::error("Google Vision OCR failed: File not found at {$filePath}");
            return $this->emptyResult('error');
        }

        try {
            $imageBytes  = file_get_contents($filePath);
            $base64Image = base64_encode($imageBytes);

            $payload = [
                'requests' => [[
                    'image'    => ['content' => $base64Image],
                    'features' => [
                        ['type' => 'DOCUMENT_TEXT_DETECTION'],
                    ],
                ]],
            ];

            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", $payload);

            if ($response->failed()) {
                Log::error('Google Vision OCR API error: ' . $response->body());
                return $this->emptyResult('failed');
            }

            $data            = $response->json();
            $textAnnotations = $data['responses'][0]['textAnnotations'] ?? [];
            $fullAnnotation  = $data['responses'][0]['fullTextAnnotation'] ?? null;

            if (empty($textAnnotations)) {
                return $this->emptyResult('no_text');
            }

            // Raw text — first annotation contains the full document text
            $rawText   = $textAnnotations[0]['description'] ?? '';
            // Single-line version for regex
            $cleanText = preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $rawText));

            // ── OCR Confidence Score ───────────────────────────────────
            $confidence = $this->parseConfidence($fullAnnotation);

            // ── All extraction ─────────────────────────────────────────
            return [
                'success'             => true,
                'status'              => 'processed',
                'raw_text'            => $rawText,
                'confidence'          => $confidence,          // float 0-1 or null

                // Core transaction fields
                'detected_ref'        => $this->parseReferenceNumber($rawText),
                'detected_amount'     => $this->parseAmount($cleanText),
                'detected_datetime'   => $this->parseDatetime($cleanText),

                // People
                'detected_sender'     => $this->parseSender($cleanText),
                'detected_receiver'   => $this->parseReceiver($cleanText),
                'detected_merchant'   => $this->parseMerchant($cleanText),

                // Payment network/method
                'detected_method'     => $this->parsePaymentMethod($cleanText),
                'detected_account'    => $this->parseAccountNumber($cleanText),

                // QR code hint
                'has_qr'              => $this->detectQrHint($cleanText),
            ];

        } catch (\Exception $e) {
            Log::error('Google Vision OCR Exception: ' . $e->getMessage());
            return $this->emptyResult('failed');
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // PARSERS
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Reference / Transaction Number.
     * Handles: GCash (13-digit starting 5/9), BDO trace, generic labels.
     */
    private function parseReferenceNumber(string $text): ?string
    {
        return (new ReceiptFieldNormalizer)->extractTransactionReference($text);
    }

    /**
     * Amount paid — with or without peso sign.
     */
    private function parseAmount(string $text): ?float
    {
        // Prefer a labelled transaction amount over balances or fees that may
        // also appear elsewhere in the screenshot.
        if (preg_match('/(?:Total\s*Amount|Amount\s*(?:Sent|Paid|Transferred?)?)\s*[:\-]?\s*(?:₱|PHP|Php)?\s*([\d,]+\.\d{2})/iu', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        // With currency symbol ₱ / PHP / Php
        if (preg_match('/(?:₱|PHP|Php)\s*([\d,]+\.\d{2})\b/u', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        // Generic decimal number
        if (preg_match('/\b(\d{1,3}(?:,\d{3})*\.\d{2})\b/', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        return null;
    }

    /**
     * Date & Time — multiple PH formats.
     */
    private function parseDatetime(string $text): ?string
    {
        $patterns = [
            // Jun 27, 2025, 11:14 AM
            '/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\w*\.?\s+\d{1,2},?\s+\d{4}(?:\s*,?\s*\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?/i',
            // 06/27/2025 11:14 AM
            '/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?\b/i',
            // 2025-06-27 11:14
            '/\b\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?\b/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) return trim($m[0]);
        }
        return null;
    }

    /**
     * Sender Name — "From" / "Sent by" label extraction.
     */
    private function parseSender(string $text): ?string
    {
        if (preg_match('/(?:From|Sender|Sent\s*by|Remitter)\s*[:\-]?\s*([A-Z][A-Za-z\s\.]{2,40}?)(?=\s{2,}|\||$|To\b|Ref|Amount)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Receiver / Recipient Name — "To" / "Recipient" label extraction.
     */
    private function parseReceiver(string $text): ?string
    {
        if (preg_match('/(?:To|Recipient|Receiver|Beneficiary)\s*[:\-]?\s*([A-Z][A-Za-z\s\.]{2,40}?)(?=\s{2,}|\||$|From\b|Ref|Amount)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Merchant / Store Name — after "Merchant" or "Paid to" label.
     */
    private function parseMerchant(string $text): ?string
    {
        if (preg_match('/(?:Merchant|Paid\s*to|Store|Business)\s*[:\-]?\s*([A-Z][A-Za-z0-9\s\.\,\-]{2,50}?)(?=\s{2,}|\||$)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Payment Method / Network — detect brand name in text.
     */
    private function parsePaymentMethod(string $text): ?string
    {
        $methods = [
            'GCash'   => '/\bgcash\b/i',
            'Maya'    => '/\b(?:maya|paymaya)\b/i',
            'BDO'     => '/\bBDO\b/',
            'BPI'     => '/\bBPI\b/',
            'UnionBank' => '/\bunionbank\b/i',
            'Metrobank' => '/\bmetrobank\b/i',
            'PNB'     => '/\bPNB\b/',
            'LandBank' => '/\blandbank\b/i',
            'ShopeePay' => '/\bshopeepay\b/i',
            'Grabpay' => '/\bgrabpay\b/i',
        ];
        foreach ($methods as $name => $pattern) {
            if (preg_match($pattern, $text)) return $name;
        }
        return null;
    }

    /**
     * Account / Mobile Number — PH mobile format or account number pattern.
     */
    private function parseAccountNumber(string $text): ?string
    {
        // PH mobile: 09xxxxxxxxx or +639xxxxxxxxx
        if (preg_match('/\b(?:\+?63|0)9\d{9}\b/', $text, $m)) return $m[0];
        // Account number label
        if (preg_match('/(?:Account\s*(?:No\.?|#|Number))\s*[:\-]?\s*([\d\-\s]{8,20})/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Detect if text contains QR code mention.
     */
    private function detectQrHint(string $text): bool
    {
        return (bool) preg_match('/\b(?:QR|QRCode|scan|InstaPay)\b/i', $text);
    }

    /**
     * Average OCR confidence from fullTextAnnotation pages.
     */
    private function parseConfidence(?array $fullAnnotation): ?float
    {
        if (!$fullAnnotation) return null;
        $scores = [];
        foreach (($fullAnnotation['pages'] ?? []) as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if (isset($block['confidence'])) {
                    $scores[] = $block['confidence'];
                }
            }
        }
        return count($scores) > 0
            ? round(array_sum($scores) / count($scores), 3)
            : null;
    }

    /**
     * Empty/error result scaffold.
     */
    private function emptyResult(string $status): array
    {
        return [
            'success'           => false,
            'status'            => $status,
            'raw_text'          => null,
            'confidence'        => null,
            'detected_ref'      => null,
            'detected_amount'   => null,
            'detected_datetime' => null,
            'detected_sender'   => null,
            'detected_receiver' => null,
            'detected_merchant' => null,
            'detected_method'   => null,
            'detected_account'  => null,
            'has_qr'            => false,
        ];
    }
}
