<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\Appointment;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $appointment;
    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, Payment $payment)
    {
        $this->appointment = $appointment;
        $this->payment = $payment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Payment Receipt - District Smile Dental Clinic',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.receipt', // this is your Blade view
            with: [
                'appointment' => $this->appointment,
                'payment' => $this->payment,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', [
            'appointment' => $this->appointment,
            'payment' => $this->payment,
        ]);

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn() => $pdf->output(), 'Payment_Receipt.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
