<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ReceiptSubmission;
use App\Services\Receipts\ReceiptOcrPipeline;
use App\Services\ReceiptClassificationService;
use App\Services\Receipts\ReceiptFieldNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

echo "=======================================================\n";
echo "   AFPS RECEIPT SCANNING & AUTO-FILL VERIFICATION      \n";
echo "=======================================================\n\n";

$passCount = 0;
function assertTest($condition, $name) {
    global $passCount;
    if ($condition) {
        echo "✓ PASS: {$name}\n";
        $passCount++;
    } else {
        echo "✗ FAIL: {$name}\n";
        throw new \Exception("Assertion failed: {$name}");
    }
}

// 1. Test Field Normalizer with GCash receipt text
$normalizer = new ReceiptFieldNormalizer();
$ocrResult = [
    'raw_text' => "GCash\nPayment Receipt\nAmount Sent: PHP 5,000.00\nRef No. 1234 5678 9012\nDate: Aug 14, 2026 10:35 AM\nStatus: Successfully Sent\nPaid to AMIS School",
    'detected_amount' => null,
    'detected_ref' => null,
    'detected_datetime' => null,
];
$normalized = $normalizer->fromOcr($ocrResult);

assertTest($normalized['amount'] === 5000.00, "GCash Amount extracted as 5000.00");
assertTest($normalized['reference_number'] === '1234 5678 9012' || $normalized['normalized_reference'] === '123456789012', "GCash Reference extracted correctly");
assertTest($normalized['transaction_date'] === '2026-08-14', "GCash Date extracted as 2026-08-14");
assertTest(str_starts_with($normalized['transaction_time'], '10:35'), "GCash Time extracted as 10:35 (got: {$normalized['transaction_time']})");
assertTest($normalized['provider'] === 'GCash' || $normalized['mode'] === 'gcash', "GCash Mode detected");

// 2. Test Partial OCR (Amount + Reference only, missing date/time/mode)
$partialOcr = [
    'raw_text' => "Transfer Amount: ₱5,000.00\nConfirmation Number: 987654321",
    'detected_amount' => null,
    'detected_ref' => null,
    'detected_datetime' => null,
];
$partialNorm = $normalizer->fromOcr($partialOcr);
assertTest($partialNorm['amount'] === 5000.00, "Partial OCR: Amount 5000.00 preserved");
assertTest($partialNorm['reference_number'] === '987654321', "Partial OCR: Reference 987654321 preserved");

// 3. Test Partial Classification does not mark valid partial receipt as not_receipt
$classifier = new ReceiptClassificationService();
$partialClass = $classifier->classify([
    'raw_text' => "Transfer Amount: ₱5,000.00\nConfirmation Number: 987654321",
    'detected_amount' => 5000.00,
    'detected_ref' => '987654321',
    'detected_method' => null,
    'detected_datetime' => null,
]);
assertTest($partialClass['type'] !== 'not_receipt', "Partial OCR is NOT classified as not_receipt (type is: {$partialClass['type']})");

// 4. Test Controller Store & Show Response
$user = User::firstOrCreate(
    ['email' => 'test.ocr@amis.edu.ph'],
    ['name' => 'Test Parent', 'username' => 'test_parent_ocr', 'password' => bcrypt('secret123'), 'role' => 'applicant']
);
\Illuminate\Support\Facades\Auth::login($user);

// Create a valid test PNG file
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$tmpFile = tempnam(sys_get_temp_dir(), 'rcpt_') . '.png';
file_put_contents($tmpFile, $pngData);

$uploadedFile = new UploadedFile($tmpFile, 'receipt.png', 'image/png', null, true);

$request = \Illuminate\Http\Request::create('/payment/receipts', 'POST', [], [], ['receipt' => $uploadedFile]);
$request->setUserResolver(fn() => $user);

$controller = app(\App\Http\Controllers\ReceiptSubmissionController::class);
$response = $controller->store($request, app(\App\Services\ReceiptFingerprintService::class));
$data = json_decode($response->getContent(), true);

assertTest($response->status() === 202, "Store response status is 202");
assertTest(!empty($data['submission_id']), "Submission ID returned: {$data['submission_id']}");
assertTest(isset($data['processing']), "Processing status returned: " . json_encode($data['processing']));

// Check Show endpoint
$receipt = ReceiptSubmission::where('submission_id', $data['submission_id'])->first();
$showReq = \Illuminate\Http\Request::create("/payment/receipts/{$receipt->submission_id}", 'GET');
$showReq->setUserResolver(fn() => $user);
$showRes = $controller->show($showReq, $receipt);
$showData = json_decode($showRes->getContent(), true);

assertTest($showRes->status() === 200, "Show response status is 200");
assertTest($showData['submission_id'] === $receipt->submission_id, "Show returns matching submission ID");

// Clean up
@unlink($tmpFile);
$receipt->delete();

echo "\n=======================================================\n";
echo "   RESULTS: {$passCount} / {$passCount} TESTS PASSED (100%)\n";
echo "=======================================================\n";
