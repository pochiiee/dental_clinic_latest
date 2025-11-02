<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;  

class ScheduleController extends Controller
{
    /**
     * Get all schedules
     */
    public function index(): JsonResponse
    {
        try {
            $schedules = Schedule::all();
            
            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedules'
            ], 500);
        }
    }

/**
 * Get available slots for a specific date 
 */
public function getAvailableSlots(string $date): JsonResponse
{
    try {
        try {
            $parsedDate = Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format'
            ], 400);
        }

        $cacheKey = "available_slots_{$parsedDate}";

        $data = Cache::remember($cacheKey, 60, function () use ($parsedDate) {

            $availableSlots = Schedule::select('schedule_id', 'start_time', 'end_time')
                ->whereNotIn('schedule_id', function ($query) use ($parsedDate) {
                    $query->select('schedule_id')
                        ->from('appointments')
                        ->whereDate('appointment_date', $parsedDate)
                        ->whereIn('status', ['confirmed', 'pending']);
                })
                ->orderBy('start_time')
                ->get()
                ->map(function ($slot) {
                    return [
                        'schedule_id' => $slot->schedule_id,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'time_slot' => $this->getTimeSlotLabel($slot),
                        'display_time' => $this->formatTimeForDisplay($slot->start_time, $slot->end_time),
                    ];
                })
                ->values();

            return [
                'success' => true,
                'available_slots' => $availableSlots,
                'date' => $parsedDate,
                'total_available' => $availableSlots->count(),
            ];
        });

        return response()->json($data);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch available slots'
        ], 500);
    }
}


/**
 * Get available dates within a range
 */
public function getAvailableDates(Request $request): JsonResponse
{
    try {
        $startDate = $request->get('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::today()->addDays(30)->format('Y-m-d'));

        $cacheKey = "available_dates_{$startDate}_{$endDate}";

        $data = Cache::remember($cacheKey, 120, function () use ($startDate, $endDate) {
            $period = CarbonPeriod::create($startDate, $endDate);
            $availableDates = [];

            $totalSlots = Schedule::count();

            $bookedByDate = Appointment::selectRaw('DATE(appointment_date) as date, COUNT(*) as count')
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->whereIn('status', ['confirmed', 'pending'])
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray();

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $bookedCount = $bookedByDate[$dateStr] ?? 0;

                if ($bookedCount < $totalSlots) {
                    $availableDates[] = [
                        'date' => $dateStr,
                        'available_slots' => $totalSlots - $bookedCount,
                        'day_name' => $date->englishDayOfWeek
                    ];
                }
            }

            return [
                'success' => true,
                'available_dates' => $availableDates,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];
        });

        return response()->json($data);

    } catch (\Exception $e) {
        Log::error('Get available dates error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch available dates'
        ], 500);
    }
}

/**
 * Check availability for a specific schedule on a date
 */
public function checkAvailability($scheduleId, Request $request): JsonResponse
{
    try {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));

        $cacheKey = "check_availability_{$scheduleId}_{$date}";

        $data = Cache::remember($cacheKey, 60, function () use ($scheduleId, $date) {
            $isBooked = Appointment::where('schedule_id', $scheduleId)
                ->whereDate('appointment_date', $date)
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();

            $schedule = Schedule::select('schedule_id', 'start_time', 'end_time')->find($scheduleId);

            return [
                'success' => true,
                'available' => !$isBooked,
                'schedule' => $schedule ? [
                    'schedule_id' => $schedule->schedule_id,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'display_time' => $this->formatTimeForDisplay($schedule->start_time, $schedule->end_time)
                ] : null,
                'date' => $date
            ];
        });

        return response()->json($data);

    } catch (\Exception $e) {
        Log::error('Check availability error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to check availability'
        ], 500);
    }
}

/**
 * Bulk check availability for multiple dates
 */
