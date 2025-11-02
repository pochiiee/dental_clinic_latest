<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get stats for the dashboard - count only users with 'user' role
        $totalAppointments = Appointment::count();
        $pendingAppointments = Appointment::where('status', 'pending')->count();
        $completedAppointments = Appointment::where('status', 'completed')->count();
        $totalPatients = User::where('role', 'user')->count();
        
        // Get today's stats for the main dashboard cards
        $today = Carbon::today()->toDateString();
        $totalAppointmentsToday = Appointment::whereDate('appointment_date', $today)->count();
        
        // Get monthly appointment data for charts
        $monthlyAppointments = $this->getMonthlyAppointments();
        $serviceDistribution = $this->getServiceDistribution();
        
        // Get additional user statistics
        $userStats = $this->getUserStatistics();
        
        // Get upcoming appointments
        $upcomingAppointments = $this->getUpcomingAppointments();
        
        // Get appointment status distribution
        $appointmentStatusDistribution = $this->getAppointmentStatusDistribution();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalAppointments' => $totalAppointments,
                'pendingAppointments' => $pendingAppointments,
                'completedAppointments' => $completedAppointments,
                'totalPatients' => $totalPatients,
                'totalAppointmentsToday' => $totalAppointmentsToday,
                'totalUsers' => $userStats['totalUsers'],
                'newUsersThisMonth' => $userStats['newUsersThisMonth'],
                'userGrowth' => $userStats['userGrowth'],
            ],
            'chartData' => [
                'monthlyAppointments' => $monthlyAppointments,
                'serviceDistribution' => $serviceDistribution,
                'userRegistrations' => $userStats['monthlyRegistrations'],
                'appointmentStatus' => $appointmentStatusDistribution,
            ],
            'upcomingAppointments' => $upcomingAppointments
        ]);
    }

    private function getMonthlyAppointments()
    {
        $appointments = Appointment::selectRaw('
            YEAR(created_at) as year, 
            MONTH(created_at) as month, 
            COUNT(*) as count
        ')
        ->where('created_at', '>=', now()->subMonths(6))
        ->groupBy('year', 'month')
        ->orderBy('year', 'asc')
        ->orderBy('month', 'asc')
        ->get();

        $labels = [];
        $data = [];

        foreach ($appointments as $appointment) {
            $date = Carbon::create($appointment->year, $appointment->month);
            $labels[] = $date->format('M Y');
            $data[] = $appointment->count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function getServiceDistribution()
    {
        // Fixed: Using service_name instead of name
        $distribution = Appointment::selectRaw('
            services.service_name as service_name,
            COUNT(appointments.appointment_id) as appointment_count
        ')
        ->join('services', 'appointments.service_id', '=', 'services.service_id')
        ->groupBy('services.service_id', 'services.service_name')
        ->orderBy('appointment_count', 'desc')
        ->limit(5)
        ->get();

        $labels = $distribution->pluck('service_name')->toArray();
        $data = $distribution->pluck('appointment_count')->toArray();

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function getUserStatistics()
    {
        // Total users with 'user' role
        $totalUsers = User::where('role', 'user')->count();
        
        // New users this month (with 'user' role)
        $newUsersThisMonth = User::where('role', 'user')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        // Users from previous month (for growth calculation)
        $previousMonthUsers = User::where('role', 'user')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
            
        // Calculate growth percentage
        $userGrowth = $previousMonthUsers > 0 
            ? round((($newUsersThisMonth - $previousMonthUsers) / $previousMonthUsers) * 100, 2)
            : ($newUsersThisMonth > 0 ? 100 : 0);
            
        // Monthly user registrations for chart (last 6 months)
        $monthlyRegistrations = $this->getMonthlyUserRegistrations();

        return [
            'totalUsers' => $totalUsers,
            'newUsersThisMonth' => $newUsersThisMonth,
            'userGrowth' => $userGrowth,
            'monthlyRegistrations' => $monthlyRegistrations
        ];
    }

    private function getMonthlyUserRegistrations()
    {
        $users = User::where('role', 'user')
            ->selectRaw('
                YEAR(created_at) as year, 
                MONTH(created_at) as month, 
                COUNT(*) as count
            ')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $labels = [];
        $data = [];

        foreach ($users as $user) {
            $date = Carbon::create($user->year, $user->month);
            $labels[] = $date->format('M Y');
            $data[] = $user->count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function getUpcomingAppointments()
    {
        $today = Carbon::today()->toDateString();
        
        return Appointment::with(['patient', 'service'])
            ->where('appointment_date', '>=', $today)
            ->whereIn('status', ['scheduled', 'pending'])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($appointment) {
                return [
                    'appointment_id' => $appointment->appointment_id,
                    'patient_name' => $appointment->patient->name ?? 'Unknown',
                    'service_name' => $appointment->service->service_name ?? 'Unknown',
                    'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                    'status' => $appointment->status,
                ];
            });
    }

    private function getAppointmentStatusDistribution()
    {
        $statuses = Appointment::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $labels = $statuses->pluck('status')->toArray();
        $data = $statuses->pluck('count')->toArray();

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get dashboard statistics for the main cards (like your original design)
     */
    public function getDashboardStats()
    {
        $today = Carbon::today()->toDateString();
        
        // Total Appointments (Today) - like your original "10"
        $totalAppointmentsToday = Appointment::whereDate('appointment_date', $today)->count();
        
        // Patients Registered - like your original "34"
        $totalPatients = User::where('role', 'user')->count();
        
        // Pending Appointments - like your original "25"
        $pendingAppointments = Appointment::where('status', 'pending')->count();
        
        // Total Value - like your original "85" (you can customize this logic)
        $totalValue = Appointment::where('status', 'completed')->count();
        
        // Upcoming Scheduled Appointments
        $upcomingAppointments = $this->getFormattedUpcomingAppointments();
        
        // Appointment Status Percentages
        $statusPercentages = $this->getStatusPercentages();

        return response()->json([
            'totalAppointmentsToday' => $totalAppointmentsToday,
            'totalPatients' => $totalPatients,
            'pendingAppointments' => $pendingAppointments,
            'totalValue' => $totalValue,
            'upcomingAppointments' => $upcomingAppointments,
            'statusPercentages' => $statusPercentages
        ]);
    }

    private function getFormattedUpcomingAppointments()
    {
        $today = Carbon::today()->toDateString();
        
        return Appointment::with(['patient', 'service'])
            ->where('appointment_date', '>=', $today)
            ->whereIn('status', ['scheduled', 'pending'])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit(3)
            ->get()
            ->map(function ($appointment) {
                return [
                    'name' => $appointment->patient->name ?? 'Unknown',
                    'procedure_type' => $appointment->service->service_name ?? 'Unknown',
                    'date_time' => $appointment->appointment_date->format('m-d-Y') . ' ' . $this->getTimeFromSchedule($appointment),
                ];
            });
    }

    private function getTimeFromSchedule($appointment)
    {
        // If you have schedule data, you can format the time here
        // For now, return a placeholder or get from schedule relationship
        if ($appointment->schedule) {
            return $appointment->schedule->start_time . ' - ' . $appointment->schedule->end_time;
        }
        
        return '10:00 a.m - 1:00 p.m'; // Default placeholder
    }

    private function getStatusPercentages()
    {
        $statuses = Appointment::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $totalAppointments = Appointment::count();
        $percentages = [];

        // Define the statuses you want to show
        $desiredStatuses = ['completed', 'scheduled', 'rescheduled', 'cancelled', 'no_show'];

        foreach ($desiredStatuses as $status) {
            $statusData = $statuses->where('status', $status)->first();
            $count = $statusData ? $statusData->count : 0;
            $percentage = $totalAppointments > 0 ? round(($count / $totalAppointments) * 100) : 0;
            $percentages[$status] = $percentage;
        }

        return $percentages;
    }
}