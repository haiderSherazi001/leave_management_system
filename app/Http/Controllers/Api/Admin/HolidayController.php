<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\HolidayService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HR-only mobile admin API for holiday management - mirrors
 * App\Livewire\Admin\Holidays::rules() exactly. Unlike the other admin
 * sections, holidays have no is_active/deactivate concept on the web
 * screen - delete() there is a hard delete, so this mirrors that with a
 * real DELETE endpoint rather than a toggle-active one.
 */
final class HolidayController extends Controller
{
    public function index(Request $request, HolidayService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        return response()->json(['data' => $service->list()]);
    }

    public function store(Request $request, HolidayService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateHoliday($request, null);

        $id = $service->create($validated['date'], $validated['name']);

        return response()->json(['data' => ['id' => $id]]);
    }

    public function update(Request $request, int $id, HolidayService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateHoliday($request, $id);

        $service->update($id, $validated['date'], $validated['name']);

        return response()->json(['data' => ['message' => 'Holiday updated.']]);
    }

    public function destroy(Request $request, int $id, HolidayService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $service->delete($id);

        return response()->json(['data' => ['message' => 'Holiday deleted.']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateHoliday(Request $request, ?int $editingId): array
    {
        return $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->where('company_id', Tenant::id())->ignore($editingId)],
            'name' => ['required', 'string', 'max:150'],
        ]);
    }
}
