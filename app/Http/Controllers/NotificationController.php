<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function panel(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadCount = $user->unreadNotifications()->count();
        $recent = $user->notifications()->take(8)->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? 'general',
                'title' => $notification->data['title'] ?? __('Notification'),
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? route('dashboard'),
                'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                'created_at_human' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $recent,
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->back()->with('status', __('Notification marked as read.'));
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->back()->with('status', __('All notifications marked as read.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->back()->with('status', __('Notification removed.'));
    }
}
