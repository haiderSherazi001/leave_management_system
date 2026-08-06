<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Small, always-embedded-in-the-header component (see layouts/app.blade.php)
 * showing unread count + a dropdown of recent notifications. The actual
 * recording of notifications (NewLeaveRequestNotification etc.) already
 * existed via the 'database' channel before this component did — this is
 * purely the first UI ever built to surface them.
 */
class NotificationBell extends Component
{
    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        $notification?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        return view('livewire.notification-bell', [
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
            'notifications' => Auth::user()->notifications()->latest()->limit(8)->get(),
        ]);
    }
}
