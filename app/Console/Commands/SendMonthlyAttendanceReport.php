<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\MonthlyAttendanceReport;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMonthlyAttendanceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:monthly-attendance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Email the previous month's attendance report to all active HR users";

    public function handle(): int
    {
        [$start, $end] = $this->previousMonthRange();
        $totalSent = 0;

        // One independent report per company: with no session user, the
        // tenant scope on User/AttendanceExportService's Eloquent queries is
        // a no-op unless Tenant::set() pins it to one company at a time —
        // without this loop, every company's HR would be emailed a report
        // mixing every company's attendance together.
        foreach (Company::all() as $company) {
            Tenant::set($company->id);

            // User carries no automatic tenant scope (see the model's own
            // docblock), so it's filtered explicitly here.
            $hrUsers = User::where('company_id', $company->id)->where('role', UserRole::Hr)->where('is_active', true)->get();

            foreach ($hrUsers as $hr) {
                Mail::to($hr)->send(new MonthlyAttendanceReport($start, $end));
            }

            $totalSent += $hrUsers->count();

            Tenant::clear();
        }

        $this->info("Monthly attendance report ({$start} to {$end}) sent to {$totalSent} HR user(s) across ".Company::count().' company(ies).');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function previousMonthRange(): array
    {
        $previousMonth = CarbonImmutable::now()->subMonthNoOverflow();

        return [
            $previousMonth->startOfMonth()->toDateString(),
            $previousMonth->endOfMonth()->toDateString(),
        ];
    }
}
