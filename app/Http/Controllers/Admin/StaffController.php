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
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'contact_no' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],], ['contact_no.regex' => 'Please enter a valid mobile number (e.g., 09123456789 or +639123456789).',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            User::create([
                'username' => $request->username,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'contact_no' => $request->contact_no,
                'password' => Hash::make($request->password),
                'role' => 'staff',
            ]);

            return redirect()->route('admin.staff.index')
                ->with('success', 'Staff member created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create staff member: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Toggle activation status
     */
    public function toggleStatus($id)
    {
        $staff = User::where('user_id', $id)
            ->where('role', 'staff')
            ->firstOrFail();

        $key = 'deactivated_staff_' . $staff->user_id;
        $isDeactivated = Cache::get($key, false);

        if ($isDeactivated) {
            Cache::forget($key);
            $status = 'activated';
        } else {
            Cache::put($key, true, now()->addYears(10)); // keep deactivated flag "forever"
            $status = 'deactivated';
        }

        return redirect()->back()->with('success', "Staff member {$status} successfully!");
    }

    public function getStats()
    {
        $staff = User::where('role', 'staff')->get();
        $totalStaff = $staff->count();
        $active = $staff->filter(fn ($u) => !Cache::get('deactivated_staff_' . $u->user_id, false))->count();
        $inactive = $totalStaff - $active;

        return response()->json([
            'total_staff' => $totalStaff,
            'active_staff' => $active,
            'inactive_staff' => $inactive,
        ]);
    }
}
