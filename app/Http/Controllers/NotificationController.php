<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()
            ->where('type', 'App\Notifications\GasoilSeuilAtteint')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = $user->unreadNotifications()
            ->where('type', 'App\Notifications\GasoilSeuilAtteint')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request)
    {
        $user = $request->user();
        $notification = $user->notifications()->find($request->id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications()
            ->where('type', 'App\Notifications\GasoilSeuilAtteint')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    public function deleteAll(Request $request)
    {
        $user = $request->user();

        $user->notifications()
            ->where('type', 'App\Notifications\GasoilSeuilAtteint')
            ->delete();

        return response()->json([
            'message' => 'Toutes les notifications ont été supprimées'
        ]);
    }
}
