<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentReminder;
use App\Models\User;
use App\Models\Payment; 
use Illuminate\Support\Facades\Cache;

class AppointmentController extends Controller
{
    /**
     * View an appointment (Kept index for backward compatibility/route resource convention)
     */
    public function index()
    {
        $userId = Auth::id();

        // Eager load 'payment' relationship to check payment status
        $appointments = Appointment::with(['service', 'schedule', 'payment'])
            ->where('patient_id', $userId)
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(function ($appointment) {
                // Get payment status from the related Payment model
                $paymentStatus = optional(optional($appointment->payment)->payment_status)->ucfirst() ?? 
                                 (in_array($appointment->status, ['Cancelled', 'Completed']) ? 'N/A' : 'Pending Payment');

                return [
                    'id' => $appointment->appointment_id,
                    'date_raw' => optional($appointment->appointment_date)->format('Y-m-d') ?? 'N/A',
                    'schedule_id' => $appointment->schedule_id,
                    'procedure' => $appointment->service->service_name ?? 'N/A',
                    'date' => optional($appointment->appointment_date)->format('m-d-Y') ?? 'N/A',
                    'time' => $appointment->schedule 
                        ? date('g:i a', strtotime($appointment->schedule->start_time)) . 
                          ' - ' . date('g:i a', strtotime($appointment->schedule->end_time))
                        : 'N/A',
                    'status' => ucfirst($appointment->status),
                    'payment_status' => $paymentStatus,
                ];
            });

        return Inertia::render('Customer/ViewAppointment', [
            'appointments' => $appointments,
        ]);
    }

    /**
     * Show appointment scheduling form
     */
    public function create()
    {
        $user = Auth::user();

        // Calculate minimum date (tomorrow)
        $minDate = Carbon::tomorrow()->format('Y-m-d');
        // Calculate maximum date (e.g., 3 months from now)
        $maxDate = Carbon::now()->addMonths(3)->format('Y-m-d');

        return Inertia::render('Customer/ScheduleAppointment', [
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact_no' => $user->contact_no,
            ],
            'services' => Service::all(),
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'today' => Carbon::today()->format('Y-m-d'),
            'tomorrow' => Carbon::tomorrow()->format('Y-m-d'),
        ]);
    }

   /**
    * Store the appointment - REMOVED HOLDING TIME AND ALLOW MULTIPLE APPOINTMENTS
    */
