<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 10;

        $user = $request->user();

        $paginator = $user->notifications()
            ->select('notifications.id', 'notifications.title', 'notifications.message', 'notifications.type', 'notifications.created_at')
            ->withPivot('read_at')
            ->orderByDesc('notifications.created_at')
            ->paginate($perPage);

        $notifications = collect($paginator->items())
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->message,
                    'type' => $notification->type,
                    'read' => !is_null($notification->pivot->read_at),
                    'read_at' => optional($notification->pivot->read_at)?->toDateTimeString(),
                    'time' => $notification->created_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->notifications()->wherePivotNull('read_at')->count(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function markRead(Request $request, int $notificationId)
    {
        $user = $request->user();

        $notification = $user->notifications()
            ->where('notifications.id', $notificationId)
            ->firstOrFail();

        if (is_null($notification->pivot->read_at)) {
            $user->notifications()->updateExistingPivot($notificationId, [
                'read_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $user->notifications()->wherePivotNull('read_at')->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();

        DB::table('notification_user')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }
}