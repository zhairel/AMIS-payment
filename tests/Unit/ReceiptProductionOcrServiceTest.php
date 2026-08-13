<?php

namespace Tests\Unit;

use App\Services\Receipts\Adapters\DocTrAdapter;
use App\Services\Receipts\Adapters\TesseractAdapter;
use App\Services\Receipts\ReceiptFieldConsensusService;
use App\Services\Receipts\ReceiptFieldNormalizer;
use App\Services\Receipts\ReceiptImagePreprocessorService;
use App\Services\Receipts\ReceiptProductionOcrService;
use App\Services\Receipts\ReceiptValidationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReceiptProductionOcrServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_strong_tesseract_result_uses_fast_path_without_doctr(): void
    {
        [$service, $tesseract, $docTr] = $this->service();
        $tesseract->shouldReceive('scan')->once()->andReturn($this->ocrResult($this->completeText()));
        $tesseract->shouldReceive('getEngineName')->once()->andReturn('Tesseract');
        $docTr->shouldNotReceive('scan');

        $result = $service->analyze('/tmp/receipt.png');

        $this->assertFalse($result['fallback_used']);
        $this->assertSame('OCR_SUCCESS', $result['ocr_status']);
        $this->assertSame('ABC123456789', $result['fields']['reference_number']);
        $this->assertCount(1, $result['attempts']);
    }

    public function test_doctr_fallback_merges_each_field_independently(): void
    {
        [$service, $tesseract, $docTr] = $this->service();
        $tesseract->shouldReceive('scan')->once()->andReturn($this->ocrResult(
            "GCash\nTransfer Amount: PHP 4,000.00\nTransaction Date: Aug 8, 2026 2:27 PM\nSuccessful"
        ));
        $tesseract->shouldReceive('getEngineName')->once()->andReturn('Tesseract');
        $docTr->shouldReceive('scan')->once()->andReturn($this->ocrResult(
            "GCash\nTransaction Reference Number: TO260808112724019\nTransaction Date: Aug 8, 2026 2:27 PM\nSuccessful"
        ));
        $docTr->shouldReceive('getEngineName')->once()->andReturn('docTR');

        $result = $service->analyze('/tmp/receipt.png');

        $this->assertTrue($result['fallback_used']);
        $this->assertSame('TO260808112724019', $result['fields']['reference_number']);
        $this->assertSame(4000.0, $result['fields']['amount']);
        $this->assertSame('OCR_SUCCESS', $result['ocr_status']);
        $this->assertCount(2, $result['attempts']);
    }

    public function test_upload_scan_ignores_transaction_label_and_returns_real_reference(): void
    {
        [$service, $tesseract, $docTr] = $this->service();
        $ocrResult = $this->ocrResult(
            "ANB TeleMoney\nTransaction Receipt\nReference Number\n400857439\nTransfer Amount: SAR 260.32\nTransaction Date: 08/08/2026\nSuccessful"
        );
        $ocrResult['detected_ref'] = 'TRANSACION';

        $tesseract->shouldReceive('scan')->once()->andReturn($ocrResult);
        $tesseract->shouldReceive('getEngineName')->once()->andReturn('Tesseract');
        $docTr->shouldNotReceive('scan');

        $result = $service->analyze('/tmp/receipt.png');

        $this->assertSame('400857439', $result['fields']['reference_number']);
        $this->assertNotSame('TRANSACION', $result['fields']['reference_number']);
    }

    public function test_both_engine_failures_return_manual_review_ocr_state(): void
    {
        [$service, $tesseract, $docTr] = $this->service();
        $failure = ['status' => 'FAILED', 'raw_text' => '', 'confidence' => null];
        $tesseract->shouldReceive('scan')->once()->andReturn($failure);
        $tesseract->shouldReceive('getEngineName')->once()->andReturn('Tesseract');
        $docTr->shouldReceive('scan')->once()->andReturn($failure);
        $docTr->shouldReceive('getEngineName')->once()->andReturn('docTR');

        $result = $service->analyze('/tmp/receipt.png');

        $this->assertTrue($result['fallback_used']);
        $this->assertSame('OCR_FAILED', $result['ocr_status']);
        $this->assertNull($result['fields']['reference_number']);
        $this->assertNull($result['fields']['amount']);
    }

    /** @return array{ReceiptProductionOcrService, TesseractAdapter&Mockery\MockInterface, DocTrAdapter&Mockery\MockInterface} */
    private function service(): array
    {
        $preprocessor = Mockery::mock(ReceiptImagePreprocessorService::class);
        $preprocessor->shouldReceive('preprocess')->once()->andReturn([
            'status' => 'SUCCESS',
            'image_type' => 'SCREENSHOT',
            'temp_enhanced_path' => null,
            'blur_status' => 'CLEAR',
            'glare_detected' => false,
            'reupload_required' => false,
        ]);
        $preprocessor->shouldReceive('cleanupTempFile')->once()->with(null);

        $tesseract = Mockery::mock(TesseractAdapter::class);
        $docTr = Mockery::mock(DocTrAdapter::class);
        $normalizer = new ReceiptFieldNormalizer;
        $validator = new ReceiptValidationService;

        return [
            new ReceiptProductionOcrService(
                $preprocessor,
                $tesseract,
                $docTr,
                $normalizer,
                $validator,
                new ReceiptFieldConsensusService($normalizer),
            ),
            $tesseract,
            $docTr,
        ];
    }

    private function ocrResult(string $text): array
    {
        return [
            'status' => 'SUCCESS',
            'raw_text' => $text,
            'confidence' => .94,
            'duration_ms' => 25,
        ];
    }

    private function completeText(): string
    {
        return "GCash\nTransaction Reference Number: ABC123456789\nTransfer Amount: PHP 4,000.00\nTransaction Date: Aug 8, 2026 2:27 PM\nSuccessful";
    }
}
