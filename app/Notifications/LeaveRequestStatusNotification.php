<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\LeaveRequestStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $leaveRequestId,
        private readonly string $leaveTypeName,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly LeaveRequestStatus $status,
        private readonly string $approverName,
        private readonly ?string $decisionNote,
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
        $message = (new MailMessage)
            ->subject("Your {$this->leaveTypeName} leave request was {$this->status->value}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$this->leaveTypeName} leave request for {$this->startDate} to {$this->endDate} has been {$this->status->value} by {$this->approverName}.");

        if ($this->decisionNote !== null && $this->decisionNote !== '') {
            $message->line("Note: {$this->decisionNote}");
        }

        return $message->action('View My Requests', route('leave.my-requests'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequestId,
            'leave_type_name' => $this->leaveTypeName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status->value,
            'approver_name' => $this->approverName,
            'decision_note' => $this->decisionNote,
        ];
    }
}
