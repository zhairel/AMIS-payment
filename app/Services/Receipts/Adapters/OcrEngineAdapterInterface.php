<?php

namespace App\Services\Receipts\Adapters;

interface OcrEngineAdapterInterface
{
    public function getEngineName(): string;

    public function scan(string $filePath): array;

    public function checkAvailability(): array;
}
