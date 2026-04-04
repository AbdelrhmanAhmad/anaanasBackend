<?php

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteExpiredAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete accounts that have passed the 30-day grace period after deletion request';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired account deletion requests...');

        $expiredRequests = AccountDeletionRequest::where('scheduled_deletion_at', '<=', now())
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->with('user')
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('No expired deletion requests found.');
            return 0;
        }

        $this->info("Found {$expiredRequests->count()} expired deletion request(s).");

        $deletedCount = 0;
        foreach ($expiredRequests as $request) {
            try {
                DB::transaction(function () use ($request, &$deletedCount) {
                    $user = $request->user;

                    if ($user) {
                        // Delete all user tokens
                        $user->tokens()->delete();

                        // Delete user's posts, comments, etc. (cascade should handle this)
                        // Or soft delete if you prefer
                        $user->delete();

                        $this->info("Deleted account for user ID: {$user->id} ({$user->email})");
                    }

                    // Mark deletion request as processed
                    $request->update(['deleted_at' => now()]);
                    $deletedCount++;
                });
            } catch (\Exception $e) {
                $this->error("Failed to delete account for request ID {$request->id}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully deleted {$deletedCount} account(s).");
        return 0;
    }
}

