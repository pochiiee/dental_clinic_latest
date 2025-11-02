<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanUpPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-up-pending-payments';
    

    /**
     * The console command description.
     *
     * @var string
     */
      protected $description = 'Delete appointments stuck in pending_payment for more than 30 minutes and release their slots.';

    /**
     * Execute the console command.
     */
     public function handle()
    {
        $cutoff = Carbon::now()->subMinutes(30);

        $deleted = Appointment::where('status', 'pending_payment')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('Cleaned up expired pending_payment appointments', [
            'count' => $deleted,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        $this->info("Cleaned up {$deleted} expired pending_payment appointments.");

        return Command::SUCCESS;
    }
}

