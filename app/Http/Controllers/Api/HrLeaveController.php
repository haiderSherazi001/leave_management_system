<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR-role mobile endpoints — the final approval stage, mirroring
 * ManagerLeaveController exactly but company-wide rather than team-scoped
 * (pendingForHr()/historyForHr() aren't tied to any one HR user). approve()/
 * reject() catch AuthorizationException explicitly to match the exact
 * wording LeaveApprovals.php's web UI already uses for the same failure.
 */
final class HrLeaveController extends Controller
{
    public function pending(Request $request, LeaveRequestService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        return response()->json(['data' => $service->pendingForHr()]);
    }

    /**
     * Paginated, so this returns the paginator's own natural shape directly
     * (current_page/data/last_page/etc.) rather than double-wrapping it in
     * another "data" key - matches how the web history tab already reads it,
     * and how ManagerLeaveController::history() already does this too.
     */
    public function history(Request $request, LeaveRequestService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        return response()->json($service->historyForHr());
    }

    public function approve(Request $request, int $leaveRequestId, LeaveRequestService $service): JsonResponse
    {
        $validated = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        try {
            $service->approveByHr($leaveRequestId, $request->user(), $validated['note'] ?? null);
        } catch (AuthorizationException) {
            abort(403, 'You are not authorized to act on this leave request.');
        }

        return response()->json(['data' => ['message' => 'Request approved.']]);
    }

    public function reject(Request $request, int $leaveRequestId, LeaveRequestService $service): JsonResponse
    {
        $validated = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        try {
            $service->reject($leaveRequestId, $request->user(), $validated['note'] ?? null);
        } catch (AuthorizationException) {
            abort(403, 'You are not authorized to act on this leave request.');
        }

        return response()->json(['data' => ['message' => 'Request rejected.']]);
    }
}
