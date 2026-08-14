<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('student_manual_soas')) {
            Schema::create('student_manual_soas', function (Blueprint $table) {
                $table->id();
                $table->string('student_identifier')->index(); // student_number, amis_student_id, or demo_student_number
                $table->string('student_name');
                $table->string('family_email')->index();
                $table->string('grade_level')->nullable();
                $table->string('school_year')->default('2026-2027');
                $table->string('billing_month'); // e.g. AUGUST 2026, JULY 2026
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_current')->default(true);
                $table->string('file_path');
                $table->string('original_filename');
                $table->string('mime_type');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('uploaded_by')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['student_identifier', 'billing_month']);
                $table->index(['family_email', 'is_current']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_manual_soas');
    }
};
