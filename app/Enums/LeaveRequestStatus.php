<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveRequestStatus: string
{
    case PendingManager = 'pending_manager';
    case PendingHR = 'pending_hr';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingManager => 'Pending Manager Approval',
            self::PendingHR => 'Pending HR Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
