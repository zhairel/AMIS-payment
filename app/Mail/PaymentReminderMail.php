<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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

    public function __construct(
        public ?string $recipientName = null,
        public ?string $billingMonth = null,
        public ?string $dispatchRef = null,
    ) {
        $this->image1Path = public_path('images/reminder/poster_payment_reminder.png');
        if (!file_exists($this->image1Path)) {
            $this->image1Path = public_path('images/reminder/image1_due_soon.png');
        }

        $this->image2Path = public_path('images/reminder/poster_payment_info.png');
        if (!file_exists($this->image2Path)) {
            $this->image2Path = public_path('images/reminder/image2_payment_info.png');
        }

        $this->image3Path = public_path('images/reminder/banner_already_paid.jpg');
        if (!file_exists($this->image3Path)) {
            $this->image3Path = public_path('images/reminder/image3_automated_reminder.jpg');
        }

        $this->logoPath   = public_path('images/AMIS_Logo.png');

        // Explicitly purge any threading headers from the Symfony MIME message
        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) {
            $headers = $message->getHeaders();
            if ($headers->has('In-Reply-To')) {
                $headers->remove('In-Reply-To');
            }
            if ($headers->has('References')) {
                $headers->remove('References');
            }
            if ($headers->has('Thread-Topic')) {
                $headers->remove('Thread-Topic');
            }
            if ($headers->has('Thread-Index')) {
                $headers->remove('Thread-Index');
            }
        });
    }

    /**
     * Build the unique recipient-specific subject line to prevent Gmail conversation threading.
     * Format: AMIS Payment Reminder – [Month Year] – [Family/Student Name]
     * Examples:
     * - AMIS Payment Reminder – August 2026 – ZHAIREL LINGASA
     * - AMIS Payment Reminder – August 2026 – ABDULRAHEEM BAULO
     * - AMIS Payment Reminder – August 2026 – ZAKI ALIH
     */
    public function resolveSubject(): string
    {
        $name = trim((string) $this->recipientName);
        if (empty($name)) {
            $name = 'VALUED FAMILY';
        } else {
            $name = mb_strtoupper($name);
        }

        $monthYear = null;
        if (!empty($this->billingMonth)) {
            try {
                $rawMonth = trim($this->billingMonth);
                $dateStr = strlen($rawMonth) === 7 ? $rawMonth . '-01' : $rawMonth;
                $monthYear = Carbon::parse($dateStr)->format('F Y');
            } catch (\Throwable) {
                $monthYear = $this->billingMonth;
            }
        }
        if (empty($monthYear)) {
            $monthYear = Carbon::now()->format('F Y');
        }

        $refSuffix = !empty($this->dispatchRef) ? " [{$this->dispatchRef}]" : '';

        return "AMIS Payment Reminder – {$monthYear} – {$name}{$refSuffix}";
    }

    /**
     * Build the envelope using the dedicated reminder sender identity.
     * Other system emails continue to use the default MAIL_FROM_* config.
     */
    public function envelope(): Envelope
    {
        $fromName    = env('REMINDER_MAIL_FROM_NAME', 'AMIS Support Staff');
        $fromAddress = env('REMINDER_MAIL_FROM_ADDRESS', config('mail.from.address', 'amisonlinesupport@gmail.com'));

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->resolveSubject(),
        );
    }

    /**
     * Configure unique message headers to strictly prevent Gmail conversation grouping.
     */
    public function headers(): Headers
    {
        $uniqueToken = bin2hex(random_bytes(16)) . '.' . microtime(true);
        $domain = parse_url(config('app.url', 'http://amis.edu.ph'), PHP_URL_HOST) ?: 'amis.edu.ph';

        return new Headers(
            messageId: "reminder.{$uniqueToken}@{$domain}",
            references: [],
            text: [
                'X-Entity-Ref-ID'    => (string) Str::uuid(),
                'X-AMIS-Reminder-ID' => (string) Str::uuid(),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_reminder',
            with: [
                'image1Path'    => $this->image1Path,
                'image2Path'    => $this->image2Path,
                'image3Path'    => $this->image3Path,
                'logoPath'      => $this->logoPath,
                'recipientName' => $this->recipientName,
                'billingMonth'  => $this->billingMonth,
            ],
        );
    }

    /**
     * Build the mailable using the legacy build() method so that
     * $message->embed() is available inside the Blade view.
     */
    public function build(): static
    {
        return $this->view('emails.payment_reminder')
                    ->subject($this->resolveSubject())
                    ->from(
                        env('REMINDER_MAIL_FROM_ADDRESS', config('mail.from.address', 'amisonlinesupport@gmail.com')),
                        env('REMINDER_MAIL_FROM_NAME', 'AMIS Support Staff')
                    )
                    ->with([
                        'image1Path'    => $this->image1Path,
                        'image2Path'    => $this->image2Path,
                        'image3Path'    => $this->image3Path,
                        'logoPath'      => $this->logoPath,
                        'recipientName' => $this->recipientName,
                        'billingMonth'  => $this->billingMonth,
                    ]);
    }
}
