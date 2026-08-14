<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_advance_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('remaining_amount', 12, 2);
            $table->string('status', 20)->default('active');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('student_account_payments', function (Blueprint $table) {
            $table->unsignedInteger('allocation_sequence')->nullable()->after('payment_submission_id');
            $table->string('allocation_source', 30)->nullable()->after('allocation_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('student_account_payments', function (Blueprint $table) {
            $table->dropColumn(['allocation_sequence', 'allocation_source']);
        });

        Schema::dropIfExists('family_advance_credits');
    }
};
