<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $leaveRequestId,
        private readonly string $employeeName,
        private readonly string $leaveTypeName,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly float $totalDays,
        private readonly string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New leave request from {$this->employeeName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->employeeName} has requested {$this->leaveTypeName} leave.")
            ->line("Dates: {$this->startDate} to {$this->endDate} (".number_format($this->totalDays, 1).' day(s)).')
            ->line("Reason: {$this->reason}")
            ->action('Review Request', route('leave.approvals'))
            ->line('Please review this request at your earliest convenience.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequestId,
            'employee_name' => $this->employeeName,
            'leave_type_name' => $this->leaveTypeName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_days' => $this->totalDays,
            'reason' => $this->reason,
        ];
    }
}
