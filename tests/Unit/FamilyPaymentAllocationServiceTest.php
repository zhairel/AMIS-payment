<?php

namespace Tests\Unit;

use App\Services\FamilyPaymentAllocationService;
use PHPUnit\Framework\TestCase;

class FamilyPaymentAllocationServiceTest extends TestCase
{
    public function test_partial_payment_stays_on_oldest_balance(): void
    {
        $result = (new FamilyPaymentAllocationService)->plan(10000, [
            ['id' => 1, 'remaining' => 15000],
            ['id' => 2, 'remaining' => 15000],
        ]);

        $this->assertSame([['id' => 1, 'amount' => 10000.0, 'remaining_after' => 5000.0]], $result['allocations']);
        $this->assertSame(0.0, $result['advance_credit']);
    }

    public function test_payment_carries_forward_to_next_month(): void
    {
        $result = (new FamilyPaymentAllocationService)->plan(8000, [
            ['id' => 1, 'remaining' => 5000],
            ['id' => 2, 'remaining' => 15000],
        ]);

        $this->assertSame(5000.0, $result['allocations'][0]['amount']);
        $this->assertSame(3000.0, $result['allocations'][1]['amount']);
        $this->assertSame(12000.0, $result['allocations'][1]['remaining_after']);
    }

    public function test_unused_amount_becomes_advance_credit(): void
    {
        $result = (new FamilyPaymentAllocationService)->plan(20000, [
            ['id' => 1, 'remaining' => 5000],
            ['id' => 2, 'remaining' => 12000],
        ]);

        $this->assertSame(3000.0, $result['advance_credit']);
    }
}
