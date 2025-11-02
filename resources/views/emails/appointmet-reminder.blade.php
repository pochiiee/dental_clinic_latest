<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appointment Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0E5C5C; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .footer { text-align: center; color: #666; font-size: 14px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
        .appointment-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0E5C5C; }
        .urgent { background: #fff3cd; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🦷 Dental Appointment Reminder</h1>
        </div>
        
        <p>Dear <strong>{{ $patientName }}</strong>,</p>
        
        <p>{!! $greeting !!}</p>
        
        <div class="appointment-details {{ $isToday ? 'urgent' : '' }}">
            <h3 style="margin-top: 0; color: #0E5C5C;">Appointment Details</h3>
            <p><strong>Service:</strong> {{ $serviceName }}</p>
            <p><strong>Date:</strong> {{ $appointmentDate }}</p>
            <p><strong>Time:</strong> {{ $appointmentTime }}</p>
            @if($isToday)
            <p style="color: #856404; font-weight: bold;">📍 Your appointment is TODAY!</p>
            @endif
        </div>
        
        <div class="content">
            <p><strong>📍 Clinic Information:</strong></p>
            <p>{{ $clinicName }}<br>
            {{ $clinicAddress }}<br>
            📞 Phone: {{ $clinicPhone }}</p>
            
            <p><strong>📋 Important Reminders:</strong></p>
            <ul>
                <li>Please arrive <strong>10-15 minutes</strong> before your scheduled time</li>
                <li>Bring your ID and any insurance information</li>
                <li>Let us know if you need to reschedule at least 24 hours in advance</li>
                <li>Contact us if you have any questions or concerns</li>
            </ul>
            
            @if($isToday)
            <div style="background: #d4edda; padding: 10px; border-radius: 5px; border-left: 4px solid #28a745;">
                <p style="margin: 0; color: #155724; font-weight: bold;">
                    ⚠️ Your appointment is today! Please don't forget to come at {{ $appointmentTime }}
                </p>
            </div>
            @endif
        </div>
        
        <p>We look forward to seeing you and helping you maintain your beautiful smile! 😊</p>
        
        <div class="footer">
            <p>Best regards,<br>
            <strong>The {{ $clinicName }} Team</strong></p>
            <p>📍 {{ $clinicAddress }} | 📞 {{ $clinicPhone }}</p>
            <p style="font-size: 12px; color: #999;">
                This is an automated reminder. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>