<?php

namespace Database\Seeders;

use App\Models\DiscountSetting;
use App\Models\PaymentDemoChild;
use App\Models\SchoolFee;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class PaymentDemoChildrenSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', ['zhairel.lingasa@gmail.com'])
            ->first();

        if (! $user) {
            throw new RuntimeException('The AFPS demo parent account was not found.');
        }

        $children = [
            ['display_name' => 'AHMAD Z. LINGASA', 'demo_student_number' => 'AFPS-DEMO-2026-001', 'grade_level' => 'Grade 1', 'gender' => 'Male'],
            ['display_name' => 'MARYAM Z. LINGASA', 'demo_student_number' => 'AFPS-DEMO-2026-002', 'grade_level' => 'Grade 3', 'gender' => 'Female'],
            ['display_name' => 'YUSUF Z. LINGASA', 'demo_student_number' => 'AFPS-DEMO-2026-003', 'grade_level' => 'Grade 5', 'gender' => 'Male'],
        ];
        $discountPercentage = DiscountSetting::current()
            ->siblingPercentageForFamilySize(count($children));

        foreach ($children as $child) {
            $fee = SchoolFee::forGrade($child['grade_level'], '2026-2027');
            if (! $fee) {
                throw new RuntimeException("No official fee schedule was found for {$child['grade_level']} SY 2026-2027.");
            }

            $tuition = round((float) $fee->tuition_fee, 2);
            $miscellaneous = round((float) $fee->misc_fee, 2);
            $books = round((float) $fee->books_fee, 2);
            $discountAmount = round($tuition * ($discountPercentage / 100), 2);
            $enrollmentFeePaid = 4000.0;
            $totalBalance = round(max(0, $tuition - $discountAmount) + $miscellaneous + $books, 2);
            $remainingBalance = round(max(0, $totalBalance - $enrollmentFeePaid), 2);
            $installmentMonths = 9;

            PaymentDemoChild::query()->updateOrCreate(
                ['demo_student_number' => $child['demo_student_number']],
                array_merge($child, [
                    'user_id' => $user->id,
                    'school_year' => '2026-2027',
                    'tuition_fee' => $tuition,
                    'miscellaneous_fee' => $miscellaneous,
                    'books_fee' => $books,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'enrollment_fee_paid' => $enrollmentFeePaid,
                    'total_balance' => $totalBalance,
                    'remaining_balance' => $remainingBalance,
                    'monthly_tuition' => round($remainingBalance / $installmentMonths, 2),
                    'installment_months' => $installmentMonths,
                ])
            );
        }

        PaymentDemoChild::query()
            ->where('user_id', $user->id)
            ->whereNotIn('demo_student_number', collect($children)->pluck('demo_student_number'))
            ->delete();
    }
}
