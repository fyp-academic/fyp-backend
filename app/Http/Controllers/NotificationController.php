<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Constants\NotificationTypes;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // ─────────────────────────────────────────────────────────────────────
    // NOTIFICATION LIST API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/notifications
     * Get paginated notifications for the authenticated user.
     * Query params: page, limit, status (all|unread|read|pending), channel (in_app|email|push|sms)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'status' => 'in:all,unread,read,pending,sent,delivered,failed',
            'channel' => 'in:in_app,email,push,sms',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $status = $validated['status'] ?? 'all';
        $channel = $validated['channel'] ?? 'in_app';

        // Check global mute
        if ($this->notificationService->isGloballyMuted($user->id)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $limit,
                    'total' => 0,
                ],
                'unread_count' => 0,
            ]);
        }

        // Get enabled notification types for this channel
        $enabledTypes = NotificationPreference::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->pluck('notification_type')
            ->toArray();

        $query = Notification::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereIn('type', $enabledTypes)
            ->orderBy('created_at', 'desc');

        // Apply status filter
        switch ($status) {
            case 'unread':
                $query->whereIn('status', ['sent', 'delivered']);
                break;
            case 'read':
                $query->where('status', 'read');
                break;
            case 'pending':
            case 'sent':
            case 'delivered':
            case 'failed':
                $query->where('status', $status);
                break;
        }

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        // Get unread count (only for enabled types)
        $unreadCount = Notification::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereIn('type', $enabledTypes)
            ->whereIn('status', ['sent', 'delivered'])
            ->count();

        // Transform notifications to match frontend expected fields
        $transformedItems = collect($paginated->items())->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'channel' => $notification->channel,
                'title' => $notification->title,
                'body' => $notification->body,
                'message' => $notification->body, // Alias for frontend compatibility
                'payload' => $notification->payload,
                'status' => $notification->status,
                'read' => $notification->status === 'read',
                'read_at' => $notification->read_at,
                'sent_at' => $notification->sent_at,
                'timestamp' => $notification->created_at->toISOString(),
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
            ];
        })->all();

        return response()->json([
            'data' => $transformedItems,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $channel = $request->input('channel', 'in_app');
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->where('channel', $channel)
            ->firstOrFail();

        $notification->markAsRead();

        // Broadcast badge update
        $unreadCount = $this->calculateUnreadCount($request->user()->id, $channel);
        broadcast(new \App\Events\NotificationBadgeUpdated($request->user()->id, $unreadCount))->toOthers();

        return response()->json([
            'message' => 'Notification marked as read.',
            'id' => $id,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /api/v1/notifications/read-all
     * Mark all unread in-app notifications as read for the current user (respecting preferences).
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $channel = $request->input('channel', 'in_app');

        // Get enabled notification types for this channel
        $enabledTypes = NotificationPreference::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->pluck('notification_type')
            ->toArray();

        $updated = Notification::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereIn('type', $enabledTypes)
            ->whereIn('status', ['sent', 'delivered'])
            ->update(['status' => 'read', 'read_at' => now()]);

        // Broadcast badge update (now 0 for this channel)
        broadcast(new \App\Events\NotificationBadgeUpdated($user->id, 0));

        return response()->json([
            'message' => 'All notifications marked as read.',
            'updated_count' => $updated,
            'unread_count' => 0,
        ]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * Permanently delete a single notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $channel = $request->input('channel', 'in_app');
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->where('channel', $channel)
            ->firstOrFail();

        $wasUnread = in_array($notification->status, ['sent', 'delivered']);
        $notification->delete();

        // Broadcast badge update
        $unreadCount = $this->calculateUnreadCount($request->user()->id, $channel);
        broadcast(new \App\Events\NotificationBadgeUpdated($request->user()->id, $unreadCount));

        return response()->json([
            'message' => 'Notification deleted.',
            'unread_count' => $unreadCount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // NOTIFICATION PREFERENCES API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/notifications/preferences
     * Get all notification preferences for the authenticated user.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure default preferences exist
        $this->notificationService->seedDefaultPreferences($user->id);

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->orderBy('notification_type')
            ->orderBy('channel')
            ->get()
            ->groupBy('notification_type');

        return response()->json([
            'data' => $preferences,
            'global_mute' => $this->notificationService->isGloballyMuted($user->id),
        ]);
    }

    /**
     * PATCH /api/v1/notifications/preferences
     * Bulk update notification preferences.
     * Body: { preferences: [{ type, channel, enabled, digest_mode?, quiet_start?, quiet_end? }] }
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'preferences' => 'required|array',
                'preferences.*.type' => 'required|string|max:80',
                'preferences.*.channel' => 'required|in:in_app,email,push,sms',
                'preferences.*.enabled' => 'required|boolean',
                'preferences.*.digest_mode' => 'nullable|in:instant,daily,weekly',
                'preferences.*.quiet_start' => 'nullable|date_format:H:i',
                'preferences.*.quiet_end' => 'nullable|date_format:H:i',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $updated = [];
        foreach ($validated['preferences'] as $pref) {
            $preference = NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $pref['type'],
                    'channel' => $pref['channel'],
                ],
                [
                    'enabled' => $pref['enabled'],
                    'digest_mode' => $pref['digest_mode'] ?? 'instant',
                    'quiet_start' => $pref['quiet_start'] ?? null,
                    'quiet_end' => $pref['quiet_end'] ?? null,
                ]
            );
            $updated[] = $preference;
        }

        return response()->json([
            'message' => 'Preferences updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * GET /api/v1/notifications/preferences/{type}
     * Get preferences for a specific notification type.
     */
    public function getPreferenceByType(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->where('notification_type', $type)
            ->get()
            ->keyBy('channel');

        if ($preferences->isEmpty()) {
            return response()->json([
                'message' => 'No preferences found for this notification type.',
            ], 404);
        }

        return response()->json(['data' => $preferences]);
    }

    /**
     * PATCH /api/v1/notifications/preferences/{type}
     * Update preferences for a single notification type.
     * Body: { channel, enabled, digest_mode?, quiet_start?, quiet_end? }
     */
    public function updatePreferenceByType(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'channel' => 'required|in:in_app,email,push,sms',
                'enabled' => 'required|boolean',
                'digest_mode' => 'nullable|in:instant,daily,weekly',
                'quiet_start' => 'nullable|date_format:H:i',
                'quiet_end' => 'nullable|date_format:H:i',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $preference = NotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_type' => $type,
                'channel' => $validated['channel'],
            ],
            [
                'enabled' => $validated['enabled'],
                'digest_mode' => $validated['digest_mode'] ?? 'instant',
                'quiet_start' => $validated['quiet_start'] ?? null,
                'quiet_end' => $validated['quiet_end'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Preference updated successfully.',
            'data' => $preference,
        ]);
    }

    /**
     * POST /api/v1/notifications/preferences/reset
     * Reset notification preferences to defaults.
     */
    public function resetPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete all custom preferences for the user
        NotificationPreference::where('user_id', $user->id)->delete();

        // Seed default preferences
        $this->notificationService->seedDefaultPreferences($user->id);

        $defaults = NotificationPreference::where('user_id', $user->id)
            ->orderBy('notification_type')
            ->orderBy('channel')
            ->get();

        return response()->json([
            'message' => 'Preferences reset to defaults.',
            'data' => $defaults,
        ]);
    }

    /**
     * POST /api/v1/notifications/mute
     * Toggle global mute for all notifications.
     * Body: { muted: boolean }
     */
    public function setGlobalMute(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'muted' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $this->notificationService->setGlobalMute($request->user()->id, $validated['muted']);

        return response()->json([
            'message' => $validated['muted'] ? 'Notifications muted.' : 'Notifications unmuted.',
            'muted' => $validated['muted'],
        ]);
    }

    /**
     * GET /api/v1/notifications/unread-count
     * Get the count of unread in-app notifications (respecting preferences).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $channel = $request->input('channel', 'in_app');
        $count = $this->calculateUnreadCount($user->id, $channel);

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calculate unread notification count for a user (filtered by channel and preferences).
     */
    private function calculateUnreadCount(int $userId, string $channel = 'in_app'): int
    {
        // Check global mute
        if ($this->notificationService->isGloballyMuted($userId)) {
            return 0;
        }

        // Get enabled notification types for this channel
        $enabledTypes = NotificationPreference::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->pluck('notification_type')
            ->toArray();

        return Notification::where('user_id', $userId)
            ->where('channel', $channel)
            ->whereIn('type', $enabledTypes)
            ->whereIn('status', ['sent', 'delivered'])
            ->count();
    }
}
