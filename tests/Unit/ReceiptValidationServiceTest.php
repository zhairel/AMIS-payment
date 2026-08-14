<?php

namespace Tests\Unit;

use App\Services\Receipts\ReceiptValidationService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReceiptValidationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_never_accepts_an_uncertain_reference(): void
    {
        $result = (new ReceiptValidationService)->validate([
            'normalized_reference' => '829108783', 'amount' => 1200,
            'currency' => 'SAR', 'transaction_date' => '2026-08-10',
            'transaction_status' => 'Successful',
        ], ['reference_number']);

        $this->assertFalse($result['valid']);
        $this->assertContains('REFERENCE_UNCERTAIN', array_column($result['errors'], 'code'));
    }

    public function test_it_rejects_a_failed_transaction_status(): void
    {
        $result = (new ReceiptValidationService)->validate([
            'normalized_reference' => 'ABC123456', 'amount' => 500,
            'currency' => 'PHP', 'transaction_date' => '2026-08-10',
            'transaction_status' => 'Reversed',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('TRANSACTION_NOT_SUCCESSFUL', array_column($result['errors'], 'code'));
    }

    public function test_missing_critical_fields_trigger_fallback(): void
    {
        $service = new ReceiptValidationService;
        $fields = ['normalized_reference' => null, 'amount' => 500, 'transaction_date' => '2026-08-10'];
        $this->assertTrue($service->needsFallback($fields, .95, $service->validate($fields)));
    }

    public function test_glare_does_not_force_slow_fallback_for_a_complete_screenshot(): void
    {
        $service = new ReceiptValidationService;
        $fields = [
            'provider' => 'GCash',
            'reference_number' => 'ABC123456789',
            'normalized_reference' => 'ABC123456789',
            'amount' => 4000,
            'currency' => 'PHP',
            'transaction_date' => '2026-08-10',
            'transaction_status' => 'Successful',
        ];
        $validation = $service->validate($fields);
        $context = [
            'raw_text' => 'GCash successful transfer reference ABC123456789 amount PHP 4,000.00 August 10 2026',
            'image_type' => 'SCREENSHOT',
            'blur_status' => 'ACCEPTABLE',
            'glare_detected' => true,
        ];

        $this->assertFalse($service->needsFallback($fields, .91, $validation, $context));

        $context['image_type'] = 'CAMERA_PHOTO';
        $this->assertTrue($service->needsFallback($fields, .91, $validation, $context));
    }

    public function test_later_date_in_current_year_is_allowed_for_finance_review(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');

        $result = (new ReceiptValidationService)->validate([
            'normalized_reference' => 'ABC123456', 'amount' => 500,
            'currency' => 'PHP', 'transaction_date' => '2026-12-31',
            'transaction_status' => 'Successful',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertContains('DATE_LATER_CURRENT_YEAR', array_column($result['warnings'], 'code'));
    }

    public function test_date_in_a_later_year_is_rejected(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');

        $result = (new ReceiptValidationService)->validate([
            'normalized_reference' => 'ABC123456', 'amount' => 500,
            'currency' => 'PHP', 'transaction_date' => '2027-01-01',
            'transaction_status' => 'Successful',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('DATE_YEAR_IN_FUTURE', array_column($result['errors'], 'code'));
    }
}
