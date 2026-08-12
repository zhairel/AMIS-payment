<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReceiptSubmissionController;
use App\Http\Controllers\FinanceReceiptController;

// Root route
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('payment.dashboard');
    }
    return redirect()->route('login');
})->name('welcome');

// Dashboard redirect
Route::get('/dashboard', function () {
    return redirect()->route('payment.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Profile
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [\App\Http\Controllers\Auth\PasswordController::class, 'update'])->name('password.update');
});

// Google OAuth
Route::get('/g-signin', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')
    ->name('auth.google');
Route::match(['get', 'post'], '/g-callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('auth.google.callback');
Route::get('/auth/unsupported-browser', [GoogleAuthController::class, 'unsupportedBrowser'])
    ->name('auth.unsupported-browser');

// Microsoft OAuth
Route::get('/m-signin', [\App\Http\Controllers\MicrosoftAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')
    ->name('auth.microsoft');
Route::match(['get', 'post'], '/m-callback', [\App\Http\Controllers\MicrosoftAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('auth.microsoft.callback');

// Auth routes
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:100,1')->name('register.store');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// OTP Verification routes
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1')->name('auth.send-otp');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1')->name('auth.verify-otp');

// Email verification — signed link (replaces OTP)
Route::get('/verify-email/notice', [AuthController::class, 'showVerificationNotice'])->name('verification.notice');
Route::get('/verify-email/notice-compat', [AuthController::class, 'showVerificationNotice'])->name('verify.email.notice');
Route::get('/verify-email/status', [AuthController::class, 'checkVerificationStatus'])
    ->middleware('throttle:600,1')
    ->name('verify.email.status');
Route::post('/verify-email/resend', [AuthController::class, 'resendVerificationLink'])->middleware('throttle:100,1')->name('verify.email.resend');
Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('verification.send');
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'showVerifyConfirm'])
    ->middleware(['throttle:60,1'])
    ->name('verification.verify');

Route::post('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['throttle:60,1'])
    ->name('verification.verify.post');

// Dashboard — accessible to all authenticated and verified users
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payment/dashboard', [PaymentController::class, 'showDashboard'])->name('payment.dashboard');
    Route::get('/payment/checkout', [PaymentController::class, 'showCheckout'])->name('payment.checkout');
    Route::post('/payment/link-student', [PaymentController::class, 'linkStudent'])->name('payment.link-student');
    Route::post('/payment/submit', [PaymentController::class, 'submitPayment'])->name('payment.submit');
    Route::post('/payment/ocr-scan', [PaymentController::class, 'ocrScan'])->name('payment.ocr-scan');
    Route::post('/payment/ocr-scan-log', [PaymentController::class, 'recordReceiptScan'])->name('payment.ocr-scan-log');
    Route::post('/payment/check-duplicate', [PaymentController::class, 'checkDuplicate'])->name('payment.check-duplicate');
    Route::post('/payment/receipts', [ReceiptSubmissionController::class, 'store'])
        ->middleware('throttle:20,1')->name('payment.receipts.store');
    Route::get('/payment/receipts/{receipt}', [ReceiptSubmissionController::class, 'show'])
        ->name('payment.receipts.show');
    Route::post('/payment/receipts/{receipt}/client-fallback', [ReceiptSubmissionController::class, 'storeClientFallback'])
        ->middleware('throttle:10,1')->name('payment.receipts.client-fallback');
    Route::get('/payment/receipts/{receipt}/original', [ReceiptSubmissionController::class, 'original'])
        ->name('payment.receipts.original');
    Route::get('/payment/receipts/{receipt}/download-jpg', [ReceiptSubmissionController::class, 'downloadJpg'])
        ->name('payment.receipts.download-jpg');
    Route::get('/payment/receipts/{receipt}/download-pdf', [ReceiptSubmissionController::class, 'downloadPdf'])
        ->name('payment.receipts.download-pdf');
    Route::get('/students/{student}', [\App\Http\Controllers\StudentController::class, 'show'])
        ->name('students.show');
    Route::post('/activity/offline', [AuthController::class, 'setOffline'])->name('activity.offline');
});

Route::middleware(['auth', 'verified', 'finance'])->prefix('finance')->name('finance.')->group(function () {
    Route::get('/receipts', [FinanceReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/{receipt}', [FinanceReceiptController::class, 'show'])->name('receipts.show');
    Route::patch('/receipts/{receipt}', [FinanceReceiptController::class, 'update'])->name('receipts.update');
    Route::post('/receipts/{receipt}/action', [FinanceReceiptController::class, 'action'])->name('receipts.action');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/ai-receipt-test', [\App\Http\Controllers\AdminReceiptTestController::class, 'index'])->name('admin.receipt_test');
    Route::get('/admin/ai-receipt-test/env', [\App\Http\Controllers\AdminReceiptTestController::class, 'checkEnv'])->name('admin.receipt_test.env');
    Route::post('/admin/ai-receipt-test/process', [\App\Http\Controllers\AdminReceiptTestController::class, 'process'])->name('admin.receipt_test.process');
    Route::post('/admin/ai-receipt-test/compare', [\App\Http\Controllers\AdminReceiptTestController::class, 'compare'])->name('admin.receipt_test.compare');
    Route::get('/admin/ai-receipt-test/preview/{testId}', [\App\Http\Controllers\AdminReceiptTestController::class, 'preview'])->name('admin.receipt_test.preview');
});

if (app()->environment('local')) {
    Route::get('/test-errors/{code}', function ($code) {
        abort((int) $code);
    });
}

