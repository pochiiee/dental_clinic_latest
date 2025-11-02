<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Customer\AppointmentController;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-appointment-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily appointment reminders to all users with confirmed appointments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting appointment reminders...');
        
        $appointmentController = new AppointmentController();
        $result = $appointmentController->sendDailyReminders();
        
        $this->info("Appointment reminders completed!");
        $this->info("Sent: {$result['sent']}, Failed: {$result['failed']}, Total: {$result['total']}");
        
        return Command::SUCCESS;
    }
}
