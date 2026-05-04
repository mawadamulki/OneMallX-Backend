<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public bool $isStoreAccount,
    ) {}

    public function build(): static
    {
        $subject = $this->isStoreAccount
            ? __('app.email_subscription_approved_subject_store')
            : __('app.email_subscription_approved_subject_service');

        return $this->subject($subject)
            ->view('emails.subscription-request-approved')
            ->with([
                'applicantName' => $this->applicantName,
                'isStoreAccount' => $this->isStoreAccount,
                'loginUrl' => config('app.frontend_login_url'),
            ]);
    }
}
