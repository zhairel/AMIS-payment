<?php

namespace Tests\Unit;

use App\Models\SoaMonthlyBilling;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\StudentAccountPayment;
use App\Services\PaymentEligibilityService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class PaymentEligibilityServiceTest extends TestCase
{
    public function test_future_month_is_locked_by_oldest_outstanding_month(): void
    {
        [$student, $july, $august] = $this->studentWithJulyAndAugust();

        $result = (new PaymentEligibilityService())->check($student, $august);

        $this->assertFalse($result['payment_allowed']);
        $this->assertSame('previous_balance', $result['reason']);
        $this->assertSame('JULY 2026', $result['oldest_outstanding_month']);
        $this->assertSame(3720.0, $result['oldest_outstanding_amount']);
    }

    public function test_next_month_is_allowed_after_previous_month_is_paid(): void
    {
        [$student, $july, $august] = $this->studentWithJulyAndAugust();
        $july->status = 'paid';

        $result = (new PaymentEligibilityService())->check($student, $august);

        $this->assertTrue($result['payment_allowed']);
        $this->assertNull($result['reason']);
    }

    public function test_paid_billing_has_no_remaining_balance_without_a_verified_payment_row(): void
    {
        [, $july] = $this->studentWithJulyAndAugust();
        $july->status = 'paid';

        $remaining = (new PaymentEligibilityService())->remainingBalance($july);

        $this->assertSame(0.0, $remaining);
    }

    public function test_pending_payment_blocks_duplicate_payment_for_same_month(): void
    {
        [$student, $july, $august] = $this->studentWithJulyAndAugust();
        $july->status = 'paid';
        $pendingPayment = new StudentAccountPayment();
        $pendingPayment->setRawAttributes(['status' => 'pending', 'amount' => 3720]);
        $august->setRelation('payments', new Collection([$pendingPayment]));

        $result = (new PaymentEligibilityService())->check($student, $august);

        $this->assertFalse($result['payment_allowed']);
        $this->assertSame('pending_payment', $result['reason']);
    }

    public function test_verified_partial_payment_reduces_only_the_current_month_balance(): void
    {
        [$student, $july, $august] = $this->studentWithJulyAndAugust();
        $partialPayment = new StudentAccountPayment();
        $partialPayment->setRawAttributes(['status' => 'verified', 'amount' => 1000]);
        $july->setRelation('payments', new Collection([$partialPayment]));

        $julyResult = (new PaymentEligibilityService())->check($student, $july);
        $augustResult = (new PaymentEligibilityService())->check($student, $august);

        $this->assertTrue($julyResult['payment_allowed']);
        $this->assertSame(2720.0, $julyResult['remaining_amount']);
        $this->assertFalse($augustResult['payment_allowed']);
        $this->assertSame(2720.0, $augustResult['oldest_outstanding_amount']);
    }

    private function studentWithJulyAndAugust(): array
    {
        $student = (new Student())->forceFill(['id' => 7]);
        $account = (new StudentAccount())->forceFill(['id' => 32, 'student_id' => 7]);
        $july = $this->billing(101, 7, 32, 'July', '2026-07-15');
        $august = $this->billing(102, 7, 32, 'August', '2026-08-15');

        $account->setRelation('monthlyBillings', new Collection([$july, $august]));
        $student->setRelation('account', $account);

        return [$student, $july, $august];
    }

    private function billing(int $id, int $studentId, int $accountId, string $month, string $date): SoaMonthlyBilling
    {
        $billing = new SoaMonthlyBilling();
        $billing->setDateFormat('Y-m-d H:i:s');
        $billing->setRawAttributes([
            'id' => $id,
            'student_id' => $studentId,
            'student_account_id' => $accountId,
            'month_name' => $month,
            'due_date' => $date,
            'amount_due' => 3720,
            'status' => 'unpaid',
        ]);
        $billing->setRelation('payments', new Collection());

        return $billing;
    }
}
