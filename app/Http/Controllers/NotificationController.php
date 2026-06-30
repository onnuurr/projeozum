<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Oturum sahibinin DB bildirimleri (AppLayout çanını besler).
 */
class NotificationController extends Controller
{
    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->whereKey($id)->first()?->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back();
    }
}
