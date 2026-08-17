<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxNotification;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        Notifier::dispatchDueReminders();

        $limit = min(max((int) $request->integer('limit', 20), 1), 50);
        $rows = InboxNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'unread_count' => InboxNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
            'data' => $rows->map(fn (InboxNotification $row) => $this->serialize($row))->values(),
        ]);
    }

    public function markRead(Request $request, InboxNotification $notification): JsonResponse
    {
        $this->authorizeOwner($request, $notification);
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $this->serialize($notification->fresh()),
            'unread_count' => $this->unreadCount($request),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        InboxNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    private function authorizeOwner(Request $request, InboxNotification $notification): void
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function unreadCount(Request $request): int
    {
        return InboxNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(InboxNotification $row): array
    {
        return [
            'id' => (string) $row->id,
            'type' => $row->type,
            'title' => $row->title,
            'body' => $row->body,
            'href' => $row->href,
            'read' => (bool) $row->read_at,
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ];
    }
}
