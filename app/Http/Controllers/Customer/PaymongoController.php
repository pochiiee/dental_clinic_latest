<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use App\Models\User;

class PaymongoController extends Controller
{

    public function getAvailablePaymentMethods()
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            $response = Http::withBasicAuth($paymongo_SecretKey, '')->get('https://api.paymongo.com/v1/merchants/capabilities/payment_methods');

            $data = $response->json();

            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods', ['error' => $e->getMessage()]);
            return [];
        }
    }
    public function createPayment(Request $request)
    {
        Log::info('Payment creation started', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        // Fix: Convert amount properly to centavos
        // NOTE: Ideally, the amount should come from the Service model, not hardcoded.
        $amountInPesos = 300.00;
        $amount = intval(round($amountInPesos * 100)); // 30000 centavos

        $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');

        try {
            $validated = $request->validate([
                'service_id' => 'required|exists:services,service_id',
                'appointment_date' => 'required|date',
                'service_name' => 'required|string',
                'schedule_id' => 'required|exists:schedules,schedule_id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Invalid request data'], 422);
        }

        DB::beginTransaction();

        try {
            // Check for existing appointment in session first
            $pendingAppointmentData = session('pending_appointment');
            $appointmentId = $pendingAppointmentData['appointment_id'] ?? session('pending_appointment_id'); // Check both old and new session keys

            $appointment = null;
            if ($appointmentId) {
                // Use the appointment already created by AppointmentController::store
                $appointment = Appointment::where('appointment_id', $appointmentId)
                    ->where('patient_id', Auth::id())
                    ->where('status', 'pending')
                    ->first();
            }

            if (!$appointment) {
                // Fallback: check if user has any pending appointment
                $appointment = Appointment::where('patient_id', Auth::id())
                    ->where('status', 'pending')
                    ->first();
            }

            // If no pending appointment found, create one (backward compatibility / safety)
            if (!$appointment) {
                Log::warning('No pending appointment found, creating new one');

                // Check if user has existing scheduled appointment
                // $existingScheduledAppointment = Appointment::where('patient_id', Auth::id())
                //     ->whereIn('status', ['scheduled'])
                //     ->first();

                // if ($existingScheduledAppointment) {
                //     DB::rollBack();
                //     return response()->json(['error' => 'You already have a scheduled appointment.'], 422);
                // }

                // Check if the slot is already booked
                $isBooked = Appointment::where('schedule_id', $validated['schedule_id'])
                    ->whereDate('appointment_date', $validated['appointment_date'])
                    ->whereIn('status', ['scheduled'])
                    ->exists();

                if ($isBooked) {
                    DB::rollBack();
                    return response()->json(['error' => 'This time slot was just booked by another user. Please choose another time.'], 422);
                }

                $schedule = Schedule::find($validated['schedule_id']);
                $scheduleDateTime = Carbon::parse($validated['appointment_date'])->setTimeFromTimeString($schedule->start_time);

                // Create appointment
                $appointment = Appointment::create([
                    'patient_id' => Auth::id(),
                    'service_id' => $validated['service_id'],
                    'schedule_id' => $validated['schedule_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'schedule_datetime' => $scheduleDateTime, // Added schedule_datetime
                    'status' => 'pending',
                ]);

                if (!$appointment->appointment_id) {
                    throw new \Exception('Failed to create appointment');
                }
            } else {

                // Verify the existing appointment data matches the request
                $schedule = Schedule::find($validated['schedule_id']);
                $scheduleDateTime = Carbon::parse($validated['appointment_date'])->setTimeFromTimeString($schedule->start_time);

                if (
                    $appointment->service_id != $validated['service_id'] ||
                    $appointment->schedule_id != $validated['schedule_id'] ||
                    $appointment->appointment_date != $validated['appointment_date']
                ) {

                    Log::warning('Appointment data mismatch, updating existing pending appointment');

                    // Update appointment with new data

                    $appointment->update([
                        'service_id' => $validated['service_id'],
                        'schedule_id' => $validated['schedule_id'],
                        'appointment_date' => $validated['appointment_date'],
                        'schedule_datetime' => $scheduleDateTime, // Added schedule_datetime
                    ]);
                }
            }

            // Create PayMongo checkout session
            $paymentMethods = ['gcash', 'grab_pay', 'paymaya', 'card'];

            $successUrl = 'https://districtsmiles.online/payment/success?appointment_id=' . $appointment->appointment_id;
            $cancelUrl = 'https://districtsmiles.online/payment/cancelled?appointment_id=' . $appointment->appointment_id;

            $payload = [
                "data" => [
                    "attributes" => [
                        "line_items" => [[
                            "name" => $validated['service_name'] . ' - District Smile Dental Clinic',
                            "amount" => $amount,
                            "currency" => "PHP",
                            "quantity" => 1,
                            "images" => ['https://districtsmiles.online/images/logo.png'] // Use absolute URL
                        ]],
                        "payment_method_types" => $paymentMethods,
                        "description" => "Service Fee of ₱300 for " . $validated['service_name'],
                        "success_url" => $successUrl,
                        "cancel_url" => $cancelUrl,
                        "metadata" => [
                            'user_id' => Auth::id(),
                            'service_id' => $validated['service_id'],
                            'schedule_id' => $validated['schedule_id'],
                            'appointment_date' => $validated['appointment_date'],
                            'service_name' => $validated['service_name'],
                            'appointment_id' => $appointment->appointment_id,
                        ],
                        "statement_descriptor" => "District Smile Dental"
                    ]
                ]
            ];

            Log::info('PayMongo API Request', ['payload' => Arr::only($payload, ['data.attributes.line_items', 'data.attributes.payment_method_types'])]);

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', $payload);

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorDetail = $errorData['errors'][0]['detail'] ?? 'Unknown API error';
                throw new \Exception('PayMongo API failed: ' . $errorDetail);
            }

            $checkoutData = $response->json();

            if (!isset($checkoutData['data']['attributes']['checkout_url'])) {
                throw new \Exception('PayMongo response missing checkout_url');
            }

            // Save PayMongo session ID to appointment
            $paymongoSessionId = $checkoutData['data']['id'];
            $appointment->paymongo_session_id = $paymongoSessionId;
            $appointment->save();

            // Clear old session and store new appointment ID for consistency
            session()->forget('pending_appointment'); // Clear the old structure if it existed
            session(['pending_appointment_id' => $appointment->appointment_id]);

            Log::info('✅ Payment session created successfully', [
                'appointment_id' => $appointment->appointment_id,
                'paymongo_session_id' => $paymongoSessionId,
                'checkout_url' => $checkoutData['data']['attributes']['checkout_url']
            ]);

            DB::commit();

            return response()->json([
                'checkout_url' => $checkoutData['data']['attributes']['checkout_url'],
                'status' => 'created',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Success Payment 
     */
    public function success(Request $request)
    {
        Log::info('=== PAYMENT SUCCESS CALLBACK START ===', [
            'all_query_params' => $request->query()
        ]);

        $appointmentId = $request->query('appointment_id');

        if (!$appointmentId) {
            Log::error('No appointment ID in success callback');
            return redirect()->route('customer.view')
                ->with('error', 'Invalid payment session or missing appointment ID.');
        }

        // Clear the pending appointment ID from session regardless of next flow
        session()->forget('pending_appointment_id');

        // If user is logged in AND it's their appointment, use authenticated flow
        if (Auth::check()) {
            return $this->handleAuthenticatedSuccess($appointmentId, $request);
        }

        // Public user flow - just show success page without authentication
        return $this->handlePublicSuccess($appointmentId, $request);
    }

    private function handleAuthenticatedSuccess($appointmentId, $request)
    {
        // Get appointment with authentication check, eager load patient for email
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->with(['service', 'schedule', 'patient'])
            ->first();

        if (!$appointment) {
            Log::error('Appointment not found or user mismatch in success handler', [
                'appointment_id' => $appointmentId,
                'authenticated_user_id' => Auth::id()
            ]);
            return redirect()->route('customer.view')
                ->with('error', 'Appointment not found or access denied.');
        }

        $payment = Payment::where('appointment_id', $appointmentId)->first();

        // Scenario 1: Already scheduled (webhook arrived first or was already processed)
        if ($appointment->status === 'scheduled' && $payment) {
            Log::info('Appointment already scheduled, showing success page');

            return Inertia::render('Payment/Success', [
                'appointment' => $appointment,
                'payment' => $payment,
                'message' => 'Payment completed successfully! Your appointment is scheduled.'
            ]);
        }

        // Scenario 2: Still pending - Check if it can be scheduled immediately (API check)
        if ($appointment->status === 'pending') {
            Log::info('Appointment pending, checking payment status via API...');

            // Verify payment status directly with PayMongo API
            $isPaid = $this->verifyPaymentStatus($appointment->paymongo_session_id);

            if ($isPaid) {
                Log::info('Payment verified via API, confirming appointment...');

                // * CORE FIX: Confirm payment and appointment in one transaction *
                $payment = DB::transaction(function () use ($appointment, $request) {
                    $appointment->update(['status' => 'scheduled']);

                    // Detect payment method from the session data pulled via API
                    $paymentMethod = $this->detectPaymentMethod($appointment->paymongo_session_id);

                    // Create Payment record (or update if it exists from a previous/delayed step)
                    $payment = Payment::updateOrCreate(
                        ['appointment_id' => $appointment->appointment_id], // Unique identifier
                        [
                            'amount' => 300.00, // Still using hardcoded amount
                            'payment_method' => $paymentMethod,
                            'payment_status' => 'completed',
                            'transaction_reference' => $appointment->paymongo_session_id,
                            'paid_at' => now(),
                        ]
                    );

                    // Send receipt email immediately
                    try {
                        Mail::to($appointment->patient->email)->send(new PaymentReceiptMail($appointment, $payment));
                        Log::info('Payment receipt email sent via success URL');
                    } catch (\Exception $e) {
                        Log::error('Failed to send email via success URL', ['error' => $e->getMessage()]);
                    }

                    return $payment;
                });

                return Inertia::render('Payment/Success', [
                    'appointment' => $appointment->fresh(['service', 'schedule', 'patient']),
                    'payment' => $payment,
                    'message' => 'Payment completed successfully! Your appointment is scheduled.'
                ]);
            }
        }

        // Scenario 3: Not scheduled and not immediately verifiable (show processing page)
        Log::info('Payment status is not scheduled yet, showing processing page');

        return Inertia::render('Payment/Success', [
            'appointment' => $appointment,
            'message' => 'We are verifying your payment. This may take a few moments, or you can check your email for updates.'
        ]);
    }

    private function handlePublicSuccess($appointmentId, $request)
    {
        Log::info('Public user access - handling success without authentication');

        // Get appointment without authentication check
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->with(['service', 'schedule'])
            ->first();

        if (!$appointment) {
            Log::error('Appointment not found for public access', [
                'appointment_id' => $appointmentId
            ]);
            return Inertia::render('Payment/Success', [
                'message' => 'Appointment not found. Please contact support.'
            ]);
        }

        $payment = Payment::where('appointment_id', $appointmentId)->first();

        if ($appointment->status === 'scheduled' && $payment) {
            return Inertia::render('Payment/Success', [
                'appointment' => $appointment,
                'payment' => $payment,
                'message' => 'Payment completed successfully! Your appointment is scheduled.'
            ]);
        }

        // Show processing page
        return Inertia::render('Payment/Success', [
            'appointment' => $appointment,
            'message' => 'We are verifying your payment. This may take a few moments...'
        ]);
    }

    /**
     * Verify payment status directly with PayMongo API
     * Checks for a 'paid' payment object inside the session.
     */
    private function verifyPaymentStatus($checkoutSessionId)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');

            Log::info('Verifying payment status for session:', ['session_id' => $checkoutSessionId]);

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");

            if ($response->successful()) {
                $sessionData = $response->json();

                // The most reliable check: see if a 'paid' payment object exists.
                $payments = $sessionData['data']['attributes']['payments'] ?? [];
                if (!empty($payments)) {
                    // Check the status of the first payment object found
                    $status = $payments[0]['attributes']['status'] ?? null;
                    Log::info('Payment status from payments array:', ['status' => $status]);
                    return $status === 'paid'; // ONLY return true if 'paid'
                }

                return false;
            } else {
                Log::error('Payment verification API failed:', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Payment status verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return false;
    }

    /**
     * WEBHOOK - REAL PAYMENT CONFIRMATION (Primary)
     */
    public function webhook(Request $request)
    {
        // ... (Webhook verification and initial logging remain the same) ...
        Log::info('=== PAYMONGO WEBHOOK START ===');

        // Log all webhook data for debugging
        Log::info('Webhook Headers:', $request->headers->all());
        Log::info('Webhook Raw Payload:', ['payload' => $request->getContent()]);

        $webhookData = $request->json()->all();
        Log::info('Webhook JSON Data:', $webhookData);

        // Verify webhook signature for security
        if (!$this->verifyWebhookSignature($request)) {
            Log::error('Webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $webhookData['data']['attributes']['type'] ?? null;

        Log::info('Webhook Event Type:', ['type' => $eventType]);

        if ($eventType === 'checkout_session.payment.paid') {
            return $this->handlePaymentPaid($webhookData);
        }

        if ($eventType === 'checkout_session.payment.failed') {
            return $this->handlePaymentFailed($webhookData);
        }

        Log::info('Webhook event ignored', ['type' => $eventType]);
        return response()->json(['status' => 'ignored']);
    }

    /**
     * Handle successful payment via webhook
     */
    private function handlePaymentPaid($webhookData)
    {
        DB::beginTransaction();

        try {
            Log::info('=== PROCESSING PAYMENT PAID WEBHOOK ===');

            $sessionData = $webhookData['data']['attributes']['data'];
            $checkoutSessionId = $sessionData['id'];
            $metadata = $sessionData['attributes']['metadata'] ?? [];

            $appointmentId = $metadata['appointment_id'] ?? null;
            $userId = $metadata['user_id'] ?? null;

            Log::info('Webhook Processing Data:', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'checkout_session_id' => $checkoutSessionId,
            ]);

            if (!$appointmentId) {
                throw new \Exception('No appointment ID in webhook metadata');
            }

            // Get appointment - NO AUTHENTICATION CHECK FOR WEBHOOK
            // Eager load patient model for email sending
            $appointment = Appointment::where('appointment_id', $appointmentId)->with('patient')->first();

            if (!$appointment) {
                throw new \Exception("Appointment {$appointmentId} not found");
            }

            // Check if already processed
            if ($appointment->status === 'scheduled') {
                Log::info('Appointment already scheduled', ['appointment_id' => $appointmentId]);
                DB::commit();
                return response()->json(['status' => 'already_processed']);
            }

            // * CORE FIX: Use updateOrCreate for Payment to handle both new creation and updates from success URL *
            $paymentMethod = $this->detectPaymentMethodFromWebhook($webhookData);

            $payment = Payment::updateOrCreate(
                ['appointment_id' => $appointment->appointment_id], // Unique identifier
                [
                    'amount' => 300.00,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'completed',
                    'transaction_reference' => $checkoutSessionId,
                    'paid_at' => now(),
                ]
            );

            // ✅ CONFIRM APPOINTMENT
            $appointment->update([
                'status' => 'scheduled',
                'paymongo_session_id' => $checkoutSessionId // Ensure session ID is captured here too
            ]);

            Log::info('Appointment updated to scheduled');

            // Send receipt email (only if the payment was successfully processed now)
            try {
                $user = $appointment->patient;
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                    Log::info('Payment receipt email sent via webhook');
                }
            } catch (\Exception $e) {
                Log::error('Failed to send email via webhook', ['error' => $e->getMessage()]);
            }

            DB::commit();

            Log::info('✅ PAYMENT scheduled VIA WEBHOOK SUCCESSFULLY', [
                'appointment_id' => $appointmentId,
                'payment_id' => $payment->payment_id,
                'payment_method' => $paymentMethod
            ]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ WEBHOOK PAYMENT PROCESSING FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'appointment_id' => $appointmentId ?? 'unknown'
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle failed payment via webhook
     */
    private function handlePaymentFailed($webhookData)
    {
        try {
            $sessionData = $webhookData['data']['attributes']['data'];
            $metadata = $sessionData['attributes']['metadata'] ?? [];
            $appointmentId = $metadata['appointment_id'] ?? null;

            if ($appointmentId) {
                $this->cleanupFailedPayment($appointmentId);
                Log::info('Payment failed via webhook', ['appointment_id' => $appointmentId]);
            }

            return response()->json(['status' => 'failed_handled']);
        } catch (\Exception $e) {
            Log::error('Failed to handle payment failure webhook', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to process'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(Request $request)
    {
        // For development/testing, you can temporarily disable signature verification
        if (app()->environment('local', 'testing')) {
            Log::info('Webhook signature verification skipped in local environment');
            return true;
        }

        $payload = $request->getContent();
        $signature = $request->header('paymongo-signature');
        $webhookSecret = env('PAYMONGO_WEBHOOK_SECRET');

        if (!$signature || !$webhookSecret) {
            Log::warning('Missing webhook signature or secret');
            return false;
        }

        // Simple signature verification (Paymongo uses HMAC SHA256)
        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        $isValid = hash_equals($signature, $computedSignature);

        if (!$isValid) {
            Log::error('Webhook signature mismatch', [
                'computed' => $computedSignature,
                'received' => $signature
            ]);
        } else {
            Log::info('Webhook signature verified successfully');
        }

        return $isValid;
    }

    /**
     * Detect payment method from webhook data
     */
    private function detectPaymentMethodFromWebhook($webhookData)
    {
        try {
            $payments = $webhookData['data']['attributes']['data']['attributes']['payments'] ?? [];

            if (!empty($payments)) {
                $paymentMethod = $payments[0]['attributes']['payment_method']['attributes']['type'] ?? null;

                $methodMap = [
                    'gcash' => 'GCash',
                    'grab_pay' => 'GrabPay',
                    'paymaya' => 'Maya',
                    'card' => 'Credit/Debit Card',
                ];

                return $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Online Payment';
            }
        } catch (\Exception $e) {
            Log::warning('Could not detect payment method from webhook');
        }

        return 'Online Payment';
    }

    /**
     * Helper method to cleanup failed payment
     */
    private function cleanupFailedPayment($appointmentId)
    {
        try {
            $appointment = Appointment::find($appointmentId);

            if ($appointment && in_array($appointment->status, ['pending'])) {
                // Mark as cancelled instead of deleting
                $appointment->status = 'cancelled';
                $appointment->save();

                Log::info('Appointment marked as cancelled after failed payment', [
                    'appointment_id' => $appointmentId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to mark appointment as cancelled', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detect payment method from session (used by success URL API check)
     */
    private function detectPaymentMethod($sessionId)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");

            $sessionData = $response->json();

            // Check for payment method that was actually used (from the 'payments' array)
            $paymentMethod = null;
            if (isset($sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'])) {
                $paymentMethod = $sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'];
            }
            // Fallback to the types requested, though less accurate for the final used method
            elseif (isset($sessionData['data']['attributes']['payment_method_types'][0])) {
                $paymentMethod = $sessionData['data']['attributes']['payment_method_types'][0];
            }

            $methodMap = [
                'gcash' => 'GCash',
                'grab_pay' => 'GrabPay',
                'paymaya' => 'Maya',
                'card' => 'Credit/Debit Card',
            ];

            return $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Online Payment';
        } catch (\Exception $e) {
            Log::warning('Could not detect payment method via API check', ['session_id' => $sessionId]);
            return 'Online Payment';
        }
    }

    /**
     * Payment cancelled 
     */
    public function cancelled(Request $request)
    {
        $appointmentId = $request->query('appointment_id');

        // 🐞 FIX: Clear the pending appointment from session
        session()->forget('pending_appointment_id');

        if ($appointmentId) {
            $this->cleanupFailedPayment($appointmentId);
        }

        // Simple cancelled page - no authentication required
        return Inertia::render('Payment/Cancelled', [
            'appointmentId' => $appointmentId,
            'message' => 'Payment was cancelled. No appointment was created or scheduled.'
        ]);
    }
}
