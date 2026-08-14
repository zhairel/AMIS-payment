<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\PaymentDemoChild;
use App\Http\Controllers\PaymentController;
use App\Services\PaymentEligibilityService;
use App\Services\DemoPaymentScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

echo "=======================================================\n";
echo "   AFPS ONSITE PAYMENT SYNC ACCEPTANCE TEST            \n";
echo "=======================================================\n\n";

$userId = 61;
$user = User::find($userId);
Auth::login($user);

// 1. Reset demo store clean in shared DB
$txIds = DB::table('finance_transactions')->where('user_id', $userId)->pluck('id');
$subIds = DB::table('payment_submissions')->where('user_id', $userId)->pluck('id');
if ($txIds->isNotEmpty()) {
    if (Schema::hasTable('finance_parent_notifications')) DB::table('finance_parent_notifications')->whereIn('finance_transaction_id', $txIds)->delete();
    if (Schema::hasTable('student_account_payments')) DB::table('student_account_payments')->whereIn('finance_transaction_id', $txIds)->delete();
    if (Schema::hasTable('finance_official_receipts')) DB::table('finance_official_receipts')->whereIn('finance_transaction_id', $txIds)->delete();
    DB::table('finance_transactions')->where('user_id', $userId)->delete();
}
if ($subIds->isNotEmpty()) {
    if (Schema::hasTable('student_account_payments')) DB::table('student_account_payments')->whereIn('payment_submission_id', $subIds)->delete();
    DB::table('payment_submissions')->where('user_id', $userId)->delete();
}
if (Schema::hasTable('family_advance_credits')) DB::table('family_advance_credits')->where('user_id', $userId)->delete();

echo "[SETUP] Demo store reset for User ID {$userId} (zhairel.lingasa@gmail.com)\n\n";

$ctrl = app(PaymentController::class);
$elig = app(PaymentEligibilityService::class);

// --- 1. INITIAL STARTING STATE IN AFPS ---
echo "--- 1. STARTING STATE IN AFPS ---\n";
$dashboard0 = $ctrl->showDashboard(request(), $elig)->getData();
$july0 = $dashboard0['monthlyGroups'][1];
$aug0 = $dashboard0['monthlyGroups'][2];

echo "AFPS July 2026: Due = ₱" . number_format($july0['total_due'], 2) . ", Paid = ₱" . number_format($july0['total_paid'], 2) . ", Rem = ₱" . number_format($july0['total_remaining'], 2) . "\n";
echo "AFPS Aug 2026:  Due = ₱" . number_format($aug0['total_due'], 2) . ", Paid = ₱" . number_format($aug0['total_paid'], 2) . ", Rem = ₱" . number_format($aug0['total_remaining'], 2) . "\n";

if (abs($july0['total_due'] - 11806.66) < 0.01 && abs($july0['total_remaining'] - 11806.66) < 0.01 && $july0['total_paid'] == 0) {
    echo "✓ PASS: AFPS starting state is July ₱11,806.66, August ₱11,806.66\n";
} else {
    echo "✗ FAIL: AFPS starting state error!\n";
    exit(1);
}

