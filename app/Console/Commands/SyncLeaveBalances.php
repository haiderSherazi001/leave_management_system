<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeaveBalanceService;
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
        $leaveTypeIds = DB::table('leave_types')->where('is_active', true)->pluck('id');

        foreach ($leaveTypeIds as $leaveTypeId) {
            $balances->provisionForLeaveType($leaveTypeId, $year);
        }

        $this->info("Leave balances synced for {$year} across {$leaveTypeIds->count()} active leave type(s).");

        return self::SUCCESS;
    }
}
