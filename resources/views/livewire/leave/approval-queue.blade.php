<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pending Approvals') }}</h2>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Pending Approvals</h3>

    @if ($errorMessage)
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    @if (count($requests) === 0)
        <p class="text-sm text-gray-500">No pending leave requests.</p>
    @else
        <div class="space-y-4">
            @foreach ($requests as $request)
                <div class="border border-gray-200 rounded-md p-4" wire:key="request-{{ $request->id }}">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-900">{{ $request->employee_name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $request->leave_type_name }} &middot;
                                {{ \Illuminate\Support\Carbon::parse($request->start_date)->format('M j, Y') }}
                                &ndash;
                                {{ \Illuminate\Support\Carbon::parse($request->end_date)->format('M j, Y') }}
                                ({{ $request->total_days }} day{{ $request->total_days == 1 ? '' : 's' }})
                            </p>
                            <p class="text-sm text-gray-600 mt-1">{{ $request->reason }}</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <input
                            type="text"
                            wire:model="notes.{{ $request->id }}"
                            placeholder="Optional note"
                            class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button
                            wire:click="approve({{ $request->id }})"
                            class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500"
                        >
                            Approve
                        </button>
                        <button
                            wire:click="reject({{ $request->id }})"
                            class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500"
                        >
                            Reject
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    </div>
    </div>
</div>
