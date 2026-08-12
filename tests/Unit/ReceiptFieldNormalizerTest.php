<?php

namespace Tests\Unit;

use App\Services\Receipts\ReceiptFieldNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReceiptFieldNormalizerTest extends TestCase
{
    #[DataProvider('identifierCases')]
    public function test_reference_or_transaction_identifier_fallback(string $ocrText, string $expected): void
    {
        $normalizer = new ReceiptFieldNormalizer;

        $this->assertSame($expected, $normalizer->extractTransactionReference($ocrText));
    }

    public function test_anb_telemoney_receipt_extraction(): void
    {
        $rawText = "anb\nTransaction Type: TeleMoney Transfer\nTransfer Amount\nExchange Rate\nFee Amount\nTotal Amount 260.32\nBank Name\nReference Number 400857439\nTransaction Date 08/08/2026\nGenerated online via: anb.com.sa";
        $normalizer = new ReceiptFieldNormalizer;
        $result = $normalizer->fromOcr(['raw_text' => $rawText]);

        $this->assertSame('ANB / TeleMoney Transfer', $result['provider']);
        $this->assertSame('400857439', $result['reference_number']);
        $this->assertSame('2026-08-08', $result['transaction_date']);
        $this->assertNull($result['transaction_time']);
        $this->assertEquals(260.32, $result['amount']);
        $this->assertSame('SAR', $result['currency']);
    }

    public function test_d360_receipt_extraction(): void
    {
        $rawText = "D360 Transaction Receipt\nDestination Country Philippines\nAmount in Destination Currency\nPHP 4,000.11\nTransfer type Bank Account\nBank Account Banco De Oro\nAccount Number 010478011996\nPurpose of Transfer Education/Study Fees";
        $normalizer = new ReceiptFieldNormalizer;
        $result = $normalizer->fromOcr(['raw_text' => $rawText]);

        $this->assertSame('D360', $result['provider']);
        $this->assertNull($result['reference_number']);
        $this->assertNull($result['transaction_date']);
        $this->assertNull($result['transaction_time']);
        $this->assertEquals(4000.11, $result['amount']);
        $this->assertSame('PHP', $result['currency']);
    }

    public function test_gcash_receipt_extraction(): void
    {
        $rawText = "GCash\nPayment Received\nAmount ₱1,500.00\nRef No. 900812345678\nDate: Aug 12, 2026 09:30 AM";
        $normalizer = new ReceiptFieldNormalizer;
        $result = $normalizer->fromOcr(['raw_text' => $rawText]);

        $this->assertSame('GCash', $result['provider']);
        $this->assertSame('900812345678', $result['reference_number']);
        $this->assertSame('2026-08-12', $result['transaction_date']);
        $this->assertSame('09:30:00', $result['transaction_time']);
        $this->assertEquals(1500.00, $result['amount']);
        $this->assertSame('PHP', $result['currency']);
    }

    public static function identifierCases(): array
    {
        return [
            'reference number' => ["Reference No.: 9043 7418 90510", '9043741890510'],
            'transaction id fallback' => ["Transaction ID: TXN-20260811-778899", 'TXN-20260811-778899'],
            'reference wins when both exist' => ["Transaction ID: TXN-55667788\nReference Number: REF-11223344", 'REF-11223344'],
            'identifier on next line' => ["Reference No.\n9043741890510", '9043741890510'],
        ];
    }
}
