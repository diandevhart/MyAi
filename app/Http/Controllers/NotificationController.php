<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class NotificationController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();

        $query = $user
            ->notifications()
            ->latest();

        $unreadOnly = $request->boolean('unread');
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $type = $request->string('type')->toString();
        if ($type !== '') {
            $query->where('data->type', $type);
        }

        $notifications = $query
            ->paginate(30);

        $typeOptions = $user->notifications()
            ->latest()
            ->limit(300)
            ->get(['data'])
            ->pluck('data.type')
            ->filter()
            ->unique()
            ->values();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'filters' => [
                'unread' => $unreadOnly,
                'type' => $type,
            ],
            'typeOptions' => $typeOptions,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(string $id): JsonResponse
    {
        Auth::user()->notifications()->findOrFail($id)->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }
}
