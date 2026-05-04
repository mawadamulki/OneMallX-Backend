<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $otp,
        public string $subjectTranslationKey = 'app.email_otp_subject',
        public int $expiresInMinutes = 10,
        public bool $isPasswordReset = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __($this->subjectTranslationKey),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
