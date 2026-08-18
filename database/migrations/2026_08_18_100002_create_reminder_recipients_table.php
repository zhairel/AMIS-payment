<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('reminder_campaigns')->cascadeOnDelete();
            $table->string('normalized_email');         // Trimmed, lowercase
            $table->string('parent_name')->nullable();  // Display name (Father/Mother/Guardian)
            $table->enum('status', [
                'PENDING',
                'PROCESSING',
                'SENT',
                'RETRY',
                'FAILED',
            ])->default('PENDING');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->string('smtp_message_id')->nullable();
            $table->timestamps();

            // CRITICAL: One unique email per campaign — prevents all duplicate delivery
            $table->unique(['campaign_id', 'normalized_email']);
            $table->index(['campaign_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_recipients');
    }
};
