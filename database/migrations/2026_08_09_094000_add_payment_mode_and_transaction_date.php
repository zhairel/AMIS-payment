<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_submissions', 'payment_mode')) {
            Schema::table('payment_submissions', function (Blueprint $table) {
                $table->string('payment_mode', 40)->nullable()->after('method');
            });
        }

        if (! Schema::hasColumn('payment_submissions', 'transaction_date')) {
            Schema::table('payment_submissions', function (Blueprint $table) {
                $table->date('transaction_date')->nullable()->after('reference_normalized');
            });
        }

        if (! Schema::hasColumn('student_account_payments', 'payment_mode')) {
            Schema::table('student_account_payments', function (Blueprint $table) {
                $table->string('payment_mode', 40)->nullable()->after('method');
            });
        }

        if (! Schema::hasColumn('student_account_payments', 'transaction_date')) {
            Schema::table('student_account_payments', function (Blueprint $table) {
                $table->date('transaction_date')->nullable()->after('reference_no');
            });
        }
    }

    public function down(): void
    {
        Schema::table('student_account_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'transaction_date']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'transaction_date']);
        });
    }
};
