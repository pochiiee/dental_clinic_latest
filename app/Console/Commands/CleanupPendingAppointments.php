<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupPendingAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-pending-appointments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marks pending appointments that have passed their implicit payment due time (based on created_at) as failed/cancelled.';

    /**
     * Define the timeout window (e.g., 15 minutes)
     * Must match the payment window given to the user.
     *
     * @var int
     */
    protected $timeoutMinutes = 15;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Calculate the cutoff time: 15 minutes ago
        $cutoffTime = Carbon::now()->subMinutes($this->timeoutMinutes);
        
        $newStatus = 'failed_timeout'; 
        
        $this->info("Checking for 'pending' appointments created before: " . $cutoffTime->toDateTimeString());

        // 2. Query: Find all 'pending' appointments where created_at is older than the cutoff time
        $expiredAppointments = Appointment::where('status', 'pending')
            // 👇 CRITICAL FIX: Use the existing created_at column
            ->where('created_at', '<', $cutoffTime)
            ->get();
            
        $count = $expiredAppointments->count();

        if ($count > 0) {
            $this->warn("Found {$count} expired pending appointments to clean up...");
            
            // 3. Batch update the found appointments
            $updatedCount = Appointment::whereIn('appointment_id', $expiredAppointments->pluck('appointment_id'))
                ->update(['status' => $newStatus]);

            Log::info('CleanupPendingAppointments: Marked expired pending appointments.', [
                'count' => $updatedCount,
                'status_changed_to' => $newStatus,
            ]);

            $this->info("Successfully updated {$updatedCount} appointments to status: '{$newStatus}'.");
        } else {
            $this->info('No expired pending appointments found.');
        }

        return Command::SUCCESS;
    }
}