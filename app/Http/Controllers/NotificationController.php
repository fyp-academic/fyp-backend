<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     * Retrieve all notifications for the authenticated user, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('timestamp', 'desc')
            ->get();

        $unreadCount = $notifications->where('read', false)->count();

        return response()->json(['data' => $notifications, 'unread_count' => $unreadCount]);
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read' => true]);

        return response()->json(['message' => 'Notification marked as read.', 'id' => $id]);
    }

    /**
     * POST /api/v1/notifications/mark-all-read
     * Mark every unread notification as read for the current user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read.', 'updated_count' => $updated]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * Permanently delete a single notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }
}
