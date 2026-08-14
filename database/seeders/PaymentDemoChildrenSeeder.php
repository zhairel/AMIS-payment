<?php

namespace Database\Seeders;

use App\Models\DiscountSetting;
use App\Models\PaymentDemoChild;
use App\Models\SchoolFee;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentDemoChildrenSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%lingasa%'])
                  ->orWhereRaw('LOWER(TRIM(email)) = ?', ['zhairel.lingasa@gmail.com']);
            })
            ->get();

        foreach ($users as $user) {
            $this->seedForUser($user);
        }
    }

    public function seedForUser(User $user): void
    {
        $isWcamsar = \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($user->email ?? ''), 'wcamsar') || (int) $user->id === 63;
        $children = $isWcamsar ? [
            ['display_name' => 'FATIMA W. CAMSAR', 'demo_student_number' => 'AFPS-DEMO-2026-001-'.$user->id, 'grade_level' => 'Grade 1', 'gender' => 'Female'],
            ['display_name' => 'OMAR W. CAMSAR', 'demo_student_number' => 'AFPS-DEMO-2026-002-'.$user->id, 'grade_level' => 'Grade 3', 'gender' => 'Male'],
            ['display_name' => 'ZAID W. CAMSAR', 'demo_student_number' => 'AFPS-DEMO-2026-003-'.$user->id, 'grade_level' => 'Grade 5', 'gender' => 'Male'],
            ['display_name' => 'AISHA W. CAMSAR', 'demo_student_number' => 'AFPS-DEMO-2026-004-'.$user->id, 'grade_level' => 'Grade 7', 'gender' => 'Female'],
        ] : [
            ['display_name' => 'AHMAD Z. LINGASA', 'demo_student_number' => 'AFPS-DEMO-2026-001-'.$user->id, 'grade_level' => 'Grade 1', 'gender' => 'Male'],
            ['display_name' => 'MARYAM Z. LINGASA', 'demo_student_number' => 'AFPS-DEMO-2026-002-'.$user->id, 'grade_level' => 'Grade 3', 'gender' => 'Female'],
            ['display_name' => 'YUSUF Z. LINGASA', 'demo_student_number' => 'AFPS-DEMO-2026-003-'.$user->id, 'grade_level' => 'Grade 5', 'gender' => 'Male'],
        ];

        $discountPercentage = DiscountSetting::current()
            ->siblingPercentageForFamilySize(count($children));

        foreach ($children as $child) {
            $fee = SchoolFee::forGrade($child['grade_level'], '2026-2027');
            if (! $fee) {
                continue;
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
    }
}