// --- 2. POST ₱4,000.00 ONSITE CASH PAYMENT (Simulating Admin Finance confirm) ---
echo "\n--- 2. POST ₱4,000.00 ONSITE CASH PAYMENT ---\n";
$subId1 = DB::table('payment_submissions')->insertGetId([
    'submission_number' => 'SUB-DEMO-' . now()->format('YmdHi') . '-001',
    'user_id' => $userId,
    'client_token' => (string) Str::uuid(),
    'method' => 'cash',
    'payment_mode' => 'onsite',
    'reference_no' => 'DEMO-OR-20260814-4001',
    'reference_normalized' => 'demo-or-20260814-4001',
    'receipt_hash' => hash('sha256', (string) Str::uuid()),
    'receipt_url' => 'finance/onsite/counter',
    'transaction_date' => now()->toDateString(),
    'transaction_at' => now(),
    'total_amount' => 4000.00,
    'status' => 'approved',
    'remarks' => 'Recorded Onsite Payment (Cash)',
    'submitted_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

$txId1 = DB::table('finance_transactions')->insertGetId([
    'transaction_number' => 'DEMO-TX-20260814-4001',
    'official_receipt_number' => 'DEMO-OR-20260814-4001',
    'user_id' => $userId,
    'payment_submission_id' => $subId1,
    'source' => 'ONSITE',
    'payment_method' => 'CASH',
    'reference_number' => 'N/A',
    'amount' => 4000.00,
    'currency' => 'PHP',
    'transaction_at' => now(),
    'status' => 'APPROVED',
    'allocation_snapshot' => json_encode([]),
    'created_by' => $userId,
    'approved_by' => $userId,
    'received_by' => $userId,
    'family_balance_after' => 7806.66,
    'remarks' => 'DEMO ONSITE PAYMENT',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('finance_official_receipts')->insert([
    'official_receipt_number' => 'DEMO-OR-20260814-4001',
    'finance_transaction_id' => $txId1,
    'issued_by' => $userId,
    'status' => 'ISSUED',
    'snapshot' => json_encode(['family_id' => $userId, 'amount' => 4000.00]),
    'issued_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

// Read fresh AFPS Dashboard
$dashboard1 = $ctrl->showDashboard(request(), $elig)->getData();
$july1 = $dashboard1['monthlyGroups'][1];
$aug1 = $dashboard1['monthlyGroups'][2];

echo "AFPS July 2026: Due = ₱" . number_format($july1['total_due'], 2) . ", Paid = ₱" . number_format($july1['total_paid'], 2) . ", Rem = ₱" . number_format($july1['total_remaining'], 2) . "\n";
echo "AFPS Aug 2026:  Due = ₱" . number_format($aug1['total_due'], 2) . ", Paid = ₱" . number_format($aug1['total_paid'], 2) . ", Rem = ₱" . number_format($aug1['total_remaining'], 2) . "\n";

if (abs($july1['total_remaining'] - 7806.66) < 0.01 && abs($july1['total_paid'] - 4000.00) < 0.01 && abs($aug1['total_remaining'] - 11806.66) < 0.01) {
    echo "✓ PASS: AFPS July balance decreased to ₱7,806.66 (Paid = ₱4,000.00), August unchanged.\n";
} else {
    echo "✗ FAIL: AFPS July balance did not decrease correctly!\n";
    exit(1);
}

// Check AFPS Transactions Tab
$txList1 = $dashboard1['familyFinanceTransactions'];
echo "AFPS Transactions count: " . $txList1->count() . "\n";
$firstTx = $txList1->first();
if ($firstTx && $firstTx['transaction_number'] === 'DEMO-TX-20260814-4001' && $firstTx['source'] === 'ONSITE' && $firstTx['method'] === 'CASH' && (float)$firstTx['amount'] == 4000.00) {
    echo "✓ PASS: AFPS Transactions tab displays the Onsite Cash transaction (₱4,000.00).\n";
} else {
    echo "✗ FAIL: Onsite transaction missing in AFPS Transactions!\n";
    exit(1);
}

// Check AFPS Notifications
$overdueNotif = $dashboard1['paymentNotifications']->firstWhere('type', 'overdue');
if ($overdueNotif && abs($overdueNotif['amount'] - 7806.66) < 0.01) {
    echo "✓ PASS: AFPS Overdue notification displays updated remaining balance ₱7,806.66.\n";
} else {
    echo "✗ FAIL: AFPS Overdue notification balance incorrect!\n";
    exit(1);
}

// --- 3. POST ADDITIONAL ₱10,000.00 (Cross-month allocation) ---
echo "\n--- 3. POST ADDITIONAL ₱10,000.00 ONSITE CASH PAYMENT (CROSS-MONTH) ---\n";
$subId2 = DB::table('payment_submissions')->insertGetId([
    'submission_number' => 'SUB-DEMO-' . now()->format('YmdHi') . '-002',
    'user_id' => $userId,
    'client_token' => (string) Str::uuid(),
    'method' => 'cash',
    'payment_mode' => 'onsite',
    'reference_no' => 'DEMO-OR-20260814-4002',
    'reference_normalized' => 'demo-or-20260814-4002',
    'receipt_hash' => hash('sha256', (string) Str::uuid()),
    'receipt_url' => 'finance/onsite/counter',
    'transaction_date' => now()->toDateString(),
    'transaction_at' => now(),
    'total_amount' => 10000.00,
    'status' => 'approved',
    'remarks' => 'Recorded Onsite Payment 2 (Cash)',
    'submitted_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

$txId2 = DB::table('finance_transactions')->insertGetId([
    'transaction_number' => 'DEMO-TX-20260814-4002',
    'official_receipt_number' => 'DEMO-OR-20260814-4002',
    'user_id' => $userId,
    'payment_submission_id' => $subId2,
    'source' => 'ONSITE',
    'payment_method' => 'CASH',
    'reference_number' => 'N/A',
    'amount' => 10000.00,
    'currency' => 'PHP',
    'transaction_at' => now(),
    'status' => 'APPROVED',
    'allocation_snapshot' => json_encode([]),
    'created_by' => $userId,
    'approved_by' => $userId,
    'received_by' => $userId,
    'family_balance_after' => 9613.32,
    'remarks' => 'DEMO ONSITE PAYMENT 2',
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::table('finance_official_receipts')->insert([
    'official_receipt_number' => 'DEMO-OR-20260814-4002',
    'finance_transaction_id' => $txId2,
    'issued_by' => $userId,
    'status' => 'ISSUED',
    'snapshot' => json_encode(['family_id' => $userId, 'amount' => 10000.00]),
    'issued_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

$dashboard2 = $ctrl->showDashboard(request(), $elig)->getData();
$july2 = $dashboard2['monthlyGroups'][1];
$aug2 = $dashboard2['monthlyGroups'][2];

echo "AFPS July 2026: Due = ₱" . number_format($july2['total_due'], 2) . ", Paid = ₱" . number_format($july2['total_paid'], 2) . ", Rem = ₱" . number_format($july2['total_remaining'], 2) . "\n";
echo "AFPS Aug 2026:  Due = ₱" . number_format($aug2['total_due'], 2) . ", Paid = ₱" . number_format($aug2['total_paid'], 2) . ", Rem = ₱" . number_format($aug2['total_remaining'], 2) . "\n";

if (abs($july2['total_remaining']) < 0.01 && abs($july2['total_paid'] - 11806.66) < 0.01 && abs($aug2['total_remaining'] - 9613.32) < 0.01 && abs($aug2['total_paid'] - 2193.34) < 0.01) {
    echo "✓ PASS: Cross-month allocation matches perfectly! July = ₱0.00 rem, August = ₱9,613.32 rem (Paid = ₱2,193.34).\n";
} else {
    echo "✗ FAIL: Cross-month allocation mismatch in AFPS!\n";
    exit(1);
}

// Clean up test records
$txIds = DB::table('finance_transactions')->where('user_id', $userId)->pluck('id');
$subIds = DB::table('payment_submissions')->where('user_id', $userId)->pluck('id');
if ($txIds->isNotEmpty()) {
    if (Schema::hasTable('finance_parent_notifications')) DB::table('finance_parent_notifications')->whereIn('finance_transaction_id', $txIds)->delete();
    if (Schema::hasTable('student_account_payments')) DB::table('student_account_payments')->whereIn('finance_transaction_id', $txIds)->delete();
    if (Schema::hasTable('finance_official_receipts')) DB::table('finance_official_receipts')->whereIn('finance_transaction_id', $txIds)->delete();
    DB::table('finance_transactions')->where('user_id', $userId)->delete();
}
if ($subIds->isNotEmpty()) {
    if (Schema::hasTable('student_account_payments')) DB::table('student_account_payments')->whereIn('payment_submission_id', $subIds)->delete();
    DB::table('payment_submissions')->where('user_id', $userId)->delete();
}
if (Schema::hasTable('family_advance_credits')) DB::table('family_advance_credits')->where('user_id', $userId)->delete();

echo "\n=======================================================\n";
echo "       ALL AFPS ACCEPTANCE TESTS PASSED (100%)         \n";
echo "=======================================================\n";
