<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 40px;
            color: #333;
            font-size: 14px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #00bfa6;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #00bfa6;
            margin: 0;
        }
        .details, .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .details th, .details td {
            padding: 8px 12px;
            text-align: left;
        }
        .summary th, .summary td {
            padding: 10px 12px;
            border-top: 1px solid #ccc;
        }
        .summary td:last-child {
            text-align: right;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            border-top: 2px solid #00bfa6;
            padding-top: 15px;
            font-size: 12px;
            color: #666;
        }
        .highlight {
            color: #00bfa6;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>District Smile Dental Clinic</h1>
        <p>Payment Receipt</p>
    </div>

    <table class="details">
        <tr>
            <th>Patient Name:</th>
            <td>{{ $appointment->patient->name }}</td>
        </tr>
        <tr>
            <th>Email:</th>
            <td>{{ $appointment->patient->email }}</td>
        </tr>
        <tr>
            <th>Service:</th>
            <td>{{ $appointment->service->service_name }}</td>
        </tr>
        <tr>
            <th>Date:</th>
            <td>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') }}</td>
        </tr>
        <tr>
            <th>Time:</th>
            <td>{{ $appointment->schedule->start_time }} - {{ $appointment->schedule->end_time }}</td>
        </tr>
        <tr>
            <th>Transaction ID:</th>
            <td>{{ $payment->transaction_reference }}</td>
        </tr>
        <tr>
            <th>Payment Method:</th>
            <td>{{ $payment->payment_method }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <th>Total Paid:</th>
            <td class="highlight">₱{{ number_format($payment->amount, 2) }}</td>
        </tr>
        <tr>
            <th>Payment Status:</th>
            <td>{{ ucfirst($payment->payment_status) }}</td>
        </tr>
        <tr>
            <th>Date Paid:</th>
            <td>{{ \Carbon\Carbon::parse($payment->paid_at)->format('F j, Y g:i A') }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Thank you for trusting <strong>District Smile Dental Clinic</strong>!</p>
        <p>This is a system-generated receipt. No signature required.</p>
    </div>
</body>
</html>
