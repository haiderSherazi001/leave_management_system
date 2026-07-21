<x-slot name="header">{{ __('HR Leave Approvals') }}</x-slot>

<x-card>
    <h3 class="text-lg font-semibold text-slate-900 mb-1">Awaiting Final Approval</h3>
    <p class="text-sm text-slate-500 mb-4">Requests already approved by a manager, awaiting your final sign-off.</p>

    @if ($errorMessage)
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

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
</x-card>
