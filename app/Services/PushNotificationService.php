<?php

namespace App\Services;

use App\Jobs\BroadcastCustomerPushNotification;
use App\Models\Advertisement;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    public function __construct(private Messaging $messaging) {}

    public function notifyHomePageAd(Advertisement $ad): void
    {
        if ($ad->placement !== 'home') {
            return;
        }

        $this->broadcastToCustomers(
            'New on home page',
            $ad->title.' — check it out now!',
            [
                'type' => 'home_ad',
                'ad_id' => (string) $ad->id,
                'target_type' => (string) $ad->targetType,
                'target_id' => (string) $ad->targetID,
            ]
        );
    }

    public function notifyStoreOpened(Store $store): void
    {
        $this->broadcastToCustomers(
            'New store opened',
            $store->name.' is now open in the mall!',
            [
                'type' => 'store_opened',
                'store_id' => (string) $store->id,
            ]
        );
    }

    public function notifyServiceOpened(Service $service): void
    {
        $this->broadcastToCustomers(
            'New service available',
            $service->name.' is now open in the mall!',
            [
                'type' => 'service_opened',
                'service_id' => (string) $service->id,
            ]
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    public function broadcastToCustomers(string $title, string $body, array $data = []): void
    {
        BroadcastCustomerPushNotification::dispatch($title, $body, $data);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function broadcastToCustomersSync(string $title, string $body, array $data = []): void
    {
        User::query()
            ->role('Customer')
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->select(['id', 'fcm_token'])
            ->chunkById(100, function ($users) use ($title, $body, $data) {
                foreach ($users as $user) {
                    $this->sendToUser($user, $title, $body, $data);
                }
            });
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (empty($user->fcm_token)) {
            return false;
        }

        return $this->sendToToken($user->fcm_token, $title, $body, $data, $user);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        ?User $user = null
    ): bool {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->normalizeData($data));

            $this->messaging->send($message);

            return true;
        } catch (NotFound $exception) {
            if ($user !== null) {
                $user->update(['fcm_token' => null]);
            }

            Log::warning('FCM token not found; token cleared.', [
                'user_id' => $user?->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } catch (\Throwable $exception) {
            Log::error('Failed to send push notification.', [
                'user_id' => $user?->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * FCM data payload values must be strings.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value);
        }

        return $normalized;
    }
}
