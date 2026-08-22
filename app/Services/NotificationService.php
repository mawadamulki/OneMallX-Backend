<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(int $userId, string $title, string $body, array $data = []): UserNotification
    {
        return UserNotification::query()->create([
            'userID' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => (string) ($data['type'] ?? 'general'),
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function broadcastToCustomers(string $title, string $body, array $data = []): void
    {
        User::query()
            ->role('Customer')
            ->select(['id'])
            ->chunkById(100, function ($users) use ($title, $body, $data) {
                foreach ($users as $user) {
                    $this->createForUser((int) $user->id, $title, $body, $data);
                }
            });
    }

    public function listForUser(int $userId, int $perPage): array
    {
        $notifications = UserNotification::query()
            ->where('userID', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'success' => true,
            'unreadCount' => UserNotification::query()
                ->where('userID', $userId)
                ->whereNull('readAt')
                ->count(),
            'notifications' => $notifications->through(fn (UserNotification $notification) => $this->format($notification)),
        ];
    }

    public function markAsRead(int $userId, int $notificationId): array
    {
        $notification = UserNotification::query()
            ->whereKey($notificationId)
            ->where('userID', $userId)
            ->first();

        if ($notification === null) {
            return [
                'success' => false,
                'message' => 'Notification not found.',
                'http_status' => 404,
            ];
        }

        if ($notification->readAt === null) {
            $notification->update(['readAt' => now()]);
        }

        return [
            'success' => true,
            'message' => 'Notification marked as read.',
            'notification' => $this->format($notification->fresh()),
        ];
    }

    public function markAllAsRead(int $userId): array
    {
        $updated = UserNotification::query()
            ->where('userID', $userId)
            ->whereNull('readAt')
            ->update(['readAt' => now()]);

        return [
            'success' => true,
            'message' => 'All notifications marked as read.',
            'updatedCount' => $updated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function format(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'type' => $notification->type,
            'data' => $notification->data ?? [],
            'isRead' => $notification->readAt !== null,
            'readAt' => $notification->readAt?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
        ];
    }
}
