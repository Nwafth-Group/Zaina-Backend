<?php

namespace App\Console\Commands;

use App\Models\UserReferralCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteExpiredReferralCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'points:cleanup-expired-referrals';

    /**
     * The console command description.
     */
    protected $description = 'Delete expired temporary user referral codes from user_referral_codes table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired referral codes...');
        Log::info('Console command points:cleanup-expired-referrals started.');

        try {
            $deletedCount = UserReferralCode::where('expires_at', '<', now())->delete();

            $this->info("Successfully deleted {$deletedCount} expired referral codes.");
            Log::info("Console command points:cleanup-expired-referrals completed. Deleted: {$deletedCount} records.");
        } catch (\Exception $e) {
            $this->error('Error during cleanup: ' . $e->getMessage());
            Log::error('Error in points:cleanup-expired-referrals command: ' . $e->getMessage());
        }
    }
}
