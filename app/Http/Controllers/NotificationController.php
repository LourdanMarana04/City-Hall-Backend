<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getUnreadNotifications()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notifications = Notification::where('is_broadcast', true)
            ->whereDoesntHave('readBy', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'created_by' => $notification->creator?->name,
                    'created_at' => $notification->created_at,
                    'time_ago' => $this->getTimeAgo($notification->created_at),
                ];
            });

        return response()->json([
            'unread_count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    public function getAllNotifications()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notifications = Notification::where('is_broadcast', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) use ($user) {
                $isRead = $notification->isReadBy($user->id);
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'created_by' => $notification->creator?->name,
                    'created_at' => $notification->created_at,
                    'time_ago' => $this->getTimeAgo($notification->created_at),
                    'is_read' => $isRead,
                ];
            });

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notification = Notification::find($request->notification_id);
        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        NotificationRead::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $unreadNotifications = Notification::where('is_broadcast', true)
            ->whereDoesntHave('readBy', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->pluck('id');

        foreach ($unreadNotifications as $notificationId) {
            NotificationRead::updateOrCreate(
                [
                    'notification_id' => $notificationId,
                    'user_id' => $user->id,
                ],
                [
                    'read_at' => now(),
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    public function getCreatedNotifications()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notifications = Notification::where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'created_at' => $notification->created_at,
                    'time_ago' => $this->getTimeAgo($notification->created_at),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function createNotification(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:info,success,warning,error',
        ]);

        $notification = Notification::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'info',
            'created_by' => $user->id,
            'is_broadcast' => true,
        ]);

        return response()->json([
            'success' => true,
            'notification' => $notification,
        ]);
    }

    private function getTimeAgo($date)
    {
        $seconds = now()->diffInSeconds($date);

        if ($seconds < 60) {
            return 'just now';
        } elseif ($seconds < 3600) {
            $minutes = intval($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($seconds < 86400) {
            $hours = intval($seconds / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = intval($seconds / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}
