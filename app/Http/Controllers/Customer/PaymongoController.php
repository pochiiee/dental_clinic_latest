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

class PaymongoController extends Controller
{
    /**
     * All Available Payments
     */
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

        $amount = 30000;
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
            // Check if user has existing appointment
            $existingAppointment = Appointment::where('patient_id', Auth::id())
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existingAppointment) {
                DB::rollBack();
                return response()->json(['error' => 'You already have a pending or confirmed appointment.'], 422);
            }

            // Check if the slot is already booked
            $isBooked = Appointment::where('schedule_id', $validated['schedule_id'])
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->whereIn('status', ['confirmed'])
                ->exists();

            if ($isBooked) {
                DB::rollBack();
                return response()->json(['error' => 'This time slot was just booked by another user. Please choose another time.'], 422);
            }

            // Clean up previous unfinished appointments
            Appointment::where('patient_id', Auth::id())
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->delete();

            // Create appointment
            $appointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $validated['service_id'],
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'status' => 'pending',
            ]);

            if (!$appointment->appointment_id) {
                throw new \Exception('Failed to create appointment');
            }

            Log::info('Appointment created', ['appointment_id' => $appointment->appointment_id]);

            // Create PayMongo checkout session with YOUR DOMAIN
            $paymentMethods = ['gcash', 'grab_pay', 'paymaya', 'card'];
            
            $successUrl = 'https://districtsmiles.online/payment/success?appointment_id=' . $appointment->appointment_id . '&checkout_session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = 'https://districtsmiles.online/payment/cancelled?appointment_id=' . $appointment->appointment_id;

            $payload = [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'cancel_url' => $cancelUrl,
                        'success_url' => $successUrl,
                        'payment_method_types' => $paymentMethods,
                        'line_items' => [
                            [
                                'amount' => $amount,
                                'currency' => 'PHP',
                                'name' => $validated['service_name'] . ' - District Smile Dental Clinic',
                                'quantity' => 1,
                            ]
                        ],
                        'description' => 'Service Fee of ₱300 for ' . $validated['service_name'],
                        'metadata' => [
                            'user_id' => Auth::id(),
                            'service_id' => $validated['service_id'],
                            'schedule_id' => $validated['schedule_id'],
                            'appointment_date' => $validated['appointment_date'],
                            'service_name' => $validated['service_name'],
                            'appointment_id' => $appointment->appointment_id,
                        ]
                    ]
                ]
            ];

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', $payload);

            Log::info('PayMongo API response', ['status' => $response->status()]);

            if (!$response->successful()) {
                $errorData = $response->json();
                throw new \Exception('PayMongo API failed: ' . ($errorData['errors'][0]['detail'] ?? 'Unknown error'));
            }

            $checkoutData = $response->json();

            if (!isset($checkoutData['data']['attributes']['checkout_url'])) {
                throw new \Exception('PayMongo response missing checkout_url');
            }

            // Save PayMongo session ID to appointment
            $paymongoSessionId = $checkoutData['data']['id'];
            $appointment->paymongo_session_id = $paymongoSessionId;
            $appointment->save();

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
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Success Payment - DUAL VERIFICATION (Webhook + Success URL)
     */
    public function success(Request $request)
    {
        $appointmentId = $request->query('appointment_id');
        $checkoutSessionId = $request->query('checkout_session_id');
        
        Log::info('Payment success callback', [
            'appointment_id' => $appointmentId,
            'checkout_session_id' => $checkoutSessionId,
            'user_id' => Auth::id()
        ]);

        if (!$appointmentId) {
            return redirect()->route('customer.appointment')
                ->with('error', 'Invalid payment session.');
        }

        // Get appointment
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return redirect()->route('customer.appointment')
                ->with('error', 'Appointment not found.');
        }

        // Check if already confirmed via webhook
        if ($appointment->status === 'confirmed') {
            $payment = Payment::where('appointment_id', $appointmentId)->first();
            
            return view('payment.success', [
                'appointment' => $appointment,
                'payment' => $payment,
                'checkoutSessionId' => $checkoutSessionId,
                'message' => 'Payment completed successfully! Your appointment is confirmed.'
            ]);
        }

        // If not confirmed yet, try to verify payment status
        $isPaid = $this->verifyPaymentStatus($checkoutSessionId);
        
        if ($isPaid) {
            // Payment is verified, confirm appointment immediately
            DB::transaction(function () use ($appointment, $checkoutSessionId) {
                $appointment->update(['status' => 'confirmed']);
                
                $paymentMethod = $this->detectPaymentMethod($checkoutSessionId);
                $payment = Payment::create([
                    'appointment_id' => $appointment->appointment_id,
                    'amount' => 300.00,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'completed',
                    'transaction_reference' => $checkoutSessionId,
                    'paid_at' => now(),
                ]);

                // Send receipt email
                try {
                    $user = $appointment->patient;
                    if ($user && $user->email) {
                        Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                        Log::info('✅ PAYMENT RECEIPT EMAIL SENT VIA SUCCESS URL');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send email', ['error' => $e->getMessage()]);
                }

                Log::info('✅ APPOINTMENT CONFIRMED VIA SUCCESS URL VERIFICATION');
            });

            return view('payment.success', [
                'appointment' => $appointment->fresh(),
                'payment' => Payment::where('appointment_id', $appointmentId)->first(),
                'checkoutSessionId' => $checkoutSessionId,
                'message' => 'Payment completed successfully! Your appointment is confirmed.'
            ]);
        }

        // Show processing page if payment not yet confirmed
        return view('payment.processing', [
            'appointment' => $appointment,
            'message' => 'We are verifying your payment. This may take a few moments...'
        ]);
    }

    /**
     * Verify payment status directly with PayMongo API
     */
    private function verifyPaymentStatus($checkoutSessionId)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            
            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");
            
            if ($response->successful()) {
                $sessionData = $response->json();
                $status = $sessionData['data']['attributes']['payments'][0]['attributes']['status'] ?? null;
                
                return $status === 'paid';
            }
        } catch (\Exception $e) {
            Log::error('Payment status verification failed', ['error' => $e->getMessage()]);
        }
        
        return false;
    }

