<?php

namespace Tests\Unit;

use App\Models\DiscountSetting;
use PHPUnit\Framework\TestCase;

class DiscountSettingTest extends TestCase
{
    public function test_family_sibling_discount_is_child_count_times_five_percent(): void
    {
        $setting = new DiscountSetting([
            'per_child_percentage' => 5,
            'first_child_percentage' => 5,
            'second_child_percentage' => 10,
            'third_child_percentage' => 15,
            'fourth_child_percentage' => 20,
            'is_active' => true,
        ]);

        $this->assertSame(0.0, $setting->siblingPercentageForFamilySize(1));
        $this->assertSame(10.0, $setting->siblingPercentageForFamilySize(2));
        $this->assertSame(15.0, $setting->siblingPercentageForFamilySize(3));
        $this->assertSame(20.0, $setting->siblingPercentageForFamilySize(4));
        $this->assertSame(25.0, $setting->siblingPercentageForFamilySize(5));
    }
}
