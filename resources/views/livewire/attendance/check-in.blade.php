<x-slot name="header">{{ __('Attendance') }}</x-slot>

<div class="space-y-6">
    @if ($errorMessage)
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    <x-card>
        <h3 class="mb-4 text-lg font-semibold text-slate-900">Today — {{ now()->format('l, M j, Y') }}</h3>

        <div class="flex items-center gap-8">
            <div>
                <p class="text-sm text-slate-500">Check-in</p>
                <p class="text-2xl font-semibold text-slate-900">
                    {{ $today?->check_in_at ? \Illuminate\Support\Carbon::parse($today->check_in_at)->format('g:i A') : '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Check-out</p>
                <p class="text-2xl font-semibold text-slate-900">
                    {{ $today?->check_out_at ? \Illuminate\Support\Carbon::parse($today->check_out_at)->format('g:i A') : '—' }}
                </p>
            </div>
        </div>

        @if ($todayHoliday)
            <div class="mt-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.516 11.598c.75 1.334-.213 2.984-1.742 2.984H3.483c-1.53 0-2.493-1.65-1.743-2.984L8.257 3.1zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800">Today is {{ $todayHoliday->name }}</p>
                    <p class="text-sm text-amber-700">It's a company holiday — no check-in is required today.</p>
                </div>
            </div>
        @else
            <div x-data="{ capturing: false, locationError: null }" class="mt-6">
                <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700" x-show="locationError" x-text="locationError" style="display: none;"></div>

                <div class="flex gap-3">
                    {{-- The geolocation call lives directly in @click (not a method
                         defined inside x-data) because Alpine only reliably injects
                         the bare $wire magic into expressions it evaluates itself —
                         like this one. Capturing it into a local (`livewire`) before
                         the async getCurrentPosition call means the callbacks close
                         over that local instead of needing $wire in scope later. --}}
                    <x-primary-button
                        type="button"
                        x-bind:disabled="capturing || {{ $today?->check_in_at ? 'true' : 'false' }}"
                        @click="
                            locationError = null;

                            if (! ('geolocation' in navigator)) {
                                locationError = 'Your browser does not support location services. Location access is required to check in.';
                                return;
                            }

                            capturing = true;

                            let livewire = $wire;

                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    capturing = false;
                                    livewire.checkIn(position.coords.latitude, position.coords.longitude);
                                },
                                () => {
                                    capturing = false;
                                    locationError = 'Location access was denied. Please allow location access in your browser settings and try again.';
                                },
                                { enableHighAccuracy: true, timeout: 10000 }
                            );
                        "
                    >
                        <span x-show="! capturing">Check In</span>
                        <span x-show="capturing" style="display: none;">Getting your location&hellip;</span>
                    </x-primary-button>
                    <x-secondary-button wire:click="checkOut" :disabled="! $today?->check_in_at || (bool) $today?->check_out_at">
                        Check Out
                    </x-secondary-button>
                </div>
            </div>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-4 text-lg font-semibold text-slate-900">Recent History</h3>

        @if (count($history) === 0)
            <p class="text-sm text-slate-500">No attendance recorded yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-4 font-medium">Date</th>
                            <th class="py-2 pr-4 font-medium">Check-in</th>
                            <th class="py-2 pr-4 font-medium">Check-out</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $record)
                            <tr wire:key="attendance-{{ $record->id }}">
                                <td class="py-2.5 pr-4">{{ \Illuminate\Support\Carbon::parse($record->date)->format('M j, Y') }}</td>
                                <td class="py-2.5 pr-4">{{ $record->check_in_at ? \Illuminate\Support\Carbon::parse($record->check_in_at)->format('g:i A') : '—' }}</td>
                                <td class="py-2.5 pr-4">{{ $record->check_out_at ? \Illuminate\Support\Carbon::parse($record->check_out_at)->format('g:i A') : '—' }}</td>
                                <td class="py-2.5 pr-4">
                                    <x-badge :color="match ($record->status) {
                                        'present' => 'emerald',
                                        'late' => 'amber',
                                        'absent' => 'red',
                                        'on_leave' => 'teal',
                                        default => 'slate',
                                    }">
                                        {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
