<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('contact_number')->nullable();
            $table->string('student_full_name')->nullable();
            $table->string('grade_level')->nullable();
            $table->string('amis_id')->nullable();
            $table->string('concern_type');
            $table->string('subject');
            $table->text('description');
            $table->string('screenshot_path')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
