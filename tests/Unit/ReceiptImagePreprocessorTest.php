<?php

namespace Tests\Unit;

use App\Services\Receipts\ReceiptImagePreprocessorService;
use PHPUnit\Framework\TestCase;

class ReceiptImagePreprocessorTest extends TestCase
{
    private ReceiptImagePreprocessorService $preprocessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preprocessor = new ReceiptImagePreprocessorService();
    }

    public function test_preprocesses_image_file_successfully(): void
    {
        $testImagePath = dirname(__DIR__, 2) . '/public/images/2x2-guide/female-standard.png';
        if (! file_exists($testImagePath)) {
            $this->markTestSkipped('Sample image not found');
        }

        $result = $this->preprocessor->preprocess($testImagePath);

        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertArrayHasKey('image_type', $result);
        $this->assertArrayHasKey('blur_score', $result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('temp_enhanced_path', $result);

        if (! empty($result['temp_enhanced_path'])) {
            $this->assertFileExists($result['temp_enhanced_path']);
            $this->preprocessor->cleanupTempFile($result['temp_enhanced_path']);
            $this->assertFileDoesNotExist($result['temp_enhanced_path']);
        }
    }

    public function test_handles_non_existent_image_gracefully(): void
    {
        $result = $this->preprocessor->preprocess('/non/existent/image.png');

        $this->assertEquals('FAILED', $result['status']);
        $this->assertFalse($result['document_detected']);
    }
}
