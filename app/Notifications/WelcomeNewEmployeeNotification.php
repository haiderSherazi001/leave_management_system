<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNewEmployeeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $setPasswordUrl,
    ) {}

    /**
     * Mail only, unlike this app's other notifications (which also use
     * 'database') - a brand-new account has no way to log in and see a
     * database notification before it's used this very link, so a
     * database row here would never actually get read.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to LeaveDesk — set up your account')
            ->greeting("Hi {$notifiable->name},")
            ->line('An account has been created for you on LeaveDesk.')
            ->line('Click below to set your password and log in.')
            ->action('Set Your Password', $this->setPasswordUrl)
            ->line('If you weren\'t expecting this, you can safely ignore this email.');
    }
}
