<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\MonthlyAttendanceReport;
use App\Models\User;
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

        $hrUsers = User::where('role', UserRole::Hr)->where('is_active', true)->get();

        if ($hrUsers->isEmpty()) {
            $this->warn('No active HR users to send the report to.');

            return self::SUCCESS;
        }

        foreach ($hrUsers as $hr) {
            Mail::to($hr)->send(new MonthlyAttendanceReport($start, $end));
        }

        $this->info("Monthly attendance report ({$start} to {$end}) sent to {$hrUsers->count()} HR user(s).");

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
