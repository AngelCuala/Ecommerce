<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = UserNotification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        $unreadCount = UserNotification::where('user_id', auth()->id())
            ->unread()
            ->count();

        return view('admin.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
