<?php

namespace App\Services;

class CurrentFamilyPaymentCoverageService
{
    public function calculate(
        float $previousBalance,
        float $currentCharges,
        float $verifiedCurrentPayments,
        float $activePendingPayments
    ): array {
        $totalPayable = (float) round(max(0, $previousBalance) + max(0, $currentCharges), 2);
        $remaining = (float) max(0, round($totalPayable - max(0, $verifiedCurrentPayments) - max(0, $activePendingPayments), 2));

        return [
            'total_payable' => $totalPayable,
            'remaining_to_submit' => $remaining,
            'awaiting_verification' => $remaining <= 0.01 && $activePendingPayments > 0,
        ];
    }
}
