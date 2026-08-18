<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_applicants', function (Blueprint $table) {
            $table->string('father_email')->nullable()->after('father_occupation');
            $table->string('mother_email')->nullable()->after('mother_occupation');
            $table->string('guardian_email')->nullable()->after('parent_email');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_applicants', function (Blueprint $table) {
            $table->dropColumn(['father_email', 'mother_email', 'guardian_email']);
        });
    }
};
