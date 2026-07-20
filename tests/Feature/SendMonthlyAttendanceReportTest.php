<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\AttendanceExport;
use App\Mail\MonthlyAttendanceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SendMonthlyAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_sends_the_report_to_active_hr_users_only(): void
    {
        Mail::fake();

        $activeHrA = User::factory()->hr()->create();
        $activeHrB = User::factory()->hr()->create();
        $inactiveHr = User::factory()->hr()->inactive()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create();

        $this->artisan('report:monthly-attendance')->assertSuccessful();

        Mail::assertSent(MonthlyAttendanceReport::class, fn (MonthlyAttendanceReport $mail) => $mail->hasTo($activeHrA->email));
        Mail::assertSent(MonthlyAttendanceReport::class, fn (MonthlyAttendanceReport $mail) => $mail->hasTo($activeHrB->email));
        Mail::assertNotSent(MonthlyAttendanceReport::class, fn (MonthlyAttendanceReport $mail) => $mail->hasTo($inactiveHr->email));
        Mail::assertNotSent(MonthlyAttendanceReport::class, fn (MonthlyAttendanceReport $mail) => $mail->hasTo($manager->email));
        Mail::assertNotSent(MonthlyAttendanceReport::class, fn (MonthlyAttendanceReport $mail) => $mail->hasTo($employee->email));
        Mail::assertSentCount(2);
    }

    public function test_command_does_not_error_when_there_are_no_active_hr_users(): void
    {
        Mail::fake();

        $this->artisan('report:monthly-attendance')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_report_covers_the_previous_calendar_month(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        User::factory()->hr()->create();

        $this->artisan('report:monthly-attendance')->assertSuccessful();

        Mail::assertSent(MonthlyAttendanceReport::class, function (MonthlyAttendanceReport $mail) {
            return $mail->envelope()->subject === 'Monthly Attendance Report — July 2026';
        });
    }

    /**
     * Regression guard: subMonthNoOverflow() must not let a month with more
     * days than the previous one roll forward past it (e.g. Mar 31 minus a
     * month should land in February, not overflow into March again).
     */
    public function test_previous_month_calculation_does_not_overflow_on_a_31st(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-03-31'));

        User::factory()->hr()->create();

        $this->artisan('report:monthly-attendance')->assertSuccessful();

        Mail::assertSent(MonthlyAttendanceReport::class, function (MonthlyAttendanceReport $mail) {
            return $mail->envelope()->subject === 'Monthly Attendance Report — February 2026';
        });
    }

    /**
     * Attachment::fromData()'s data closure is lazy — Mail::fake() plus
     * calling $mail->attachments() (below) never actually invokes it, so
     * this exercises Excel::raw() directly against the exact export/format
     * the Mailable uses, to catch failures the closure-deferred tests can't
     * (this caught a real bug during development: 'Xlsx' vs a facade
     * constant that doesn't exist on the Facade class).
     */
    public function test_the_excel_attachment_can_actually_be_generated(): void
    {
        $export = app(AttendanceExport::class, ['start' => '2026-07-01', 'end' => '2026-07-31']);

        $contents = Excel::raw($export, 'Xlsx');

        $this->assertNotEmpty($contents);
    }

    public function test_report_has_a_single_excel_attachment_with_the_expected_filename(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        User::factory()->hr()->create();

        $this->artisan('report:monthly-attendance')->assertSuccessful();

        Mail::assertSent(MonthlyAttendanceReport::class, function (MonthlyAttendanceReport $mail) {
            $attachments = $mail->attachments();

            return count($attachments) === 1
                && $attachments[0]->as === 'attendance-2026-07-01-to-2026-07-31.xlsx';
        });
    }
}
