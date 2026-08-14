<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE student_account_payments MODIFY COLUMN method ENUM('gcash', 'maya', 'bdo', 'remittance', 'cash') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE student_account_payments MODIFY COLUMN method ENUM('gcash', 'bdo', 'remittance', 'cash') NOT NULL");
    }
};
