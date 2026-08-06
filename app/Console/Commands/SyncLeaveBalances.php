<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\LeaveBalanceService;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLeaveBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:sync-balances {year? : Defaults to the current year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing leave balances for active employees and active leave types';

    public function handle(LeaveBalanceService $balances): int
    {
        $year = (int) ($this->argument('year') ?? date('Y'));
        $totalLeaveTypes = 0;

        // Run per-company, not once globally: with no session user, the
        // tenant scope on every query below is a no-op unless Tenant::set()
        // pins it to one company at a time — without this loop, "active
        // leave types" would mean every company's leave types mixed
        // together, and every balance would get provisioned for every
        // employee in every company.
        foreach (Company::all() as $company) {
            Tenant::set($company->id);

            $leaveTypeIds = DB::table('leave_types')->where('company_id', $company->id)->where('is_active', true)->pluck('id');

            foreach ($leaveTypeIds as $leaveTypeId) {
                $balances->provisionForLeaveType($leaveTypeId, $year);
            }

            $totalLeaveTypes += $leaveTypeIds->count();

            Tenant::clear();
        }

        $this->info("Leave balances synced for {$year} across {$totalLeaveTypes} active leave type(s).");

        return self::SUCCESS;
    }
}
