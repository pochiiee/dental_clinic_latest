<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;

class PatientController extends Controller
{
    public function index()
    {
        // fetch all users where role = 'patient'
        $patients = User::where('role', 'user')
            ->select('user_id', 'first_name', 'last_name', 'email', 'contact_no')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->user_id,
                    'firstName' => $p->first_name,
                    'lastName' => $p->last_name,
                    'email' => $p->email,
                    'contactNumber' => $p->contact_no,
                ];
            });

        return Inertia::render('Admin/Patients', [
            'patients' => $patients
        ]);
    }
}
