<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('first_child_percentage')
                ->default(5)
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('discount_settings', function (Blueprint $table) {
            $table->dropColumn('first_child_percentage');
        });
    }
};