/**
 * WEBHOOK - REAL PAYMENT CONFIRMATION (Primary)
 */
public function webhook(Request $request)
{
    Log::info('Paymongo Webhook Received', [
        'type' => $request->header('paymongo-event'),
        'timestamp' => now()->toDateTimeString(),
        'ip' => $request->ip()
    ]);

    // Verify webhook signature for security
    if (!$this->verifyWebhookSignature($request)) {
        Log::error('Webhook signature verification failed');
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    $webhookData = $request->json()->all();
    $eventType = $webhookData['data']['attributes']['type'] ?? null;
    
    Log::info('Webhook Event Type', ['type' => $eventType]);

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
            $sessionData = $webhookData['data']['attributes']['data'];
            $checkoutSessionId = $sessionData['id'];
            $metadata = $sessionData['attributes']['metadata'] ?? [];
            
            $appointmentId = $metadata['appointment_id'] ?? null;
            $userId = $metadata['user_id'] ?? null;

            Log::info('Processing paid webhook', [
                'appointment_id' => $appointmentId,
                'checkout_session_id' => $checkoutSessionId
            ]);

            if (!$appointmentId) {
                throw new \Exception('No appointment ID in webhook metadata');
            }

            // Get appointment
            $appointment = Appointment::where('appointment_id', $appointmentId)->first();
            
            if (!$appointment) {
                throw new \Exception("Appointment {$appointmentId} not found");
            }

            // Double-check this is the user's appointment
            if ($appointment->patient_id != $userId) {
                throw new \Exception("Appointment user mismatch");
            }

            // Check if already processed
            if ($appointment->status === 'confirmed') {
                Log::info('Appointment already confirmed', ['appointment_id' => $appointmentId]);
                DB::commit();
                return response()->json(['status' => 'already_processed']);
            }

            // ✅ CONFIRM APPOINTMENT
            $appointment->update([
                'status' => 'confirmed',
            ]);

            // ✅ CREATE PAYMENT RECORD
            $paymentMethod = $this->detectPaymentMethodFromWebhook($webhookData);
            
            $payment = Payment::create([
                'appointment_id' => $appointment->appointment_id,
                'amount' => 300.00,
                'payment_method' => $paymentMethod,
                'payment_status' => 'completed',
                'transaction_reference' => $checkoutSessionId,
                'paid_at' => now(),
            ]);

            // Send receipt email
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

            Log::info('✅ PAYMENT CONFIRMED VIA WEBHOOK', [
                'appointment_id' => $appointmentId,
                'payment_id' => $payment->payment_id,
                'payment_method' => $paymentMethod
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Webhook payment processing failed', [
                'error' => $e->getMessage(),
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
     * Detect payment method from session
     */
    private function detectPaymentMethod($sessionId)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            
            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");
            
            $sessionData = $response->json();

            // Check for payment method
            $paymentMethod = null;
            if (isset($sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'])) {
                $paymentMethod = $sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'];
            } elseif (isset($sessionData['data']['attributes']['payment_method_types'][0])) {
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
            Log::warning('Could not detect payment method', ['session_id' => $sessionId]);
            return 'Online Payment';
        }
    }

    /**
     * Payment cancelled 
     */
    public function cancelled(Request $request)
    {
        Log::info('Payment cancelled', ['user_id' => Auth::id()]);

        $appointmentId = $request->query('appointment_id');
        
        if ($appointmentId) {
            $this->cleanupFailedPayment($appointmentId);
        }

        session()->forget(['pending_payment', 'pending_appointment']);

        return redirect()->route('customer.appointment')
            ->with('error', 'Payment was cancelled. No appointment was created.');
    }

    /**
     * Manual verification - FOR DEVELOPMENT TESTING
     */
    public function manualVerify(Request $request)
    {
        // Only allow in local development
        if (!app()->environment('local')) {
            abort(404);
        }

        $appointmentId = $request->input('appointment_id');
        
        if (!$appointmentId) {
            return redirect()->back()->with('error', 'Appointment ID required');
        }

        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return redirect()->back()->with('error', 'Appointment not found');
        }

        // Manually confirm for testing
        if ($appointment->status === 'pending') {
            $appointment->update(['status' => 'confirmed']);
            
            Payment::create([
                'appointment_id' => $appointment->appointment_id,
                'amount' => 300.00,
                'payment_method' => 'Manual_Test',
                'payment_status' => 'completed',
                'transaction_reference' => 'manual_test_' . time(),
                'paid_at' => now(),
            ]);

            return redirect()->route('customer.appointment')->with('success', 'Appointment manually confirmed for testing.');
        }

        return redirect()->route('customer.appointment')->with('info', 'Appointment already confirmed.');
    }
}