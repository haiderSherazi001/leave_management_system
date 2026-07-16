<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Attendance') }}</h2>

        @if ($errorMessage)
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                {{ $errorMessage }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Today — {{ now()->format('l, M j, Y') }}</h3>

            <div class="flex items-center gap-6">
                <div>
                    <p class="text-sm text-gray-500">Check-in</p>
                    <p class="text-lg font-medium text-gray-900">
                        {{ $today?->check_in_at ? \Illuminate\Support\Carbon::parse($today->check_in_at)->format('g:i A') : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Check-out</p>
                    <p class="text-lg font-medium text-gray-900">
                        {{ $today?->check_out_at ? \Illuminate\Support\Carbon::parse($today->check_out_at)->format('g:i A') : '—' }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex gap-2">
                <x-primary-button wire:click="checkIn" :disabled="(bool) $today?->check_in_at">
                    Check In
                </x-primary-button>
                <x-secondary-button wire:click="checkOut" :disabled="! $today?->check_in_at || (bool) $today?->check_out_at">
                    Check Out
                </x-secondary-button>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Recent History</h3>

            @if (count($history) === 0)
                <p class="text-sm text-gray-500">No attendance recorded yet.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Check-in</th>
                            <th class="py-2 pr-4">Check-out</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($history as $record)
                            <tr wire:key="attendance-{{ $record->id }}">
                                <td class="py-2 pr-4">{{ \Illuminate\Support\Carbon::parse($record->date)->format('M j, Y') }}</td>
                                <td class="py-2 pr-4">{{ $record->check_in_at ? \Illuminate\Support\Carbon::parse($record->check_in_at)->format('g:i A') : '—' }}</td>
                                <td class="py-2 pr-4">{{ $record->check_out_at ? \Illuminate\Support\Carbon::parse($record->check_out_at)->format('g:i A') : '—' }}</td>
                                <td class="py-2 pr-4">
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800' => $record->status === 'present',
                                        'bg-yellow-100 text-yellow-800' => $record->status === 'late',
                                        'bg-red-100 text-red-800' => $record->status === 'absent',
                                        'bg-blue-100 text-blue-800' => $record->status === 'on_leave',
                                    ])>
                                        {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
