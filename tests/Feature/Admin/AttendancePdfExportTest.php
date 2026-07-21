<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePdfExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * barryvdh/laravel-dompdf has no Excel::fake()-style test double, so
     * this hits the real DomPDF renderer and checks the actual response —
     * status, headers, and the PDF file-signature magic bytes — rather
     * than mocking anything away.
     */
    public function test_hr_can_download_the_attendance_pdf_export(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['name' => 'Jane Employee']);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->toDateString();

        $response = $this->actingAs($hr)->get(route('admin.attendance.export-pdf', [
            'start' => $start,
            'end' => $end,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            "attendance-{$start}-to-{$end}.pdf",
            $response->headers->get('content-disposition'),
        );

        // A genuine PDF file starts with this signature — confirms a real,
        // non-empty document came back, not an error page or blank output.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_non_hr_users_cannot_download_the_attendance_pdf_export(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.attendance.export-pdf', [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->toDateString(),
            ]))->assertForbidden();
        }
    }

    public function test_pdf_export_validates_the_date_range(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('admin.attendance.export-pdf', ['start' => now()->toDateString(), 'end' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('end');

        $this->actingAs($hr)
            ->get(route('admin.attendance.export-pdf'))
            ->assertSessionHasErrors(['start', 'end']);
    }
}
