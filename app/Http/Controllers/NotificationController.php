<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        return response()->json(
            $this->notificationService->listForUser((int) Auth::id(), $perPage)
        );
    }

    public function markAsRead(int $id)
    {
        $result = $this->notificationService->markAsRead((int) Auth::id(), $id);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['http_status'] ?? 404);
        }

        return response()->json($result);
    }

    public function markAllAsRead()
    {
        return response()->json(
            $this->notificationService->markAllAsRead((int) Auth::id())
        );
    }
}
