<?php

namespace Tests\Unit;

use App\Models\PaymentDemoChild;
use App\Services\DemoPaymentScheduleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoPaymentScheduleServiceTest extends TestCase
{
    public function test_it_generates_paid_enrollment_and_nine_monthly_installments_without_official_ids(): void
    {
        Carbon::setTestNow('2026-08-13 17:45:00');

        $children = collect(range(1, 3))->map(function (int $id) {
            $child = new PaymentDemoChild([
                'display_name' => 'DEMO CHILD '.$id,
                'demo_student_number' => 'AFPS-DEMO-'.$id,
                'grade_level' => 'Grade '.$id,
                'school_year' => '2026-2027',
                'enrollment_fee_paid' => 4000,
                'remaining_balance' => 32440,
                'monthly_tuition' => 3604.44,
                'installment_months' => 9,
            ]);
            $child->id = $id;

            return $child;
        });

        $groups = app(DemoPaymentScheduleService::class)->build($children);

        $this->assertCount(10, $groups);
        $this->assertSame(3, $groups[0]['paid_count']);
        $this->assertSame(0, $groups[0]['unpaid_count']);
        $this->assertSame(12000.0, $groups[0]['total_paid']);
        $this->assertSame('JULY 2026', $groups[1]['month_label']);
        $this->assertTrue($groups[1]['is_overdue']);
        $this->assertSame(10813.32, $groups[1]['total_remaining']);
        $this->assertSame('MARCH 2027', $groups[9]['month_label']);
        $this->assertSame(10813.44, $groups[9]['total_remaining']);
        $this->assertSame(97320.0, round(collect($groups)->skip(1)->sum('total_due'), 2));
        $this->assertFalse($groups[1]['children'][0]['payment_allowed']);
        $this->assertNull($groups[1]['children'][0]['billing_id']);

        $installments = app(DemoPaymentScheduleService::class)->installmentsFor($children->first());

        $this->assertCount(9, $installments);
        $this->assertSame('JULY 2026', $installments[0]['month']);
        $this->assertSame('Overdue', $installments[0]['status']);
        $this->assertSame('AUGUST 2026', $installments[1]['month']);
        $this->assertSame('Current', $installments[1]['status']);
        $this->assertSame('MARCH 2027', $installments[8]['month']);
        $this->assertSame(3604.48, $installments[8]['original']);
        $this->assertSame(32440.0, round(collect($installments)->sum('original'), 2));

        Carbon::setTestNow();
    }

    public function test_it_keeps_each_grade_level_monthly_tuition_and_rounding_total(): void
    {
        Carbon::setTestNow('2026-08-13 17:45:00');

        $fees = [
            ['grade' => 'Grade 1', 'remaining' => 32440.00, 'monthly' => 3604.44],
            ['grade' => 'Grade 3', 'remaining' => 33480.00, 'monthly' => 3720.00],
            ['grade' => 'Grade 5', 'remaining' => 34760.00, 'monthly' => 3862.22],
        ];
        $children = collect($fees)->map(function (array $fee, int $index) {
            $child = new PaymentDemoChild([
                'display_name' => 'DEMO CHILD '.($index + 1),
                'demo_student_number' => 'AFPS-DEMO-'.($index + 1),
                'grade_level' => $fee['grade'],
                'school_year' => '2026-2027',
                'enrollment_fee_paid' => 4000,
                'remaining_balance' => $fee['remaining'],
                'monthly_tuition' => $fee['monthly'],
                'installment_months' => 9,
            ]);
            $child->id = $index + 1;

            return $child;
        });

        $groups = app(DemoPaymentScheduleService::class)->build($children);

        $this->assertSame([3604.44, 3720.0, 3862.22], collect($groups[1]['children'])->pluck('original_amount')->all());
        $this->assertSame(11186.66, $groups[1]['total_due']);
        $this->assertSame(11186.72, $groups[9]['total_due']);
        $this->assertSame(100680.0, round(collect($groups)->skip(1)->sum('total_due'), 2));

        Carbon::setTestNow();
    }
}
