<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Schedule;
use Carbon\Carbon;

class AppointmentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $appointment;
    public $service;
    public $schedule;

    public function __construct(User $user, Appointment $appointment, Service $service, Schedule $schedule)
    {
        $this->user = $user;
        $this->appointment = $appointment;
        $this->service = $service;
        $this->schedule = $schedule;
    }

    public function build()
    {
        $appointmentDate = Carbon::parse($this->appointment->appointment_date);
        $today = Carbon::today();
        
        // Determine if appointment is today or in the future
        if ($appointmentDate->isToday()) {
            $subject = 'Reminder: Your Dental Appointment Today';
            $greeting = 'This is a friendly reminder about your dental appointment **today**:';
        } else {
            $daysUntil = $today->diffInDays($appointmentDate);
            $subject = "Reminder: Your Upcoming Dental Appointment in {$daysUntil} day" . ($daysUntil > 1 ? 's' : '');
            $greeting = "This is a friendly reminder about your upcoming dental appointment in **{$daysUntil} day" . ($daysUntil > 1 ? 's' : '') . "**:";
        }

        return $this->subject($subject)
                    ->view('emails.appointment-reminder')
                    ->with([
                        'patientName' => $this->user->first_name . ' ' . $this->user->last_name,
                        'serviceName' => $this->service->service_name,
                        'appointmentDate' => $appointmentDate->format('F j, Y'),
                        'appointmentTime' => Carbon::parse($this->schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($this->schedule->end_time)->format('g:i A'),
                        'clinicName' => 'District Smile Dental Clinic',
                        'clinicAddress' => '123 Dental Street, City, State 12345',
                        'clinicPhone' => '(555) 123-4567',
                        'greeting' => $greeting,
                        'isToday' => $appointmentDate->isToday(),
                    ]);
    }
}