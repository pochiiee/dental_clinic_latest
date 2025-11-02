<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Feedbacks; 
use App\Models\User;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AdminFeedbackController extends Controller
{
    /**
     * Display a listing of feedbacks.
     */
    public function index(Request $request)
    {
        $filter = $request->input('filter', 'All');
        
        $feedbacks = Feedbacks::with('user')
            ->when($filter === 'Today', function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($filter === 'This Week', function ($query) {
                return $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ]);
            })
            ->when($filter === 'This Month', function ($query) {
                return $query->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ]);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($feedback) {
                return $feedback->created_at->format('F j, Y');
            })
            ->map(function ($feedbacks, $date) {
                return [
                    'date' => $date,
                    'items' => $feedbacks->map(function ($feedback) {
                        return [
                            'id' => $feedback->feedback_id,
                            'name' => $feedback->user 
                                ? $feedback->user->first_name . ' ' . $feedback->user->last_name 
                                : 'Anonymous',
                            'feedback' => $feedback->message,
                            'uploadedImages' => $feedback->image_url ? 1 : 0,
                            'image_url' => $feedback->image_url,
                            'created_at' => $feedback->created_at->format('M j, Y g:i A'),
                        ];
                    })->toArray()
                ];
            })
            ->values()
            ->toArray();

        return Inertia::render('Admin/Feedback', [
            'feedbacks' => $feedbacks,
            'filters' => [
                'current' => $filter
            ]
        ]);
    }

    /**
     * Display the specified feedback.
     */
    public function show($id)
    {
        $feedback = Feedbacks::with('user')->findOrFail($id);

        $feedbackData = [
            'id' => $feedback->feedback_id,
            'name' => $feedback->user 
                ? $feedback->user->first_name . ' ' . $feedback->user->last_name 
                : 'Anonymous',
            'email' => $feedback->user ? $feedback->user->email : 'N/A',
            'feedback' => $feedback->message,
            'image_url' => $feedback->image_url,
            'created_at' => $feedback->created_at->format('F j, Y g:i A'),
            'contact_no' => $feedback->user ? $feedback->user->contact_no : 'N/A',
        ];

        return Inertia::render('Admin/Feedback/Show', [
            'feedback' => $feedbackData,
        ]);
    }

    /**
     * Serve feedback image directly (fixes 403 & path issues)
     */
    public function getImageSimple($id)
    {
        $feedback = Feedbacks::findOrFail($id);

        if (!$feedback->image_url) {
            abort(404, 'No image found for this feedback.');
        }

        // Clean up stored path (remove duplicates and invalid parts)
        $relativePath = str_replace(['storage/', 'public/', '//'], '', $feedback->image_url);

        // Build full path
        $path = storage_path('app/public/' . $relativePath);

        if (!file_exists($path)) {
            abort(404, 'Image file not found at: ' . $path);
        }

        $mimeType = mime_content_type($path);
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'max-age=86400, public',
        ]);
    }

    /**
     * Get feedback statistics for dashboard
     */
    public function getStats()
    {
        $totalFeedbacks = Feedbacks::count();
        $feedbacksWithImages = Feedbacks::whereNotNull('image_url')->count();
        $recentFeedbacks = Feedbacks::where('created_at', '>=', now()->subDays(7))->count();

        return response()->json([
            'total_feedbacks' => $totalFeedbacks,
            'feedbacks_with_images' => $feedbacksWithImages,
            'recent_feedbacks' => $recentFeedbacks,
        ]);
    }

public function getImage($filename)
{
    // Remove any URL encoding
    $filename = urldecode($filename);
    
    // Try multiple possible storage locations
    $possiblePaths = [
        'feedbacks/' . $filename,
        'images/' . $filename,
        'uploads/' . $filename,
        $filename
    ];

    foreach ($possiblePaths as $path) {
        $fullPath = storage_path('app/public/' . $path);
        if (file_exists($fullPath)) {
            return response()->file($fullPath, [
                'Content-Type' => mime_content_type($fullPath),
                'Cache-Control' => 'max-age=86400, public',
            ]);
        }
    }

    // Log for debugging
    Log::error("Feedback image not found: {$filename}");
    abort(404, "Image not found: {$filename}");
}


}
