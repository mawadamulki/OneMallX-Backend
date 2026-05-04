<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public bool $isStoreAccount,
        public ?string $rejectionReason,
    ) {}

    public function build(): static
    {
        $subject = $this->isStoreAccount
            ? __('app.email_subscription_rejected_subject_store')
            : __('app.email_subscription_rejected_subject_service');

        return $this->subject($subject)
            ->view('emails.subscription-request-rejected')
            ->with([
                'applicantName' => $this->applicantName,
                'isStoreAccount' => $this->isStoreAccount,
                'rejectionReason' => $this->rejectionReason,
                'supportUrl' => config('app.frontend_support_url'),
            ]);
    }
}
