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

    /**
     * Create Payment 
     */
    public function createPayment(Request $request)
    {
        Log::info('Payment creation started', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        $amount = 30000;
        $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');

        // ✅ FIXED VALIDATION - Remove appointment_id since it doesn't exist yet
        try {
            $validated = $request->validate([
                'service_id' => 'required|exists:services,service_id',
                'appointment_date' => 'required|date', 
                'service_name' => 'required|string',
                'schedule_id' => 'required|exists:schedules,schedule_id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            $errorMessages = collect($e->errors())->flatten()->implode(', ');
            return response()->json([
                'error' => 'Invalid request data: ' . $errorMessages
            ], 422);
        }

        Log::info('Validation passed', ['validated_data' => $validated]);

        // Use database transaction to prevent race conditions
        DB::beginTransaction();

        try {
            // Check if user has existing appointment
            $existingAppointment = Appointment::where('patient_id', Auth::id())
                ->whereIn('status', ['pending', 'ongoing', 'confirmed'])
                ->first();

            if ($existingAppointment) {
                DB::rollBack();
                Log::warning('User has existing appointment', [
                    'user_id' => Auth::id(),
                    'existing_appointment_id' => $existingAppointment->appointment_id
                ]);
                return response()->json([
                    'error' => 'You already have a pending or ongoing appointment.'
                ], 422);
            }

            // Parse the appointment date
            $appointmentDate = Carbon::parse($validated['appointment_date'])->format('Y-m-d');
            
            // Check if the slot is already booked
            $isBooked = Appointment::where('schedule_id', $validated['schedule_id'])
                ->whereDate('appointment_date', $appointmentDate)
                ->whereIn('status', ['confirmed'])
                ->exists();

            if ($isBooked) {
                DB::rollBack();
                return response()->json(['error' => 'This time slot was just booked by another user. Please choose another time.'], 422);
            }

            // Clean up previous unfinished appointment
            Appointment::where(function ($q) use ($validated, $appointmentDate) {
                    $q->where('schedule_id', $validated['schedule_id'])
                    ->whereDate('appointment_date', $appointmentDate)
                    ->where('status', 'pending');
                })
                ->where(function ($q) {
                    $q->where('patient_id', Auth::id())
                    ->orWhere('created_at', '<', now()->subMinutes(30));
                })
                ->delete();

            // ✅ CREATE TEMPORARY APPOINTMENT FIRST (this generates the appointment_id)
            $tempAppointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $validated['service_id'],
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'status' => 'pending',
            ]);

            if (!$tempAppointment->appointment_id) {
                DB::rollBack();
                return response()->json(['error' => 'Failed to reserve time slot. Please try again.'], 500);
            }

            // Get schedule for time display
            $schedule = Schedule::find($validated['schedule_id']);
            $timeDisplay = $this->formatTimeForDisplay($schedule->start_time, $schedule->end_time);


            // ✅ FIXED SESSION DATA - Use the newly created appointment_id
            session([
                'pending_payment' => [
                    'service_id' => $validated['service_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'schedule_id' => $validated['schedule_id'],
                    'service_name' => $validated['service_name'],
                    'amount' => $amount,
                    'user_id' => Auth::id(),
                    'temp_appointment_id' => $tempAppointment->appointment_id, // ✅ Use the newly created ID
                    'time_display' => $timeDisplay,
                ]
            ]);

            // Create PayMongo checkout session
            $paymentMethods = ['gcash', 'grab_pay', 'paymaya', 'card'];

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => [
                                [
                                    'name' => $validated['service_name'] . ' - District Smile Dental Clinic',
                                    'amount' => $amount,
                                    'currency' => 'PHP',
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => $paymentMethods,
                            'success_url' => route('payment.success'),
                            'cancel_url' => route('payment.cancelled'),
                            'description' => 'Service Fee of ₱300 for ' . $validated['service_name'],
                            'send_email_receipt' => false,
                            'show_description' => true,
                            'show_line_items' => true,
                            'metadata' => [
                                'user_id' => Auth::id(),
                                'service_id' => $validated['service_id'],
                                'schedule_id' => $validated['schedule_id'],
                                'appointment_date' => $appointmentDate,
                                'service_name' => $validated['service_name'],
                                'temp_appointment_id' => $tempAppointment->appointment_id, // ✅ Use the new ID
                            ],
                        ],
                    ],
                ]);

            Log::info('PayMongo API response', ['status' => $response->status()]);

            $checkoutData = $response->json();

            if (!$response->successful()) {
                // Delete the temporary appointment if payment fails
                $this->cleanupFailedPayment($tempAppointment->appointment_id);
                DB::rollBack();

                Log::error('PayMongo API failed', [
                    'status' => $response->status(),
                    'errors' => $checkoutData['errors'] ?? null
                ]);
                
                $errorMessage = 'Failed to create payment session. ';
                if (isset($checkoutData['errors'][0]['detail'])) {
                    $errorMessage .= $checkoutData['errors'][0]['detail'];
                }
                
                return response()->json(['error' => $errorMessage], 500);
            }

            if (!isset($checkoutData['data']['attributes']['checkout_url'])) {
                // Delete the temporary appointment if invalid response
                $this->cleanupFailedPayment($tempAppointment->appointment_id);
                DB::rollBack();

                Log::error('PayMongo invalid response', ['response' => $checkoutData]);
                return response()->json([
                    'error' => 'Payment gateway returned invalid response.'
                ], 500);
            }

            // Update session with paymongo session id
            session([
                'pending_payment.checkout_session_id' => $checkoutData['data']['id'],
                'pending_payment.paymongo_session_id' => $checkoutData['data']['id'],
                'pending_payment.temp_appointment_id' => $tempAppointment->appointment_id
            ]);

            DB::commit();

            return response()->json([
                'checkout_url' => $checkoutData['data']['attributes']['checkout_url'],
                'status' => 'created',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up any temporary appointment on exception
            if (isset($tempAppointment) && $tempAppointment->appointment_id) {
                $this->cleanupFailedPayment($tempAppointment->appointment_id);
            }

            Log::error('💥 Payment create exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Payment processing failed. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Success Payment - WITH DOUBLE SAFETY CHECK
     */
    public function success(Request $request)
    {
        try {
            $sessionId = $request->query('session_id');

            if (!$sessionId) {
                return redirect()->route('customer.appointment')->with('error', 'Invalid payment session.');
            }

            $pendingPayment = session('pending_payment');
            
            if (!$pendingPayment) {
                return redirect()->route('customer.appointment')->with('error', 'Payment session expired.');
            }

            if ($pendingPayment['user_id'] != Auth::id()) {
                return redirect()->route('customer.appointment')->with('error', 'Payment session user mismatch.');
            }

            DB::beginTransaction();

            try {
                $appointment = Appointment::where('appointment_id', $pendingPayment['temp_appointment_id'])
                    ->where('patient_id', Auth::id())
                    ->first();

                if (!$appointment) {
                    throw new \Exception('Temporary appointment not found');
                }

                // ✅ SAFETY CHECK 1: Verify payment with PayMongo API
                $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
                $verifyResponse = Http::withBasicAuth($paymongo_SecretKey, '')
                    ->get("https://api.paymongo.com/v1/checkout_sessions/{$pendingPayment['paymongo_session_id']}");

                if (!$verifyResponse->successful()) {
                    throw new \Exception('Could not verify payment with PayMongo');
                }

                $sessionData = $verifyResponse->json();
                $payments = $sessionData['data']['attributes']['payments'] ?? [];
                $paymentStatus = null;

                if (count($payments) > 0) {
                    $paymentStatus = $payments[0]['attributes']['status'] ?? null;
                }

                // ✅ SAFETY CHECK 2: Only confirm if payment is actually paid AND appointment is still pending
                if ($paymentStatus === 'paid' && $appointment->status === 'pending') {
                    $appointment->update([
                        'status' => 'confirmed',
                    ]);

                    // Detect payment method
                    $paymentMethod = $this->detectPaymentMethod($pendingPayment['paymongo_session_id']);
                    
                    $payment = Payment::create([
                        'appointment_id' => $appointment->appointment_id,
                        'amount' => $pendingPayment['amount'] / 100,
                        'payment_method' => $paymentMethod,
                        'payment_status' => 'completed',
                        'transaction_reference' => $pendingPayment['paymongo_session_id'],
                        'paid_at' => now(),
                    ]);

                    Log::info('✅ PAYMENT CONFIRMED VIA SUCCESS PAGE', [
                        'appointment_id' => $appointment->appointment_id,
                        'payment_status' => $paymentStatus,
                        'method' => 'success_page'
                    ]);

                    $successMessage = 'Payment successful! Your appointment has been confirmed.';
                    
                } elseif ($appointment->status === 'confirmed') {
                    // Appointment already confirmed (possibly by webhook if it works)
                    Log::info('Appointment already confirmed', [
                        'appointment_id' => $appointment->appointment_id,
                        'current_status' => $appointment->status
                    ]);
                    
                    $successMessage = 'Payment already processed! Your appointment is confirmed.';
                    
                } else {
                    // Payment not yet completed
                    Log::warning('Payment not paid yet', [
                        'appointment_id' => $appointment->appointment_id,
                        'payment_status' => $paymentStatus,
                        'appointment_status' => $appointment->status
                    ]);
                    
                    $successMessage = 'Payment is processing. Your appointment will be confirmed shortly.';
                }

                DB::commit();

                // Redirect logic
                $redirectUrl = $request->header('referer') ?: route('customer.appointment');
                $avoidUrls = ['payment.success', 'payment.cancelled', 'login'];
                foreach ($avoidUrls as $avoidUrl) {
                    if (str_contains($redirectUrl, $avoidUrl)) {
                        $redirectUrl = route('customer.appointment');
                        break;
                    }
                }

                // Clear session
                session()->forget('pending_payment');

                if (str_contains($successMessage, 'successful')) {
                    return redirect($redirectUrl)->with('success', $successMessage);
                } else {
                    return redirect($redirectUrl)->with('info', $successMessage);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Payment success failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            // Clean up on error
            if (isset($pendingPayment['temp_appointment_id'])) {
                $this->cleanupFailedPayment($pendingPayment['temp_appointment_id']);
            }

            session()->forget('pending_payment');
            
            return redirect()->route('customer.appointment')->with('error', 'Payment processing failed. Please try again.');
        }
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
     * Detect payment method
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

            return $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Unknown';

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

        $pendingPayment = session('pending_payment');
        if ($pendingPayment && isset($pendingPayment['temp_appointment_id'])) {
            $this->cleanupFailedPayment($pendingPayment['temp_appointment_id']);
        }

        // GET REFERRER URL
        $redirectUrl = $request->header('referer') ?: route('customer.appointment');
        
        // Avoid redirecting to payment URLs
        if (str_contains($redirectUrl, 'payment.success') || str_contains($redirectUrl, 'payment.cancelled')) {
            $redirectUrl = route('customer.appointment');
        }

        session()->forget(['pending_payment', 'pending_appointment']);

        return redirect($redirectUrl)
            ->with('error', 'Payment was cancelled. No appointment was created.');
    }

    /**
     * Webhook handler - FOR WHEN YOU GET A DOMAIN
     */
    public function webhook(Request $request)
    {
        Log::info('PayMongo webhook received', $request->all());

        try {
            $eventType = $request->input('data.attributes.type');

            if ($eventType !== 'payment.paid') {
                return response()->json(['ignored' => true]);
            }

            $paymentData = $request->input('data.attributes.data.attributes');
            $metadata = $paymentData['metadata'] ?? [];
            $appointmentId = $metadata['temp_appointment_id'] ?? null;

            if (!$appointmentId) {
                Log::warning('Webhook missing appointment_id');
                return response()->json(['error' => 'Missing appointment_id'], 400);
            }

            $appointment = Appointment::find($appointmentId);
            if (!$appointment) {
                return response()->json(['error' => 'Appointment not found'], 404);
            }

            // ✅ Verify payment status from PayMongo API
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            $paymentId = $request->input('data.id'); 

            $verifyResponse = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/payments/{$paymentId}");

            if (!$verifyResponse->successful()) {
                Log::error('PayMongo verification failed', ['payment_id' => $paymentId]);
                return response()->json(['error' => 'Unable to verify payment'], 400);
            }

            $verifyData = $verifyResponse->json();
            $paymentStatus = $verifyData['data']['attributes']['status'] ?? null;

            if ($paymentStatus !== 'paid') {
                Log::warning('Payment not confirmed by PayMongo', ['status' => $paymentStatus]);
                return response()->json(['ignored' => true]);
            }

            // ✅ Only confirm if still pending (safety check)
            if ($appointment->status === 'pending') {
                $appointment->update(['status' => 'confirmed']);

                Payment::updateOrCreate(
                    ['appointment_id' => $appointment->appointment_id],
                    [
                        'amount' => $paymentData['amount'] / 100,
                        'payment_method' => ucfirst($paymentData['payment_method_used'] ?? 'Online'),
                        'payment_status' => 'completed',
                        'transaction_reference' => $paymentId,
                        'paid_at' => now(),
                    ]
                );

                Log::info('✅ PAYMENT CONFIRMED VIA WEBHOOK', [
                    'appointment_id' => $appointment->appointment_id,
                    'payment_id' => $paymentId,
                    'method' => 'webhook'
                ]);
            } else {
                Log::info('Appointment already confirmed, webhook ignored', [
                    'appointment_id' => $appointment->appointment_id,
                    'current_status' => $appointment->status
                ]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Helper: Format time for display
     */
    private function formatTimeForDisplay($startTime, $endTime): string
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        
        return $start->format('g:i A') . ' - ' . $end->format('g:i A');
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