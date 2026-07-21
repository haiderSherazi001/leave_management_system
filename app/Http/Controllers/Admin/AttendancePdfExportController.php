<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AttendanceExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class AttendancePdfExportController extends Controller
{
    /**
     * Same abort_unless(...) pattern as AttendanceExportController (the
     * Excel export) and every admin Livewire page's mount() — no
     * role-based route middleware exists anywhere in this app.
     */
    public function __invoke(Request $request, AttendanceExportService $service): Response
    {
        abort_unless(Auth::user()->isHr(), 403);

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $rows = $service->rowsBetween($validated['start'], $validated['end']);
        $filename = "attendance-{$validated['start']}-to-{$validated['end']}.pdf";

        return Pdf::loadView('pdf.attendance-report', [
            'rows' => $rows,
            'start' => $validated['start'],
            'end' => $validated['end'],
        ])->download($filename);
    }
}
