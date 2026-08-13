<?php

namespace App\Services\Receipts\Adapters;

use App\Services\GoogleVisionService;

class GoogleVisionAdapter implements OcrEngineAdapterInterface
{
    public function __construct(
        private readonly GoogleVisionService $googleVision
    ) {}

    public function getEngineName(): string
    {
        return 'Google Vision';
    }

    public function checkAvailability(): array
    {
        $key = config('services.google_vision.key');

        return [
            'available' => filled($key),
            'reason' => filled($key) ? 'API key configured' : 'GOOGLE_VISION_KEY is not configured',
        ];
    }

    public function scan(string $filePath): array
    {
        $res = $this->googleVision->scanReceipt($filePath);

        if (! ($res['success'] ?? false)) {
            return [
                'engine' => 'Google Vision',
                'status' => 'FAILED',
                'raw_text' => '',
                'confidence' => null,
                'duration_ms' => 0,
                'error' => $res['status'] ?? 'Google Vision OCR failed',
            ];
        }

        return [
            'engine' => 'Google Vision',
            'status' => 'SUCCESS',
            'raw_text' => (string) ($res['raw_text'] ?? ''),
            'confidence' => $res['confidence'] ?? 0.95,
            'duration_ms' => 0,
            'parsed' => [
                'provider' => $res['detected_method'] ?? null,
                'reference_number' => $res['detected_ref'] ?? null,
                'normalized_reference' => $res['detected_ref'] ?? null,
                'amount' => $res['detected_amount'] ?? null,
                'transaction_date' => $res['detected_datetime'] ?? null,
                'sender_name' => $res['detected_sender'] ?? null,
                'receiver_name' => $res['detected_receiver'] ?? null,
            ],
        ];
    }
}
