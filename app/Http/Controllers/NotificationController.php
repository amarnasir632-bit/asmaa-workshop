<?php

namespace App\Http\Controllers;

use App\Models\InternalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markRead(Request $request, InternalNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        if (! $notification->resolved_at && ! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->route('dashboard');
    }
}
