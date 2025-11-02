<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Feedbacks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    /**
     * Display the Feedback page (Inertia render)
     */
    public function index()
    {
        try {
            $feedbacks = Feedbacks::with('user')->latest()->get();

            return Inertia::render('Customer/Feedback', [
                'feedbacks' => $feedbacks,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load feedback page', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Unable to load feedback page.']);
        }
    }

    /**
     * Store feedback with optional image upload
     */
   public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'name'    => 'nullable|string|max:255',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            // Store image in storage/app/public/feedbacks
            $storedPath = $request->file('image')->store('feedbacks', 'public');

            // ✅ Clean the path (remove any "public/" prefix just in case)
            $imagePath = str_replace('public/', '', $storedPath);
        }

        $feedback = Feedbacks::create([
            'user_id'   => Auth::id(),
            'message'   => $validated['message'],
            'image_url' => $imagePath, // ✅ stored as "feedbacks/filename.jpg"
        ]);

        if (!empty($validated['name'])) {
            Log::info('Feedback name provided', ['name' => $validated['name']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully!',
            'data'    => $feedback,
        ]);
    } catch (\Exception $e) {
        Log::error('Feedback store failed', ['error' => $e->getMessage()]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to submit feedback. Please try again later.',
        ], 500);
    }
}

}
