<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_demo_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('demo_student_number', 50)->unique();
            $table->string('grade_level', 50);
            $table->string('gender', 20)->nullable();
            $table->string('school_year', 20)->default('2026-2027');
            $table->decimal('tuition_fee', 12, 2)->default(0);
            $table->decimal('miscellaneous_fee', 12, 2)->default(0);
            $table->decimal('books_fee', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('enrollment_fee_paid', 12, 2)->default(0);
            $table->decimal('total_balance', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2)->default(0);
            $table->decimal('monthly_tuition', 12, 2)->default(0);
            $table->unsignedTinyInteger('installment_months')->default(9);
            $table->timestamps();

            $table->index(['user_id', 'school_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_demo_children');
    }
};