public function bulkCheckAvailability(Request $request): JsonResponse
{
    try {
        $dates = $request->get('dates', []);
        if (empty($dates)) {
            return response()->json([
                'success' => false,
                'message' => 'No dates provided'
            ], 400);
        }

        $cacheKey = 'bulk_availability_' . md5(json_encode($dates));

        $results = Cache::remember($cacheKey, 60, function () use ($dates) {
            $allSlots = Schedule::select('schedule_id')->get();
            $totalSlots = $allSlots->count();

            // ✅ Fetch all booked slots in one query
            $booked = Appointment::whereIn('status', ['confirmed'])
                ->whereIn(DB::raw('DATE(appointment_date)'), $dates)
                ->selectRaw('DATE(appointment_date) as date, COUNT(schedule_id) as count')
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $results = [];
            foreach ($dates as $date) {
                $bookedCount = $booked[$date] ?? 0;
                $availableCount = max($totalSlots - $bookedCount, 0);

                $results[$date] = [
                    'available' => $availableCount > 0,
                    'available_slots' => $availableCount,
                    'total_slots' => $totalSlots
                ];
            }

            return $results;
        });

        return response()->json([
            'success' => true,
            'results' => $results
        ]);

    } catch (\Exception $e) {
        Log::error('Bulk check availability error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to perform bulk availability check'
        ], 500);
    }
}

/**
 * Get schedules by date range
 */
public function getByDateRange(Request $request): JsonResponse
{
    try {
        $startDate = $request->get('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::today()->addDays(7)->format('Y-m-d'));

        $cacheKey = "schedule_by_range_{$startDate}_{$endDate}";

        $data = Cache::remember($cacheKey, 120, function () use ($startDate, $endDate) {
            $period = CarbonPeriod::create($startDate, $endDate);
            $scheduleData = [];

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $availableSlots = $this->getAvailableSlotsForDate($dateStr);

                $scheduleData[] = [
                    'date' => $dateStr,
                    'day_name' => $date->englishDayOfWeek,
                    'available_slots' => $availableSlots,
                    'has_availability' => count($availableSlots) > 0
                ];
            }

            return [
                'success' => true,
                'data' => $scheduleData,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];
        });

        return response()->json($data);

    } catch (\Exception $e) {
        Log::error('Get by date range error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch schedule data for date range'
        ], 500);
    }
}


    /**
     * Get today's available slots
     */
    public function getTodaySlots(): JsonResponse
    {
        return $this->getAvailableSlots(Carbon::today()->format('Y-m-d'));
    }

    /**
     * Get tomorrow's available slots
     */
    public function getTomorrowSlots(): JsonResponse
    {
        return $this->getAvailableSlots(Carbon::tomorrow()->format('Y-m-d'));
    }

    /**
     * Get schedules for a specific date
     */
    public function getByDate($date): JsonResponse
    {
        return $this->getAvailableSlots($date);
    }

    /**
     * Helper: Get time slot label
     */
    private function getTimeSlotLabel($slot): string
    {
        $start = Carbon::parse($slot->start_time);
        $end = Carbon::parse($slot->end_time);
        
        return $start->format('g:i A') . ' - ' . $end->format('g:i A');
    }

    /**
     * Helper: Format time for display
     */
    private function formatTimeForDisplay($startTime, $endTime): string
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        
        return $start->format('g:i A') . ' - ' . $end->format('g:i A');
    }

    /**
     * Helper: Get available slots for a date
     */
    private function getAvailableSlotsForDate(string $date): array
    {
        $bookedSlotIds = Appointment::whereDate('appointment_date', $date)
            ->whereIn('status', ['confirmed'])
            ->pluck('schedule_id')
            ->toArray();

        return Schedule::whereNotIn('schedule_id', $bookedSlotIds)
            ->get()
            ->map(function ($slot) {
                return [
                    'schedule_id' => $slot->schedule_id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'display_time' => $this->formatTimeForDisplay($slot->start_time, $slot->end_time)
                ];
            })
            ->toArray();
    }
}