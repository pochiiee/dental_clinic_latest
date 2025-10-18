<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:' . User::class],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'contact_no' => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'This email is already registered.',
            'contact_no.unique' => 'This contact number is already registered.',
            'contact_no.size' => 'Contact number must be exactly 11 digits.',
            'contact_no.regex' => 'Contact number must contain digits only.',
        ]);

        $user = User::create([
            'username' => $request->username,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        event(new Registered($user));

        return redirect()->route('login')->with('success', 'Account created successfully!');
        
        return back()->with('error', 'Account already exists.');
    }

}
