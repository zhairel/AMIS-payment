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
        ];

        // Clean up any removed demo children for this user
        $validNumbers = array_column($children, 'demo_student_number');
        PaymentDemoChild::query()
            ->where('user_id', $user->id)
            ->whereNotIn('demo_student_number', $validNumbers)
            ->delete();

        $discountPercentage = DiscountSetting::current()
            ->siblingPercentageForFamilySize(count($children));

        foreach ($children as $child) {
            $tuition = 35800.00;
            $miscellaneous = 1900.00;
            $books = 5900.00;
            $discountAmount = round($tuition * ($discountPercentage / 100), 2);
            $enrollmentFeePaid = 4000.00;
            $totalBalance = round(max(0, $tuition - $discountAmount) + $miscellaneous + $books, 2);
            $monthlyTuition = 4400.00;
            $installmentMonths = 9;
            $remainingBalance = 22600.00; // Remaining after 4000 enrollment, 1000 books paid, and 16000 approved payment

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
                    'monthly_tuition' => $monthlyTuition,
                    'installment_months' => $installmentMonths,
                ])
            );
        }

        // Seed / Preserve the ₱16,000 Approved Payment Transaction (15-Aug-2026, Ref: 10539)
        if (\Illuminate\Support\Facades\Schema::hasTable('payment_submissions')) {
            \App\Models\PaymentSubmission::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'reference_no' => '10539',
                ],
                [
                    'submission_number' => 'PS-2026-0815-10539-'.$user->id,
                    'client_token' => 'tok_demo_'.\Illuminate\Support\Str::random(16),
                    'method' => 'bank_transfer',
                    'payment_mode' => 'online',
                    'account_received' => '10539',
                    'reference_normalized' => '10539',
                    'transaction_date' => '2026-08-15',
                    'transaction_at' => '2026-08-15 10:00:00',
                    'total_amount' => 16000.00,
                    'status' => 'approved',
                    'remarks' => 'Approved Payment Transaction - Allocated across monthly dues',
                    'submitted_at' => '2026-08-15 10:00:00',
                ]
            );
        }
    }
}

