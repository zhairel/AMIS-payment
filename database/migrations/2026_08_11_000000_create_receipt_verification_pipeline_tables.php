<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('UPLOADED')->index();
            $table->string('original_filename');
            $table->string('original_mime', 100);
            $table->unsignedBigInteger('original_size');
            $table->string('original_receipt_path');
            $table->string('processed_receipt_path')->nullable();
            $table->char('receipt_hash', 64)->index();
            $table->char('perceptual_hash', 16)->nullable()->index();

            $table->string('provider', 120)->nullable();
            $table->string('reference_number', 150)->nullable();
            $table->string('normalized_reference', 150)->nullable()->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->date('transaction_date')->nullable();
            $table->time('transaction_time')->nullable();
            $table->string('sender_name', 180)->nullable();
            $table->string('receiver_name', 180)->nullable();
            $table->string('transaction_status', 80)->nullable();

            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->json('quality_assessment')->nullable();
            $table->string('primary_ocr_engine', 40)->nullable();
            $table->decimal('ocr_confidence', 5, 4)->nullable();
            $table->json('structured_ocr')->nullable();
            $table->json('uncertain_fields')->nullable();
            $table->string('duplicate_status', 30)->default('UNIQUE')->index();
            $table->json('duplicate_results')->nullable();
            $table->json('validation_results')->nullable();
            $table->text('review_reason')->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'normalized_reference']);
            $table->index(['amount', 'currency', 'transaction_date']);
        });

        Schema::create('receipt_ocr_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_submission_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 40);
            $table->unsignedSmallInteger('attempt_number');
            $table->string('source_variant', 30)->default('processed');
            $table->string('status', 30);
            $table->longText('raw_text')->nullable();
            $table->json('raw_json')->nullable();
            $table->json('structured_json')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('warnings')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['receipt_submission_id', 'attempt_number']);
            $table->index(['receipt_submission_id', 'engine']);
        });

        Schema::create('receipt_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->json('changes')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['receipt_submission_id', 'created_at']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->foreignId('receipt_submission_id')
                ->nullable()
                ->after('user_id')
                ->constrained('receipt_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_submission_id');
        });
        Schema::dropIfExists('receipt_audit_logs');
        Schema::dropIfExists('receipt_ocr_results');
        Schema::dropIfExists('receipt_submissions');
    }
};
