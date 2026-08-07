<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Bell icon + dropdown embedded in the main nav for authenticated users.
 * Reads from the standard Laravel database notifications table.
 */
class NotificationBell extends Component
{
    public function markAsRead(string $notificationId): void
    {
        auth()->user()->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->latest()->limit(10)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.notification-bell');
    }
}
