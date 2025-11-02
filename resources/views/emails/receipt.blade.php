@component('mail::message')
# 🦷 Payment Receipt

Hello {{ $appointment->patient->name }},

Your payment has been successfully processed!

---

**Service:** {{ $appointment->service->service_name }}  
**Date:** {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') }}  
**Time:** {{ $appointment->schedule->start_time }} - {{ $appointment->schedule->end_time }}  
**Amount Paid:** ₱{{ number_format($payment->amount, 2) }}  
**Payment Method:** {{ $payment->payment_method }}  
**Transaction ID:** {{ $payment->transaction_reference }}

---

@component('mail::button', ['url' => route('customer.view')])
View Appointment
@endcomponent

Thank you for trusting **District Smile Dental Clinic**!

@endcomponent
