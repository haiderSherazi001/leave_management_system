<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeaveTypeService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HR-only mobile admin API for leave type management - mirrors
 * App\Livewire\Admin\LeaveTypes::rules() exactly. No related-entity option
 * data needed (unlike Employees/Departments), so list() can be returned
 * directly - its query already selects every leave_types column (no
 * sensitive data on this table, unlike the users table's password hash).
 */
final class LeaveTypeController extends Controller
{
    public function index(Request $request, LeaveTypeService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        return response()->json(['data' => $service->list()]);
    }

    public function store(Request $request, LeaveTypeService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateLeaveType($request, null);

        $id = $service->create(
            name: $validated['name'],
            code: $validated['code'],
            yearlyAllocationDays: $validated['yearly_allocation_days'],
            carryForwardEnabled: $validated['carry_forward_enabled'] ?? false,
            carryForwardMaxDays: $validated['carry_forward_max_days'] ?? null,
            description: ($validated['description'] ?? '') !== '' ? $validated['description'] : null,
        );

        return response()->json(['data' => ['id' => $id]]);
    }

    public function update(Request $request, int $id, LeaveTypeService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateLeaveType($request, $id);

        $service->update(
            leaveTypeId: $id,
            name: $validated['name'],
            code: $validated['code'],
            yearlyAllocationDays: $validated['yearly_allocation_days'],
            carryForwardEnabled: $validated['carry_forward_enabled'] ?? false,
            carryForwardMaxDays: $validated['carry_forward_max_days'] ?? null,
            description: ($validated['description'] ?? '') !== '' ? $validated['description'] : null,
        );

        return response()->json(['data' => ['message' => 'Leave type updated.']]);
    }

    public function toggleActive(Request $request, int $id, LeaveTypeService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $leaveType = DB::table('leave_types')->where('company_id', Tenant::id())->where('id', $id)->first();
        abort_if($leaveType === null, 404);

        $service->setActive($id, ! (bool) $leaveType->is_active);

        return response()->json(['data' => ['message' => $leaveType->is_active ? 'Leave type deactivated.' : 'Leave type reactivated.']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLeaveType(Request $request, ?int $editingId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->where('company_id', Tenant::id())->ignore($editingId)],
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_types', 'code')->where('company_id', Tenant::id())->ignore($editingId)],
            'yearly_allocation_days' => ['required', 'integer', 'min:0', 'max:365'],
            'carry_forward_enabled' => ['boolean'],
            'carry_forward_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
