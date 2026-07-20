<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AttendanceExportController extends Controller
{
    /**
     * No role-based route middleware exists anywhere in this app — every
     * admin page enforces access in its own mount()/action, so this
     * matches that same pattern rather than introducing a new one.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(Auth::user()->isHr(), 403);

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $filename = "attendance-{$validated['start']}-to-{$validated['end']}.xlsx";

        return Excel::download(
            app(AttendanceExport::class, ['start' => $validated['start'], 'end' => $validated['end']]),
            $filename,
        );
    }
}
