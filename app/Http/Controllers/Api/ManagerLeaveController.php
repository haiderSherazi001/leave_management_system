<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manager-role mobile endpoints. approve()/reject() catch AuthorizationException
 * explicitly to match the exact wording ApprovalQueue.php's web UI already
 * uses for the same failure, rather than Laravel's generic default message.
 */
final class ManagerLeaveController extends Controller
{
    public function pending(Request $request, LeaveRequestService $service): JsonResponse
    {
        abort_unless($request->user()->isManager(), 403);

        return response()->json(['data' => $service->pendingForApprover((int) $request->user()->id)]);
    }

    /**
     * Paginated, so this returns the paginator's own natural shape directly
     * (current_page/data/last_page/etc.) rather than double-wrapping it in
     * another "data" key - matches how the web history tab already reads it.
     */
    public function history(Request $request, LeaveRequestService $service): JsonResponse
    {
        abort_unless($request->user()->isManager(), 403);

        return response()->json($service->historyForApprover((int) $request->user()->id));
    }

    public function approve(Request $request, int $leaveRequestId, LeaveRequestService $service): JsonResponse
    {
        $validated = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        try {
            $service->approve($leaveRequestId, $request->user(), $validated['note'] ?? null);
        } catch (AuthorizationException) {
            abort(403, 'You are not authorized to act on this leave request.');
        }

        return response()->json(['data' => ['message' => 'Request forwarded to HR for final approval.']]);
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
