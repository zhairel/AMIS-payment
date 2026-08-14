<?php

namespace Tests\Unit;

use App\Services\CurrentFamilyPaymentCoverageService;
use PHPUnit\Framework\TestCase;

class CurrentFamilyPaymentCoverageServiceTest extends TestCase
{
    public function test_previous_balances_carry_into_current_total(): void
    {
        $coverage = (new CurrentFamilyPaymentCoverageService)->calculate(12000, 30000, 0, 10000);

        $this->assertSame(42000.0, $coverage['total_payable']);
        $this->assertSame(32000.0, $coverage['remaining_to_submit']);
        $this->assertFalse($coverage['awaiting_verification']);
    }

    public function test_active_pending_proofs_can_cover_full_payable_without_marking_paid(): void
    {
        $coverage = (new CurrentFamilyPaymentCoverageService)->calculate(12000, 30000, 7000, 35000);

        $this->assertSame(0.0, $coverage['remaining_to_submit']);
        $this->assertTrue($coverage['awaiting_verification']);
    }

    public function test_only_active_pending_amount_is_passed_into_coverage(): void
    {
        $coverage = (new CurrentFamilyPaymentCoverageService)->calculate(5000, 15000, 5000, 0);

        $this->assertSame(15000.0, $coverage['remaining_to_submit']);
        $this->assertFalse($coverage['awaiting_verification']);
    }
}
