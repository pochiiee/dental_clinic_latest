<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Payment;

class PaymentController extends Controller
{
     public function index()
    {
        $payments = Payment::with([
                'appointment.patient', 
                'appointment.service'
            ])
            ->orderBy('paid_at', 'desc')
            ->get()
            ->map(function ($pay) {

                $patient = $pay->appointment->patient ?? null;
                $service = $pay->appointment->service ?? null;

                return [
                    'id' => $pay->payment_id,
                    'last_name' => $patient->last_name ?? 'N/A',
                    'first_name' => $patient->first_name ?? 'N/A',
                    'procedure' => $service->service_name ?? 'N/A',
                    'amount' => '₱' . number_format($pay->amount, 2),
                    'status' => ucfirst($pay->payment_status),
                    'paid_at' => optional($pay->paid_at)->format('m-d-Y | g:i a'),
                ];
            });

        return Inertia::render('Admin/PaymentTable', [
            'payments' => $payments,
        ]);
    }
}