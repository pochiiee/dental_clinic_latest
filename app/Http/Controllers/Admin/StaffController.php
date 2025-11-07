<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StaffController extends Controller
{
    public function index()
    {
        $staff = User::where('role', 'staff')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($user) {
                $isDeactivated = Cache::get('deactivated_staff_' . $user->user_id, false);
                return [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'contact_no' => $user->contact_no,
                    'role' => $user->role,
                    'status' => $isDeactivated ? 'inactive' : 'active',
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Admin/ManageStaffAccount', [
            'staff' => $staff,
        ]);
    }

    public function store(Request $request)
    {
        // Log incoming request for debugging
        Log::info('Staff creation request received:', $request->all());

        // Validate request
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'contact_no' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'username.required' => 'Username is required.',
            'username.unique' => 'This username is already taken.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'contact_no.required' => 'Contact number is required.',
            'contact_no.regex' => 'Please enter a valid mobile number (e.g., 09123456789 or +639123456789).',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        try {
            $user = User::create([
                'username' => $request->username,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'contact_no' => $request->contact_no,
                'password' => Hash::make($request->password),
                'role' => 'staff',
            ]);

            Log::info('Staff created successfully:', ['user_id' => $user->user_id]);

            return redirect()->route('admin.staff.index')
                ->with('success', 'Staff member created successfully!');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error creating staff:', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Database error: ' . $e->getMessage()])
                ->withInput($request->except('password', 'password_confirmation'));
        } catch (\Exception $e) {
            Log::error('Error creating staff:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create staff member: ' . $e->getMessage()])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }

    /**
     * Toggle activation status
     */
    public function toggleStatus($id)
    {
        try {
            $staff = User::where('user_id', $id)
                ->where('role', 'staff')
                ->firstOrFail();

            $key = 'deactivated_staff_' . $staff->user_id;
            $isDeactivated = Cache::get($key, false);

            if ($isDeactivated) {
                Cache::forget($key);
                $status = 'activated';
            } else {
                Cache::put($key, true, now()->addYears(10));
                $status = 'deactivated';
            }

            return redirect()->back()->with('success', "Staff member {$status} successfully!");
        } catch (\Exception $e) {
            Log::error('Error toggling staff status:', ['error' => $e->getMessage()]);
            return redirect()->back()->withErrors(['error' => 'Failed to update staff status.']);
        }
    }

    public function getStats()
    {
        try {
            $staff = User::where('role', 'staff')->get();
            $totalStaff = $staff->count();
            $active = $staff->filter(fn($u) => !Cache::get('deactivated_staff_' . $u->user_id, false))->count();
            $inactive = $totalStaff - $active;

            return response()->json([
                'total_staff' => $totalStaff,
                'active_staff' => $active,
                'inactive_staff' => $inactive,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting staff stats:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch stats'], 500);
        }
    }
}
