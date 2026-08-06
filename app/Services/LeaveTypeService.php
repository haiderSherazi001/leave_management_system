<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

final class LeaveTypeService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * @return array<int, object>
     */
    public function list(?string $search = null, ?string $status = null): array
    {
        return DB::table('leave_types')
            ->where('company_id', Tenant::id())
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Active leave types only — the exact query the web apply-for-leave
     * form (RequestForm) already inlines for its dropdown, promoted to a
     * service method now that the mobile API needs the same list.
     *
     * @return array<int, object>
     */
    public function activeList(): array
    {
        return DB::table('leave_types')
            ->where('company_id', Tenant::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function create(
        string $name,
        string $code,
        int $yearlyAllocationDays,
        bool $carryForwardEnabled,
        ?int $carryForwardMaxDays,
        ?string $description,
    ): int {
        $leaveTypeId = DB::table('leave_types')->insertGetId([
            'company_id' => Tenant::id(),
            'name' => $name,
            'code' => $code,
            'yearly_allocation_days' => $yearlyAllocationDays,
            'carry_forward_enabled' => $carryForwardEnabled,
            'carry_forward_max_days' => $carryForwardEnabled ? $carryForwardMaxDays : null,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->balances->provisionForLeaveType($leaveTypeId, (int) date('Y'));

        return $leaveTypeId;
    }

    public function update(
        int $leaveTypeId,
        string $name,
        string $code,
        int $yearlyAllocationDays,
        bool $carryForwardEnabled,
        ?int $carryForwardMaxDays,
        ?string $description,
    ): void {
        DB::table('leave_types')
            ->where('id', $leaveTypeId)
            ->where('company_id', Tenant::id())
            ->update([
                'name' => $name,
                'code' => $code,
                'yearly_allocation_days' => $yearlyAllocationDays,
                'carry_forward_enabled' => $carryForwardEnabled,
                'carry_forward_max_days' => $carryForwardEnabled ? $carryForwardMaxDays : null,
                'description' => $description,
                'updated_at' => now(),
            ]);
    }

    public function setActive(int $leaveTypeId, bool $active): void
    {
        DB::table('leave_types')
            ->where('id', $leaveTypeId)
            ->where('company_id', Tenant::id())
            ->update(['is_active' => $active]);

        if ($active) {
            $this->balances->provisionForLeaveType($leaveTypeId, (int) date('Y'));
        }
    }
}
