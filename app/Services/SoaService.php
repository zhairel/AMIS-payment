<?php

namespace App\Services;

use App\Models\EnrollmentApplicant;
use App\Models\SchoolFee;
use App\Models\SoaMonthlyBilling;
use App\Models\Student;
use App\Models\StudentAccount;

class SoaService
{
    /**
     * Generate SOA + 9 monthly billing installments for a newly approved student.
     */
    public function generate(Student $student, EnrollmentApplicant $applicant): StudentAccount
    {
        $fee = SchoolFee::forGrade($applicant->grade_level, $applicant->school_year);

        if (!$fee) {
            throw new \Exception("No school fees found for {$applicant->grade_level} SY {$applicant->school_year}");
        }

        $tuition            = (float) $fee->tuition_fee;
        $misc               = (float) $fee->misc_fee;
        $books              = (float) $fee->books_fee;
        $discountPercentage = (float) ($applicant->discount_percentage ?? 0);
        $discountAmount     = (float) ($applicant->discount_amount ?? 0);

        // Calculate discount on Tuition only (System Rule 2)
        if ($discountPercentage > 0 && $discountAmount <= 0) {
            $discountAmount = round($tuition * ($discountPercentage / 100), 2);
            $applicant->update(['discount_amount' => $discountAmount]);
        }

        $discountedTuition = max(0, $tuition - $discountAmount);
        $gross             = $discountedTuition + $misc + $books; // Final Assessment

        $enrollFee         = 4000.00; // Enrollment Fee (Month 0)
        $totalBalance      = $gross;
        $balanceForInstallment = max(0, $totalBalance - $enrollFee);
        $installmentMonths = 9;

        // Calculate monthly installment amount with rounding safety (System Rule 6)
        $monthlyTuition = round($balanceForInstallment / $installmentMonths, 2);

        $account = StudentAccount::create([
            'student_id'              => $student->id,
            'enrollment_applicant_id' => $applicant->id,
            'school_year'             => $applicant->school_year,
            'grade_level'             => $applicant->grade_level,
            'tuition_fee'             => $tuition,
            'monthly_tuition'         => $monthlyTuition,
            'miscellaneous_fee'       => $misc,
            'books_fee'               => $books,
            'sibling_order'           => $applicant->sibling_order,
            'discount_type'           => $discountPercentage > 0 ? ($applicant->discount_type ?: 'sibling') : null,
            'discount_percentage'     => $discountPercentage,
            'discount_amount'         => $discountAmount,
            'gross_total'             => $gross,
            'enrollment_fee_paid'     => $enrollFee,
            'total_balance'           => $totalBalance,
            'amount_paid'             => $enrollFee,
            'remaining_balance'       => $balanceForInstallment,
            'status'                  => $balanceForInstallment > 0 ? 'partial' : 'paid',
        ]);

        // Generate Enrollment (0) + 9 monthly billing rows (July=1 to March=9)
        $this->generateMonthlyBillings($account, $student, $balanceForInstallment, $enrollFee, $installmentMonths, $applicant->school_year);

        return $account;
    }

    private function generateMonthlyBillings(
        StudentAccount $account,
        Student $student,
        float $balanceForInstallment,
        float $enrollFee,
        int $installmentMonths,
        string $schoolYear
    ): void {
        $startYear = (int) explode('-', $schoolYear)[0]; // e.g. 2026
        // Set up Month 0
        SoaMonthlyBilling::create([
            'student_account_id' => $account->id,
            'student_id'         => $student->id,
            'month_number'       => 0,
            'month_name'         => 'Enrollment',
            'due_date'           => "{$startYear}-06-15",
            'amount_due'         => $enrollFee,
            'description'        => 'Enrollment Fee',
            'status'             => 'paid',
            'paid_at'            => now(),
        ]);
        // Calculate even installments
        $baseInstallment = round($balanceForInstallment / $installmentMonths, 2);
        $totalAllocated  = 0.00;

        $monthsList = [
            1 => ['July',      "{$startYear}-07-15"],
            2 => ['August',    "{$startYear}-08-15"],
            3 => ['September', "{$startYear}-09-15"],
            4 => ['October',   "{$startYear}-10-15"],
            5 => ['November',  "{$startYear}-11-15"],
            6 => ['December',  "{$startYear}-12-15"],
            7 => ['January',   ($startYear + 1) . '-01-15'],
            8 => ['February',  ($startYear + 1) . '-02-15'],
            9 => ['March',     ($startYear + 1) . '-03-15'],
        ];

        foreach ($monthsList as $num => [$name, $due]) {
            $amount = $baseInstallment;

            // Adjust the final month's installment to prevent rounding errors (System Rule 6)
            if ($num === $installmentMonths) {
                $amount = round($balanceForInstallment - $totalAllocated, 2);
            } else {
                $totalAllocated += $amount;
            }

            SoaMonthlyBilling::create([
                'student_account_id' => $account->id,
                'student_id'         => $student->id,
                'month_number'       => $num,
                'month_name'         => $name,
                'due_date'           => $due,
                'amount_due'         => $amount,
                'description'        => 'Monthly Tuition Installment',
                'status'             => 'unpaid',
            ]);
        }
    }
}
