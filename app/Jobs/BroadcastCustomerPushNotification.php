<?php

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastCustomerPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(PushNotificationService $pushNotification): void
    {
        $pushNotification->pushBroadcastToCustomers($this->title, $this->body, $this->data);
    }
}
