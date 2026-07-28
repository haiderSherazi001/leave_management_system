<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_service_synthesizes_present_late_absent_and_on_leave_rows_for_working_days_only(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);

        $employee = User::factory()->create(['name' => 'Jane Employee']);
        $inactiveEmployee = User::factory()->inactive()->create(['name' => 'Old Employee']);
        $leaveType = LeaveType::factory()->create();

        $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
        $tuesday = $monday->addDay();
        $wednesday = $monday->addDays(2);
        $thursday = $monday->addDays(3);
        $friday = $monday->addDays(4);
        $saturday = $monday->addDays(5);
        $sunday = $monday->addDays(6);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => $monday->toDateString(),
            'status' => 'present',
            'check_in_at' => $monday->setTime(9, 0),
            'check_out_at' => $monday->setTime(17, 0),
        ]);
        Attendance::factory()->late()->create([
            'user_id' => $employee->id,
            'date' => $tuesday->toDateString(),
            'check_in_at' => $tuesday->setTime(9, 30),
            'check_out_at' => $tuesday->setTime(17, 0),
        ]);
        // Wednesday: deliberately no attendance row and no leave -> Absent.
        // approver_id/hr_approver_id are pinned to $employee (not left to
        // the factory default) because LeaveRequestFactory::approved()
        // otherwise spawns extra, unrelated active users as the approvers,
        // which would silently inflate the row count below.
        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $employee->id,
            'hr_approver_id' => $employee->id,
            'start_date' => $thursday->toDateString(),
            'end_date' => $friday->toDateString(),
        ]);

        // An inactive user's attendance must never appear in the export.
        Attendance::factory()->create([
            'user_id' => $inactiveEmployee->id,
            'date' => $monday->toDateString(),
            'status' => 'present',
        ]);

        $service = $this->app->make(AttendanceExportService::class);
        $rows = $service->rowsBetween($monday->toDateString(), $sunday->toDateString());

        // 5 working days (Mon-Fri) x 1 active employee. Sat/Sun produce no rows at all.
        $this->assertCount(5, $rows);
        $this->assertTrue($rows->every(fn (array $row) => $row['name'] === 'Jane Employee'));

        $byDate = $rows->keyBy('date');

        $this->assertSame('Present', $byDate[$monday->toDateString()]['status']);
        $this->assertSame('9:00 AM', $byDate[$monday->toDateString()]['check_in']);
        $this->assertSame(480, $byDate[$monday->toDateString()]['worked_minutes']);
        $this->assertSame('8h 0m', $byDate[$monday->toDateString()]['hours_worked']);

        $this->assertSame('Late', $byDate[$tuesday->toDateString()]['status']);
        $this->assertSame(450, $byDate[$tuesday->toDateString()]['worked_minutes']);
        $this->assertSame('7h 30m', $byDate[$tuesday->toDateString()]['hours_worked']);

        $this->assertSame('Absent', $byDate[$wednesday->toDateString()]['status']);
        $this->assertNull($byDate[$wednesday->toDateString()]['check_in']);
        $this->assertNull($byDate[$wednesday->toDateString()]['worked_minutes']);
        $this->assertSame('—', $byDate[$wednesday->toDateString()]['hours_worked']);

        $this->assertSame('On Leave', $byDate[$thursday->toDateString()]['status']);
        $this->assertSame('On Leave', $byDate[$friday->toDateString()]['status']);

        $this->assertFalse($rows->contains('date', $saturday->toDateString()));
        $this->assertFalse($rows->contains('date', $sunday->toDateString()));
    }

    public function test_summary_between_totals_hours_worked_across_the_range(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);

        $employee = User::factory()->create(['name' => 'Jane Employee']);

        $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
        $tuesday = $monday->addDay();

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => $monday->toDateString(),
            'status' => 'present',
            'check_in_at' => $monday->setTime(9, 0),
            'check_out_at' => $monday->setTime(17, 0),
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => $tuesday->toDateString(),
            'status' => 'present',
            'check_in_at' => $tuesday->setTime(9, 0),
            'check_out_at' => $tuesday->setTime(13, 30),
        ]);

        $service = $this->app->make(AttendanceExportService::class);
        $summary = $service->summaryBetween($monday->toDateString(), $tuesday->toDateString());

        // 8h Monday + 4.5h Tuesday = 12.5h total.
        $this->assertSame(12.5, $summary->firstWhere('user_id', $employee->id)['total_hours_worked']);
    }

    public function test_export_service_skips_holidays(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);
        User::factory()->create();

        $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
        $tuesday = $monday->addDay();

        Holiday::factory()->create(['date' => $tuesday->toDateString(), 'name' => 'Test Holiday']);

        $service = $this->app->make(AttendanceExportService::class);
        $rows = $service->rowsBetween($monday->toDateString(), $tuesday->toDateString());

        $this->assertCount(1, $rows);
        $this->assertSame($monday->toDateString(), $rows->first()['date']);
    }

    public function test_hr_can_download_the_attendance_export(): void
    {
        Excel::fake();

        $hr = User::factory()->hr()->create();

        $response = $this->actingAs($hr)->get(route('admin.attendance.export', [
            'start' => now()->startOfMonth()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        Excel::assertDownloaded(
            "attendance-{$this->monthStartString()}-to-{$this->todayString()}.xlsx",
            fn () => true,
        );
    }

    public function test_non_hr_users_cannot_download_the_attendance_export(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.attendance.export', [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->toDateString(),
            ]))->assertForbidden();
        }
    }

    public function test_export_validates_the_date_range(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('admin.attendance.export', ['start' => now()->toDateString(), 'end' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('end');

        $this->actingAs($hr)
            ->get(route('admin.attendance.export'))
            ->assertSessionHasErrors(['start', 'end']);
    }

    private function monthStartString(): string
    {
        return now()->startOfMonth()->toDateString();
    }

    private function todayString(): string
    {
        return now()->toDateString();
    }
}
