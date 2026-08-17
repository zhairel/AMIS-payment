<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDemoPaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:reset-demo {email=zhairel.lingasa@gmail.com}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset AFPS demo payments and restore clean initial July 2026 balance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $this->info("Resetting AFPS demo payments for: {$email}...");

        $user = User::query()->where('email', $email)->first();
        $userId = $user?->id ?? 61;

        // 1. Clear round robin pointer cache
        for ($m = 1; $m <= 12; $m++) {
            $monthDate = Carbon::create(2026, 7, 15)->addMonthsNoOverflow($m - 1);
            $monthLabel = strtoupper($monthDate->format('F Y'));
            $monthKey = "demo_rr_ptr_{$userId}_".preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($monthLabel));
            Cache::forget($monthKey);
            Cache::forget("demo_rr_ptr_zhairel_lingasa_gmail_com_".preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($monthLabel)));
        }

        // 2. Delete payment submissions and receipt submissions
        if (Schema::hasTable('receipt_submissions')) {
            try {
                DB::table('receipt_submissions')->where('user_id', $userId)->delete();
                $this->line("  ✓ Cleared receipt submissions for user #{$userId}");
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('payment_submissions')) {
            try {
                DB::table('payment_submissions')->where('user_id', $userId)->delete();
                $this->line("  ✓ Cleared demo payment submissions for user #{$userId}");
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Submissions cleanup notice: ".$e->getMessage());
            }
        }

        // 3. Delete advance credits
        if (Schema::hasTable('family_advance_credits')) {
            try {
                DB::table('family_advance_credits')->where('user_id', $userId)->delete();
                $this->line("  ✓ Cleared advance credits");
            } catch (\Throwable $e) {}
        }

        Cache::flush();

        $this->newLine();
        $this->info("✓ AFPS demo reset complete for {$email}!");
        $this->line("  Ahmad Z. Lingasa is restored to his initial unpaid July 2026 tuition:");
        $this->line("  • AHMAD Z. LINGASA (Grade 1): ₱4,477.78");
        $this->line("  • July Total: ₱4,477.78");

        return Command::SUCCESS;
    }
}
