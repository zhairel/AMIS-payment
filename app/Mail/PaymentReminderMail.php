<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Paths to the three embedded images.
     * These are resolved at send-time so the mailable can be serialized safely.
     */
    public string $image1Path;
    public string $image2Path;
    public string $image3Path;
    public string $logoPath;

    public function __construct()
    {
        $this->image1Path = public_path('images/reminder/poster_payment_reminder.png');
        $this->image2Path = public_path('images/reminder/poster_payment_info.png');
        $this->image3Path = public_path('images/reminder/banner_already_paid.jpg');
        $this->logoPath   = public_path('images/AMIS_Logo.png');
    }

    /**
     * Build the envelope using the dedicated reminder sender identity.
     * Other system emails continue to use the default MAIL_FROM_* config.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                env('REMINDER_MAIL_FROM_ADDRESS', config('mail.from.address')),
                env('REMINDER_MAIL_FROM_NAME', 'AMIS Support Staff')
            ),
            subject: 'AMIS Payment Reminder – Monthly School Fees',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: null,   // Using build() below for CID embed support
            view: null,
        );
    }

    /**
     * Build the mailable using the legacy build() method so that
     * $message->embed() is available inside the Blade view.
     */
    public function build(): static
    {
        return $this->view('emails.payment_reminder')
                    ->subject('AMIS Payment Reminder – Monthly School Fees')
                    ->from(
                        env('REMINDER_MAIL_FROM_ADDRESS', config('mail.from.address')),
                        env('REMINDER_MAIL_FROM_NAME', 'AMIS Support Staff')
                    )
                    ->with([
                        'image1Path' => $this->image1Path,
                        'image2Path' => $this->image2Path,
                        'image3Path' => $this->image3Path,
                    ]);
    }
}
