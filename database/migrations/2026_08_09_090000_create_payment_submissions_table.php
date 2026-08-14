<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_number', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_token')->unique();
            $table->string('method', 30);
            $table->string('reference_no', 100);
            $table->string('reference_normalized', 100);
            $table->char('receipt_hash', 64);
            $table->decimal('total_amount', 12, 2);
            $table->text('receipt_url');
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->string('ocr_status', 30)->default('skipped');
            $table->longText('ocr_raw_text')->nullable();
            $table->string('ocr_scanned_ref', 120)->nullable();
            $table->decimal('ocr_scanned_amount', 12, 2)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['user_id', 'method', 'reference_normalized'], 'payment_submission_reference_unique');
            $table->unique(['user_id', 'receipt_hash'], 'payment_submission_receipt_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::table('student_account_payments', function (Blueprint $table) {
            $table->foreignId('payment_submission_id')
                ->nullable()
                ->after('id')
                ->constrained('payment_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_account_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_submission_id');
        });

        Schema::dropIfExists('payment_submissions');
    }
};
