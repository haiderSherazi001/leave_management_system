<x-slot name="header">{{ __('My Leave Requests') }}</x-slot>

<div class="space-y-6">
    <x-card>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="leaveTypeFilter" value="Leave Type" />
                <select id="leaveTypeFilter" wire:model.live="leaveTypeFilter" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                    <option value="">All types</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="statusFilter" value="Status" />
                <select id="statusFilter" wire:model.live="statusFilter" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\LeaveRequestStatus::cases() as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>

            @if ($leaveTypeFilter !== null || $statusFilter !== '')
                <x-secondary-button wire:click="clearFilters">Clear</x-secondary-button>
            @endif
        </div>
    </x-card>

    <x-card padding="p-0">
    @if (count($requests) === 0)
        <p class="p-6 text-sm text-slate-500">
            {{ $leaveTypeFilter !== null || $statusFilter !== '' ? 'No leave requests match these filters.' : "You haven't submitted any leave requests yet." }}
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-3 px-6 font-medium">Leave Type</th>
                        <th class="py-3 px-6 font-medium">Dates</th>
                        <th class="py-3 px-6 font-medium">Days</th>
                        <th class="py-3 px-6 font-medium">Status</th>
                        <th class="py-3 px-6 font-medium">Approver</th>
                        <th class="py-3 px-6 font-medium">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($requests as $request)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-6 font-medium text-slate-900">{{ $request->leave_type_name }}</td>
                            <td class="py-3 px-6 text-slate-600">
                                {{ \Illuminate\Support\Carbon::parse($request->start_date)->format('M j, Y') }}
                                &ndash;
                                {{ \Illuminate\Support\Carbon::parse($request->end_date)->format('M j, Y') }}
                                @if ($request->is_half_day)
                                    <span class="text-slate-400">(half day)</span>
                                @endif
                            </td>
                            <td class="py-3 px-6 text-slate-600">{{ $request->total_days }}</td>
                            <td class="py-3 px-6">
                                <x-badge :color="match (true) {
                                    in_array($request->status, ['pending_manager', 'pending_hr']) => 'amber',
                                    $request->status === 'approved' => 'emerald',
                                    $request->status === 'rejected' => 'red',
                                    default => 'slate',
                                }">
                                    {{ \App\Enums\LeaveRequestStatus::from($request->status)->label() }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-6 text-slate-600">
                                @if ($request->hr_approver_name)
                                    {{ $request->approver_name }} &rarr; {{ $request->hr_approver_name }}
                                @else
                                    {{ $request->approver_name ?? '—' }}
                                @endif
                            </td>
                            <td class="py-3 px-6 text-slate-600">{{ $request->decision_note ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </x-card>
</div>
