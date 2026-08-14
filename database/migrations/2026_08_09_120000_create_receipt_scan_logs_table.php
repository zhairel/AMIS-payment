<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('scan_token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('parent_full_name');
            $table->json('student_names');
            $table->json('billing_ids');
            $table->string('receiving_channel', 30)->nullable();
            $table->string('receiving_account', 100)->nullable();
            $table->string('payment_mode', 30)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('detected_amount', 12, 2)->nullable();
            $table->decimal('expected_amount', 12, 2);
            $table->string('ocr_engine', 100)->nullable();
            $table->unsignedSmallInteger('ocr_passes')->default(0);
            $table->string('document_status', 30);
            $table->string('image_quality_status', 30);
            $table->string('amount_status', 30);
            $table->string('date_status', 30);
            $table->string('duplicate_status', 30);
            $table->string('scan_status', 30);
            $table->char('receipt_hash', 64)->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['user_id', 'scanned_at']);
            $table->index(['reference_no', 'detected_amount']);
            $table->index('receipt_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_scan_logs');
    }
};
