<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Schedule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminAppointmentController extends Controller
{
    public function index()
    {
        // Make sure you're NOT filtering out cancelled appointments
        $appointments = Appointment::with([
                'patient', 
                'service.tools',
                'schedule',
                'payment'
            ])
            // REMOVE any whereNotIn or where clauses that filter status
            ->orderBy('appointment_date', 'asc')
            ->get()
            ->map(function ($apt) {
                $service = $apt->service;
                $schedule = $apt->schedule;
                $payment = $apt->payment;

                return [
                    'id'        => $apt->appointment_id,
                    'patient'   => trim(($apt->patient->first_name ?? '') . ' ' . ($apt->patient->last_name ?? '')),
                    'procedure' => $service?->service_name ?? 'N/A',
                    'tools'     => $service?->tools->pluck('tool_name')->toArray() ?? [],
                    'datetime'  => $apt->appointment_date
                        ? $apt->appointment_date->format('Y-m-d') . ' ' . ($schedule?->time_slot ?? '')
                        : 'N/A',
                    'time'      => $schedule?->time_slot ?? null,
                    'status'    => ucfirst($apt->status ?? 'Pending'),
                    'payment'   => $payment?->payment_status ?? 'Pending', 
                    'day'       => strtoupper(optional($apt->appointment_date)->format('D')),
                    'date' => optional($apt->appointment_date)->timezone('Asia/Manila')->format('Y-m-d'),
                    'schedule_id' => $apt->schedule_id,
                ];
            });


        $timeSlots = Schedule::select('start_time', 'end_time')
            ->get()
            ->map(function ($s) {
                $start = date('h:i A', strtotime($s->start_time));
                $end   = date('h:i A', strtotime($s->end_time));
                return "$start - $end";
            })
            ->unique()
            ->values()
            ->toArray();

        $stats = [
            'totalPending' => Appointment::where('status', 'pending')->count(),
            'cancelled'    => Appointment::where('status', 'cancelled')->count(),
            'rescheduled'  => Appointment::where('status', 'rescheduled')->count(),
            'completed'    => Appointment::where('status', 'completed')->count(),
        ];

        return Inertia::render('Admin/AppointmentTable', [
            'appointments' => $appointments,
            'stats'        => $stats,
            'timeSlots'    => $timeSlots,
        ]);
    }

    public function updateStatus($id)
    {
        $appointment = Appointment::findOrFail($id);
        
        $validated = request()->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled,rescheduled'
        ]);

        $appointment->update([
            'status' => $validated['status']
        ]);

        return back()->with('success', 'Appointment status updated.');
    }

    public function reschedule(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $appointment = Appointment::findOrFail($id);

            // Check if appointment is cancelled
            if ($appointment->status === 'cancelled') {
                return back()->with('error', 'Cancelled appointments cannot be rescheduled.');
            }

            $validated = $request->validate([
                'date' => 'required|date',
                'schedule_id' => 'required|exists:schedules,schedule_id',
            ]);

            // Check if reschedule is at least 12 hours before the original appointment
            $originalAppointmentDateTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->schedule->start_time);
            $currentDateTime = Carbon::now();
            $hoursDifference = $currentDateTime->diffInHours($originalAppointmentDateTime, false);

            if ($hoursDifference < 12) {
                return back()->with('error', 'Appointments can only be rescheduled at least 12 hours before the scheduled time.');
            }

            // Check if the selected schedule is available
            $isScheduleTaken = Appointment::where('appointment_date', $validated['date'])
                ->where('schedule_id', $validated['schedule_id'])
                ->where('appointment_id', '!=', $id)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($isScheduleTaken) {
                return back()->with('error', 'The selected time slot is already taken.');
            }

     
            $appointment->update([
                'appointment_date' => $validated['date'],
                'schedule_id' => $validated['schedule_id'],
                'status' => 'rescheduled',
            ]);

            DB::commit();

            return redirect()->route('admin.appointments.index')
                ->with('success', 'Appointment rescheduled successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reschedule failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to reschedule appointment.');
        }
    }

    public function cancel($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $appointment = Appointment::with('schedule')
                    ->where('appointment_id', $id)
                    ->first();

                if (!$appointment) {
                    return redirect()->back()->with('error', 'Appointment not found.');
                }

                if ($appointment->status === 'cancelled') {
                    return redirect()->back()->with('error', 'Appointment is already cancelled.');
                }

                Log::info("Admin cancelling appointment:", [
                    'appointment_id' => $appointment->appointment_id,
                    'current_status' => $appointment->status,
                    'appointment_date' => $appointment->appointment_date,
                ]);

                // 12-hour rule using the formatted time slot
                $appointmentDate = Carbon::parse($appointment->appointment_date);
                
                // Use the time slot from the schedule (e.g., "10:00 AM - 12:00 PM")
                $timeSlot = $appointment->schedule->time_slot ?? '10:00 AM - 12:00 PM';
                
                // Extract start time from time slot (first part before " - ")
                $startTimeString = explode(' - ', $timeSlot)[0];
                
                // Convert "10:00 AM" to Carbon time
                $startTime = Carbon::parse($startTimeString);
                
                // Combine date and time
                $appointmentDateTime = $appointmentDate->copy()
                    ->setTime($startTime->hour, $startTime->minute, $startTime->second);

                $currentDateTime = Carbon::now();
                $hoursDifference = $currentDateTime->diffInHours($appointmentDateTime, false);

                Log::info("Time validation using time_slot:", [
                    'time_slot' => $timeSlot,
                    'start_time_extracted' => $startTimeString,
                    'appointment_datetime' => $appointmentDateTime,
                    'hours_difference' => $hoursDifference
                ]);

                if ($hoursDifference < 12) {
                    return redirect()->back()->with('error', 
                        'Appointments can only be cancelled at least 12 hours before the scheduled time. ' .
                        'This appointment is in ' . round($hoursDifference, 1) . ' hours.'
                    );
                }

                // Release the schedule slot
                if ($appointment->schedule) {
                    $appointment->schedule->update(['is_available' => true]);
                }

                // Update appointment status
                $appointment->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => Auth::id(),
                ]);

                Log::info('Appointment successfully cancelled by admin');

                return redirect()
                    ->route('admin.appointments.index')
                    ->with('success', 'Appointment cancelled successfully and time slot released.');
            });

        } catch (\Exception $e) {
            Log::error('Admin cancel failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cancel appointment.');
        }
    }
    public function getBookedSlots(Request $request)
{
    $validated = $request->validate([
        'date' => 'required|date'
    ]);

    $bookedSlots = Appointment::where('appointment_date', $validated['date'])
        ->whereNotIn('status', ['cancelled'])
        ->pluck('schedule_id')
        ->toArray();

    return response()->json([
        'booked_slots' => $bookedSlots
    ]);
}
}