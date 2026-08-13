<?php

namespace Tests\Unit;

use App\Services\Receipts\ReceiptFieldNormalizer;
use App\Services\Receipts\ReceiptOcrComparatorService;
use PHPUnit\Framework\TestCase;

class ReceiptFieldNormalizerTest extends TestCase
{
    private ReceiptFieldNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ReceiptFieldNormalizer();
    }

    public function test_extracts_reference_number_across_priority_aliases(): void
    {
        $samples = [
            "Transaction Reference: TO260808112724019\nAmount: 4000.00" => 'TO260808112724019',
            "Reference Number: 400857439\nDate: 08/08/2026" => '400857439',
            "Ref No. 9876543210\nAmount: 500.00" => '9876543210',
            "InstaPay Ref: IN20260812999\nPaid Amount: PHP 1200.00" => 'IN20260812999',
            "Trace ID: TR99887766\nTotal: P4,000.00" => 'TR99887766',
        ];

        foreach ($samples as $ocrText => $expectedRef) {
            $parsed = $this->normalizer->fromOcr(['raw_text' => $ocrText]);
            $this->assertEquals($expectedRef, $parsed['reference_number'], "Failed extracting reference for sample: {$ocrText}");
        }
    }

    public function test_rejects_negative_reference_words_and_names(): void
    {
        $invalidSample = "Reference: NURHASAN OFFICIAL REQUESTED\nReference No.\nTO260808112724019";
        $parsed = $this->normalizer->fromOcr(['raw_text' => $invalidSample]);
        $this->assertEquals('TO260808112724019', $parsed['reference_number']);
        $this->assertNotEquals('NURHASAN', $parsed['reference_number']);
    }

    public function test_extracts_amount_prioritizing_transfer_amount_over_fees(): void
    {
        $ocrText = "Transfer Amount: ₱4,000.00\nFee Amount: ₱10.00\nTotal Amount: ₱4,010.00";
        $parsed = $this->normalizer->fromOcr(['raw_text' => $ocrText]);

        $this->assertEquals(4000.00, $parsed['amount']);
        $this->assertEquals('PHP', $parsed['currency']);
    }

    public function test_normalizes_international_currencies(): void
    {
        $ocrText = "Total Amount 260.32 SAR\nTransaction Date 08/08/2026";
        $parsed = $this->normalizer->fromOcr(['raw_text' => $ocrText]);

        $this->assertEquals(260.32, $parsed['amount']);
        $this->assertEquals('SAR', $parsed['currency']);
    }

    public function test_extracts_transaction_time_skipping_status_bar_clock(): void
    {
        $ocrText = "2:28 PM\nGCash\nTransaction Date: 08 Aug 2026 2:27 PM\nRef No. 123456789";
        $parsed = $this->normalizer->fromOcr(['raw_text' => $ocrText]);

        $this->assertEquals('2026-08-08', $parsed['transaction_date']);
        $this->assertEquals('14:27:00', $parsed['transaction_time']);
    }

    public function test_disambiguates_sending_provider_from_receiving_bank(): void
    {
        $ocrText = "Sent via GCash\nDestination Bank: BDO Unibank\nRef: 9988776655\nAmount: PHP 1,000.00";
        $parsed = $this->normalizer->fromOcr(['raw_text' => $ocrText]);

        $this->assertEquals('GCash', $parsed['provider']);
        $this->assertEquals('BDO Unibank', $parsed['receiving_bank']);
    }

    public function test_builds_field_level_multi_engine_consensus(): void
    {
        $engineResults = [
            'doctr' => [
                'engine' => 'docTR',
                'parsed' => [
                    'provider' => 'GCash',
                    'reference_number' => 'TO260808112724019',
                    'amount' => null,
                    'currency' => 'PHP',
                    'transaction_date' => '2026-08-08',
                    'transaction_time' => '14:27:00',
                ]
            ],
            'tesseract' => [
                'engine' => 'Tesseract',
                'parsed' => [
                    'provider' => 'GCash',
                    'reference_number' => '1T0260808112724019',
                    'amount' => 4000.00,
                    'currency' => 'PHP',
                    'transaction_date' => '2026-08-08',
                    'transaction_time' => '14:27:00',
                ]
            ],
            'paperless' => [
                'engine' => 'Paperless-ngx',
                'parsed' => [
                    'provider' => 'GCash',
                    'reference_number' => 'TO260808112724019',
                    'amount' => 4000.00,
                    'currency' => 'PHP',
                    'transaction_date' => '2026-08-08',
                    'transaction_time' => '14:27:00',
                ]
            ],
        ];

        $comparator = new ReceiptOcrComparatorService($this->normalizer);
        $consensus = $comparator->buildFieldConsensus($engineResults);

        $this->assertEquals('GCash', $consensus['provider']);
        $this->assertEquals('TO260808112724019', $consensus['reference_number']);
        $this->assertEquals(4000.00, $consensus['amount']);
        $this->assertEquals('2026-08-08', $consensus['transaction_date']);
    }
}
