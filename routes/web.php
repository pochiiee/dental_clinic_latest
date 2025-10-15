<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing/Welcome', [

    ]);
});
// Landing Page Routes
Route::get('/services', fn() => Inertia::render('Landing/Services'));
Route::get('/faqs', fn() => Inertia::render('Landing/Faqs'));
Route::get('/contactUs', fn() => Inertia::render('Landing/ContactUs'));
Route::get('/testimonials', fn() => Inertia::render('Landing/Testimonials'));

// Customer Routes
Route::get('/home', fn() => Inertia::render('Customer/Home'));
Route::get('/schedule-appointment', fn() => Inertia::render('Customer/ScheduleAppointment'));
Route::get('/view-appointment', fn() => Inertia::render('Customer/ViewAppointment'));
Route::get('/feedback', fn() => Inertia::render('Customer/Feedback'));
Route::get('/invoices', fn() => Inertia::render('Customer/Profile'));

Route::prefix('invoice')->group(function () {
    Route::get('/personal-information', function () {
        return Inertia::render('Profile/PersonalInformation');
    })->name('profile.personal-information');

    Route::get('/change-password', function () {
        return Inertia::render('Profile/ChangePassword');
    })->name('profile.change-password');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
