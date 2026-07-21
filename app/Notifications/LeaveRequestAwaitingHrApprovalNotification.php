<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestAwaitingHrApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $leaveRequestId,
        private readonly string $employeeName,
        private readonly string $leaveTypeName,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly float $totalDays,
        private readonly string $managerName,
        private readonly bool $isSelfSubmitted = false,
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
            ->subject("Leave request from {$this->employeeName} awaiting your final approval")
            ->greeting("Hi {$notifiable->name},");

        // A Manager applying for their own leave has no separate approver to
        // forward it — the system does that automatically. Saying "X has
        // approved X's request" in that case just repeats one name and reads
        // like a bug, so this case gets its own, clearer wording.
        if ($this->isSelfSubmitted) {
            $message->line("{$this->employeeName} (a Manager) has submitted their own {$this->leaveTypeName} leave request. It needs your final sign-off since managers can't approve their own leave.");
        } else {
            $message->line("{$this->managerName} has approved {$this->employeeName}'s {$this->leaveTypeName} leave request and it now needs your final sign-off.");
        }

        return $message
            ->line("Dates: {$this->startDate} to {$this->endDate} (".number_format($this->totalDays, 1).' day(s)).')
            ->action('Review Request', route('admin.leave-approvals'))
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
            'manager_name' => $this->managerName,
            'is_self_submitted' => $this->isSelfSubmitted,
        ];
    }
}
