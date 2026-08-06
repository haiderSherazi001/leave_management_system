<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Ambient current-company resolver. For web/Livewire requests this needs no
 * wiring at all — id() falls through to Auth::user()->company_id, the same
 * ambient-resolution pattern this app already uses everywhere (e.g.
 * Auth::user()->isHr()). set()/clear() exist only for the contexts with no
 * single authenticated session to read from: the Sanctum-guarded mobile API
 * (see SetTenantFromApiUser) and console commands that must process every
 * company in a loop (SendMonthlyAttendanceReport, GeneratePayrollToken).
 */
final class Tenant
{
    private static ?int $override = null;

    private static bool $isOverridden = false;

    public static function id(): ?int
    {
        return self::$isOverridden ? self::$override : Auth::user()?->company_id;
    }

    public static function set(?int $companyId): void
    {
        self::$override = $companyId;
        self::$isOverridden = true;
    }

    public static function clear(): void
    {
        self::$override = null;
        self::$isOverridden = false;
    }
}
