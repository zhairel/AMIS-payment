<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dateTime('transaction_at')->nullable()->after('transaction_date');
            $table->char('perceptual_hash', 16)->nullable()->after('receipt_hash')->index();
            $table->decimal('ocr_confidence', 5, 4)->nullable()->after('ocr_status');
            $table->string('risk_status', 30)->default('manual_review')->after('ocr_confidence');
            $table->json('risk_flags')->nullable()->after('risk_status');
            $table->timestamp('risk_checked_at')->nullable()->after('risk_flags');
        });

        Schema::table('student_account_payments', function (Blueprint $table) {
            $table->dateTime('transaction_at')->nullable()->after('transaction_date');
        });

        Schema::table('receipt_scan_logs', function (Blueprint $table) {
            $table->dateTime('transaction_at')->nullable()->after('transaction_date');
            $table->char('perceptual_hash', 16)->nullable()->after('receipt_hash')->index();
            $table->decimal('ocr_confidence', 5, 4)->nullable()->after('ocr_passes');
            $table->json('risk_codes')->nullable()->after('scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_scan_logs', function (Blueprint $table) {
            $table->dropIndex(['perceptual_hash']);
            $table->dropColumn(['transaction_at', 'perceptual_hash', 'ocr_confidence', 'risk_codes']);
        });
        Schema::table('student_account_payments', fn (Blueprint $table) => $table->dropColumn('transaction_at'));
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropIndex(['perceptual_hash']);
            $table->dropColumn(['transaction_at', 'perceptual_hash', 'ocr_confidence', 'risk_status', 'risk_flags', 'risk_checked_at']);
        });
    }
};
