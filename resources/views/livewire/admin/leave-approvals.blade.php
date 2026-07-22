<x-slot name="header">{{ __('HR Leave Approvals') }}</x-slot>

<x-card>
    <div class="mb-4 flex gap-1 border-b border-slate-200">
        <button type="button" wire:click="setTab('pending')"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                {{ $tab === 'pending' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Pending
        </button>
        <button type="button" wire:click="setTab('history')"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                {{ $tab === 'history' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            History
        </button>
    </div>

    @if ($errorMessage)
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($tab === 'pending')
        <h3 class="text-lg font-semibold text-slate-900 mb-1">Awaiting Final Approval</h3>
        <p class="text-sm text-slate-500 mb-4">Requests already approved by a manager, awaiting your final sign-off.</p>

        @if (count($requests) === 0)
            <p class="text-sm text-slate-500">No leave requests are awaiting HR approval.</p>
        @else
            <div class="space-y-4">
                @foreach ($requests as $request)
                    <div class="border border-slate-200 rounded-lg p-4" wire:key="request-{{ $request->id }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-slate-900">{{ $request->employee_name }}</p>
                                <p class="text-sm text-slate-500">
                                    {{ $request->leave_type_name }} &middot;
                                    {{ \Illuminate\Support\Carbon::parse($request->start_date)->format('M j, Y') }}
                                    &ndash;
                                    {{ \Illuminate\Support\Carbon::parse($request->end_date)->format('M j, Y') }}
                                    ({{ $request->total_days }} day{{ $request->total_days == 1 ? '' : 's' }})
                                </p>
                                <p class="text-sm text-slate-600 mt-1">{{ $request->reason }}</p>
                                <p class="text-xs text-slate-400 mt-1">
                                    Approved by manager: {{ $request->manager_name ?? '—' }}
                                    @if ($request->manager_note)
                                        &middot; "{{ $request->manager_note }}"
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-text-input
                                type="text"
                                wire:model="notes.{{ $request->id }}"
                                placeholder="Optional note"
                                class="block w-full text-sm"
                            />
                        </div>

                        <div class="mt-3 flex gap-2">
                            <x-primary-button wire:click="approve({{ $request->id }})" class="!bg-emerald-600 hover:!bg-emerald-500">
                                Approve
                            </x-primary-button>
                            <x-danger-button wire:click="reject({{ $request->id }})">
                                Reject
                            </x-danger-button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <h3 class="text-lg font-semibold text-slate-900 mb-1">Decided Requests</h3>
        <p class="text-sm text-slate-500 mb-4">Every leave request the company has finalized, approved or rejected.</p>

        @if ($history->isEmpty())
            <p class="text-sm text-slate-500">No decided requests yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-3 px-4 font-medium">Employee</th>
                            <th class="py-3 px-4 font-medium">Leave Type</th>
                            <th class="py-3 px-4 font-medium">Dates</th>
                            <th class="py-3 px-4 font-medium">Days</th>
                            <th class="py-3 px-4 font-medium">Status</th>
                            <th class="py-3 px-4 font-medium">Manager</th>
                            <th class="py-3 px-4 font-medium">HR Approver</th>
                            <th class="py-3 px-4 font-medium">Decided</th>
                            <th class="py-3 px-4 font-medium">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $request)
                            <tr wire:key="history-{{ $request->id }}">
                                <td class="py-3 px-4 font-medium text-slate-900">{{ $request->employee_name }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ $request->leave_type_name }}</td>
                                <td class="py-3 px-4 text-slate-600">
                                    {{ \Illuminate\Support\Carbon::parse($request->start_date)->format('M j, Y') }}
                                    &ndash;
                                    {{ \Illuminate\Support\Carbon::parse($request->end_date)->format('M j, Y') }}
                                </td>
                                <td class="py-3 px-4 text-slate-600">{{ $request->total_days }}</td>
                                <td class="py-3 px-4">
                                    <x-badge :color="$request->status === 'approved' ? 'emerald' : 'red'">
                                        {{ \App\Enums\LeaveRequestStatus::from($request->status)->label() }}
                                    </x-badge>
                                </td>
                                <td class="py-3 px-4 text-slate-600">{{ $request->manager_name ?? '—' }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ $request->hr_approver_name ?? '—' }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ \Illuminate\Support\Carbon::parse($request->decided_at)->format('M j, Y g:ia') }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ $request->decision_note ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $history->links() }}</div>
        @endif
        @endif
</x-card>
