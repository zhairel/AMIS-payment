<?php

namespace Tests\Unit;

use App\Services\ReceiptClassificationService;
use PHPUnit\Framework\TestCase;

class ReceiptClassificationServiceTest extends TestCase
{
    public function test_it_rejects_a_statement_of_account_as_a_receipt(): void
    {
        $result = (new ReceiptClassificationService())->classify([
            'raw_text' => 'STATEMENT OF ACCOUNT SY 2026-2027 Tuition Fees Required Payment Monthly Total Amount to pay',
            'detected_amount' => 3720,
            'detected_ref' => null,
            'detected_method' => null,
            'detected_datetime' => null,
        ]);

        $this->assertSame('not_receipt', $result['type']);
    }

    public function test_it_recognizes_a_payment_receipt_from_extracted_fields(): void
    {
        $result = (new ReceiptClassificationService())->classify([
            'raw_text' => 'GCash Transaction details Amount sent PHP 3,720.00 Reference number 1234567890123 Successful',
            'detected_amount' => 3720,
            'detected_ref' => '1234567890123',
            'detected_method' => 'GCash',
            'detected_datetime' => 'Aug 9, 2026 3:42 PM',
        ]);

        $this->assertSame('receipt', $result['type']);
        $this->assertGreaterThanOrEqual(5, $result['score']);
    }

    public function test_it_allows_manual_review_when_ocr_is_unavailable(): void
    {
        $result = (new ReceiptClassificationService())->classify(['raw_text' => null]);

        $this->assertSame('uncertain', $result['type']);
    }

    public function test_it_rejects_a_normal_computer_screenshot(): void
    {
        $result = (new ReceiptClassificationService())->classify([
            'raw_text' => 'Settings Appearance Dark mode Accent color Background Personalization',
            'detected_method' => null,
            'detected_ref' => null,
            'detected_amount' => null,
            'detected_datetime' => null,
        ]);

        $this->assertSame('not_receipt', $result['type']);
        $this->assertStringContainsString('does not appear to be a payment receipt', $result['message']);
    }

    public function test_it_rejects_a_monthly_payment_reminder_poster_even_with_a_false_reference(): void
    {
        $result = (new ReceiptClassificationService())->classify([
            'raw_text' => 'REMINDER! THOSE WHO HAVE NOT YET SETTLED THEIR JULY MONTHLY PAYMENT ARE REQUESTED TO DO SO AS SOON AS POSSIBLE.',
            'detected_method' => null,
            'detected_ref' => 'AVGGELAL',
            'detected_amount' => null,
            'detected_datetime' => null,
        ]);

        $this->assertSame('not_receipt', $result['type']);
        $this->assertStringContainsString('payment reminder', $result['message']);
    }
}
