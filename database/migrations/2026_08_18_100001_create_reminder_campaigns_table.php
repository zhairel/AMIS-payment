<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('school_year')->default('2026-2027');
            $table->enum('status', [
                'DRAFT',
                'PROCESSING',
                'PAUSED',
                'COMPLETED',
                'PARTIALLY_COMPLETED',
                'FAILED',
            ])->default('DRAFT');
            $table->string('paused_reason')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            // Source statistics
            $table->unsignedInteger('total_sources')->default(0);       // Raw email count from DB
            $table->unsignedInteger('total_unique')->default(0);        // After dedup
            $table->unsignedInteger('total_duplicates_removed')->default(0);
            $table->unsignedInteger('total_invalid')->default(0);       // Invalid format removed

            // Delivery statistics (live counters)
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_pending')->default(0);
            $table->unsignedInteger('total_retry')->default(0);
            $table->unsignedInteger('total_failed')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_campaigns');
    }
};