public function store(Request $request)
{
    Log::info('=== APPOINTMENT STORE STARTED ===', $request->all());

    $validated = $request->validate([
        'service_id' => 'required|exists:services,service_id',
        'schedule_id' => 'required|exists:schedules,schedule_id',
        'appointment_date' => 'required|date|after:today',
    ]);

    return DB::transaction(function () use ($validated) {
        // Check if appointment date is at least 1 day in advance
        $appointmentDate = Carbon::parse($validated['appointment_date']);
        $today = Carbon::today();
        
        if ($appointmentDate->lte($today)) {
            return back()->withErrors([
                'error' => 'Appointments must be scheduled at least 1 day in advance. Please choose a future date.'
            ]);
        }

        // CRITICAL: Check if the time slot is already booked by ANY user
        $isAlreadyBooked = Appointment::where('schedule_id', $validated['schedule_id'])
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->where('status', 'Scheduled')
            ->exists();

        if ($isAlreadyBooked) {
            return back()->withErrors([
                'error' => 'This time slot is already booked. Please choose another time.'
            ]);
        }

        // Get the schedule to create proper datetime
        $schedule = Schedule::find($validated['schedule_id']);
        
        // Use start_time for schedule_datetime field
        $scheduleDateTime = Carbon::parse($validated['appointment_date'])->setTimeFromTimeString($schedule->start_time);
        
        // Get service for price
        $service = Service::find($validated['service_id']);
        $amount = $service->price ?? 300.00;

        // Create the appointment
        $appointment = Appointment::create([
            'patient_id' => Auth::id(),
            'service_id' => $validated['service_id'],
            'schedule_id' => $validated['schedule_id'],
            'appointment_date' => $validated['appointment_date'],
            'schedule_datetime' => $scheduleDateTime,
            'status' => 'Scheduled',
        ]);

        // Create payment record immediately
        $payment = Payment::create([
            'appointment_id' => $appointment->appointment_id,
            'amount' => $amount,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        Log::info('✅ Appointment created successfully', [
            'appointment_id' => $appointment->appointment_id,
            'user_id' => Auth::id(),
            'status' => 'Scheduled'
        ]);

        return redirect()
            ->route('customer.view')
            ->with('success', 'Appointment booked successfully!');
    });
}

    /**
     * Show payment page (SIMPLIFIED - No pending session checks)
     */
    public function showPaymentPage()
    {
        // Get the latest appointment for the user that might need payment
        $appointment = Appointment::with(['service', 'schedule', 'payment'])
            ->where('patient_id', Auth::id())
            ->where('status', 'Scheduled')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$appointment) {
            return redirect()->route('customer.appointment.index')->with('error', 'No scheduled appointment found.');
        }

        $service = $appointment->service;
        $schedule = $appointment->schedule;
        $amount = $service->price ?? 300.00;

        return Inertia::render('Customer/PaymentPage', [ 
            'appointment_data' => [
                'appointment_id' => $appointment->appointment_id,
                'service_name' => $service->service_name,
                'appointment_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                'time_slot' => $schedule->start_time . ' - ' . $schedule->end_time,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                'amount' => $amount,
            ]
        ]);
    }

    /**
     * View user's appointments (Dedicated view method for the main list page)
     */
    public function view()
    {
        $user = Auth::user();
        
        // Eager load 'payment' relationship to check payment status
        $appointments = Appointment::with(['service', 'schedule', 'payment'])
            ->where('patient_id', $user->user_id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('schedule_datetime', 'desc')
            ->get()
            ->map(function ($appointment) {
                $serviceName = optional($appointment->service)->service_name ?? 'Service Deleted'; 
                $timeSlot = optional($appointment->schedule)->start_time && optional($appointment->schedule)->end_time ? 
                    Carbon::parse($appointment->schedule->start_time)->format('g:i A') . ' - ' . 
                    Carbon::parse($appointment->schedule->end_time)->format('g:i A') : 
                    'N/A';

                // Get payment status from the related Payment model
                $paymentStatus = optional(optional($appointment->payment)->payment_status)->ucfirst() ?? 'Pending Payment';

                return [
                    'appointment_id' => $appointment->appointment_id,
                    'service_name' => $serviceName,
                    'appointment_date' => $appointment->appointment_date,
                    'schedule_datetime' => $appointment->schedule_datetime,
                    'status' => $appointment->status,
                    'formatted_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                    'formatted_time' => $timeSlot,
                    // Check logic for reschedule/cancel based on status
                    'can_cancel' => $appointment->status === 'Scheduled',
                    'can_reschedule' => $appointment->status === 'Scheduled', 
                    'is_scheduled' => $appointment->status === 'Scheduled',
                    'is_completed' => $appointment->status === 'Completed',
                    'is_cancelled' => $appointment->status === 'Cancelled',
                    'is_rescheduled' => $appointment->status === 'Rescheduled',
                    'payment_status' => $paymentStatus,
                ];
            });

        return Inertia::render('Customer/ViewAppointments', [
            'appointments' => $appointments,
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Cancel an appointment 
     */
    public function cancel(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            $appointment = Appointment::with('schedule')
                ->where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            // Only allow cancellation of 'Scheduled' appointments
            if ($appointment->status !== 'Scheduled') {
                return back()->with('error', 'Only scheduled appointments can be cancelled.');
            }
            
            // Update appointment status to Cancelled
            $appointment->update([
                'status' => 'Cancelled',
            ]);

            Log::info('Appointment cancelled', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment cancelled successfully.');
        });
    }

    /**
     * Reschedule an appointment 
     */
    public function reschedule(Request $request, $id)
    {
        $validated = $request->validate([
            'new_schedule_id' => 'required|exists:schedules,schedule_id',
            'new_appointment_date' => 'required|date|after:today',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            // Check if new appointment date is at least 1 day in advance
            $newAppointmentDate = Carbon::parse($validated['new_appointment_date']);
            $today = Carbon::today();
            
            if ($newAppointmentDate->lte($today)) {
                return back()->with('error', 'Appointments must be scheduled at least 1 day in advance. Please choose a future date.');
            }

            $appointment = Appointment::with('schedule')
                ->where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            if ($appointment->status !== 'Scheduled') {
                return back()->with('error', 'Only scheduled appointments can be rescheduled.');
            }

            // Check if the NEW slot is already booked by someone else
            $isAlreadyBooked = Appointment::where('schedule_id', $validated['new_schedule_id'])
                ->whereDate('appointment_date', $validated['new_appointment_date'])
                ->where('status', 'Scheduled')
                ->where('appointment_id', '!=', $id)
                ->exists();

            if ($isAlreadyBooked) {
                return back()->with('error', 'The selected time slot is already booked. Please choose another time.');
            }

            $newSchedule = Schedule::find($validated['new_schedule_id']);

            $newScheduleDateTime = Carbon::parse(
                $validated['new_appointment_date'] . ' ' . $newSchedule->start_time
            );
            
            // Update appointment record with Rescheduled status
            $appointment->update([
                'schedule_id'       => $validated['new_schedule_id'],
                'appointment_date'  => $validated['new_appointment_date'],
                'schedule_datetime' => $newScheduleDateTime,
                'status'            => 'Rescheduled',
            ]);

            Log::info('Appointment rescheduled successfully', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'new_status' => 'Rescheduled'
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment rescheduled successfully.');
        });
    }

    /**
     * Get available time slots for a specific date
     */
    public function getAvailableSlots(Request $request)
    {
        $userId = Auth::id();
        $request->validate([
            'date' => 'required|date|after:today',
        ]);

        $date = Carbon::parse($request->query('date'))->toDateString();

        // Check if date is at least 1 day in advance
        $today = Carbon::today();
        $selectedDate = Carbon::parse($date);
        
        if ($selectedDate->lte($today)) {
            return response()->json([
                'success' => false,
                'message' => 'Appointments must be scheduled at least 1 day in advance.',
                'available_slots' => []
            ], 422);
        }

        $cacheKey = "available_slots:{$date}";
        $cachedSlots = Cache::get($cacheKey);
        
        if ($cachedSlots) {
            return response()->json([
                'success' => true,
                'available_slots' => $cachedSlots,
                'date' => $date,
                'cached' => true
            ]);
        }
        
        // Only check Scheduled appointments (no pending/holding time)
        $availableSlots = Schedule::leftJoin('appointments', function($join) use ($date) {
                $join->on('schedules.schedule_id', '=', 'appointments.schedule_id')
                    ->whereDate('appointments.appointment_date', $date)
                    ->where('appointments.status', 'Scheduled');
            })
            ->select(
                'schedules.schedule_id',
                'schedules.start_time',
                'schedules.end_time',
                DB::raw('appointments.schedule_id IS NOT NULL as is_booked')
            )
            ->get()
            ->map(function ($schedule) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') .
                        ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                    'is_booked' => (bool) $schedule->is_booked,
                ];
            })
            ->values();

        Cache::put($cacheKey, $availableSlots, 300); // 5 minutes

        return response()->json([
            'success' => true,
            'available_slots' => $availableSlots,
            'date' => $date,
            'cached' => false
        ]);
    }

    /**
     * Check slot availability
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,schedule_id',
            'date' => 'required|date|after:today',
        ]);

        // Check if date is at least 1 day in advance
        $today = Carbon::today();
        $selectedDate = Carbon::parse($request->date);
        
        if ($selectedDate->lte($today)) {
            return response()->json([
                'available' => false,
                'message' => 'Appointments must be scheduled at least 1 day in advance.',
                'schedule' => null
            ], 422);
        }

        // Only check Scheduled appointments (no pending/holding time)
        $isAvailable = !Appointment::where('schedule_id', $request->schedule_id)
            ->whereDate('appointment_date', $request->date)
            ->where('status', 'Scheduled')
            ->exists();

        $schedule = Schedule::find($request->schedule_id);

        return response()->json([
            'available' => $isAvailable,
            'message' => $isAvailable ? 'Time slot is available' : 'Time slot is not available',
            'schedule' => $schedule ? [
                'schedule_id' => $schedule->schedule_id,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A')
            ] : null
        ]);
    }

    /**
     * Get available dates
     */
    public function getAvailableDates(Request $request)
    {
        $startDate = Carbon::tomorrow(); 
        $endDate = Carbon::now()->addMonths(3);
        
        // CACHE AVAILABLE DATES
        $cacheKey = 'available_dates_range';
        $cachedDates = Cache::get($cacheKey);
        
        if ($cachedDates) {
            return response()->json([
                'success' => true,
                'available_dates' => $cachedDates,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'cached' => true
            ]);
        }

        $availableDates = [];
        $totalSlots = Schedule::count();

        // BATCH QUERY - Get all booked counts in single query (only Scheduled appointments)
        $bookedCounts = Appointment::whereDate('appointment_date', '>=', $startDate)
            ->whereDate('appointment_date', '<=', $endDate)
            ->where('status', 'Scheduled')
            ->groupBy('appointment_date')
            ->selectRaw('appointment_date, COUNT(*) as booked_count')
            ->pluck('booked_count', 'appointment_date');

        $period = $startDate->toPeriod($endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $bookedCount = $bookedCounts[$dateStr] ?? 0;
            $availableCount = $totalSlots - $bookedCount;

            if ($availableCount > 0) {
                $availableDates[] = [
                    'date' => $dateStr,
                    'available_slots' => $availableCount,
                    'day_name' => $date->englishDayOfWeek,
                    'is_available' => true
                ];
            }
        }
        
        Cache::put($cacheKey, $availableDates, 900); // 15 minutes

        return response()->json([
            'success' => true,
            'available_dates' => $availableDates,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'cached' => false
        ]);
    }

    /**
     * Send individual appointment reminder email
     */
    private function sendAppointmentReminder($appointment)
    {
        try {
            // Eager load if not already loaded
            $user = $appointment->user ?? User::find($appointment->patient_id);
            $service = $appointment->service ?? Service::find($appointment->service_id);
            $schedule = $appointment->schedule ?? Schedule::find($appointment->schedule_id);

            if ($user && $service && $schedule) {
                Mail::to($user->email)->send(new AppointmentReminder(
                    $user,
                    $appointment,
                    $service,
                    $schedule
                ));
                
                Log::info('Appointment reminder email sent', [
                    'appointment_id' => $appointment->appointment_id,
                    'user_email' => $user->email,
                    'appointment_date' => $appointment->appointment_date
                ]);
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send appointment reminder email', [
                'appointment_id' => $appointment->appointment_id,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }

    /**
     * Command method to send daily reminders (Scheduled Task)
     */
    public function sendDailyReminders()
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        
        // Get all Scheduled appointments for TOMORROW
        $appointments = Appointment::with(['user', 'service', 'schedule'])
            ->whereDate('appointment_date', $tomorrow)
            ->where('status', 'Scheduled')
            ->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($appointments as $appointment) {
            $success = $this->sendAppointmentReminder($appointment);
            
            if ($success) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        Log::info("Daily appointment reminders completed", [
            'date' => $tomorrow,
            'reminders_sent' => $sentCount,
            'reminders_failed' => $failedCount,
            'total_appointments_checked' => $appointments->count()
        ]);

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $appointments->count()
        ];
    }

    /**
     * Mark appointment as completed (for admin use or automatic completion after service)
     */
    public function complete($id)
    {
        return DB::transaction(function () use ($id) {
            $appointment = Appointment::where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            // Only allow completion of Scheduled or Rescheduled appointments
            if (!in_array($appointment->status, ['Scheduled', 'Rescheduled'])) {
                return back()->with('error', 'Only scheduled or rescheduled appointments can be marked as completed.');
            }
            
            // Update appointment status to Completed
            $appointment->update([
                'status' => 'Completed',
            ]);

            Log::info('Appointment marked as completed', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment marked as completed successfully.');
        });
    }
}