<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Only ever hit once (on page load/reconnect) to hydrate the bell dropdown — everything
// after that arrives live over the user's own private Reverb channel
// (App.Models.User.{id}), never polled.
class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user('user');

        $notifications = $user->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->success(
            message: 'Notifications retrieved successfully.',
            data: $notifications,
            meta: [
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        );
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user('user')->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->success(message: 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user('user')->unreadNotifications->markAsRead();

        return $this->success(message: 'All notifications marked as read.');
    }
}
