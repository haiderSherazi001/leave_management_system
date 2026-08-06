@php
    $data = $notification->data;

    [$message, $link] = match ($notification->type) {
        \App\Notifications\NewLeaveRequestNotification::class => [
            "{$data['employee_name']} requested {$data['leave_type_name']} leave",
            route('leave.approvals'),
        ],
        \App\Notifications\LeaveRequestAwaitingHrApprovalNotification::class => [
            "{$data['employee_name']}'s {$data['leave_type_name']} request needs your final approval",
            route('admin.leave-approvals'),
        ],
        \App\Notifications\LeaveRequestStatusNotification::class => [
            "Your {$data['leave_type_name']} request was {$data['status']}",
            route('leave.my-requests'),
        ],
        default => ['You have a new notification', null],
    };
@endphp

<a
    href="{{ $link ?? '#' }}"
    wire:click="markAsRead('{{ $notification->id }}')"
    @class([
        'block px-4 py-3 text-sm border-b border-slate-100 last:border-0 hover:bg-slate-50 transition',
        'bg-teal-50/60' => $notification->read_at === null,
    ])
>
    <p class="text-slate-800">{{ $message }}</p>
    <p class="mt-0.5 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
</a>
