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

            @if ($todayHoliday)
                <div class="mt-6 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.516 11.598c.75 1.334-.213 2.984-1.742 2.984H3.483c-1.53 0-2.493-1.65-1.743-2.984L8.257 3.1zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-amber-800">Today is {{ $todayHoliday->name }}</p>
                        <p class="text-sm text-amber-700">It's a company holiday — no check-in is required today.</p>
                    </div>
                </div>
            @else
                <div
                    x-data="{
                        capturing: false,
                        locationError: null,
                        checkIn() {
                            this.locationError = null;

                            if (! ('geolocation' in navigator)) {
                                this.locationError = 'Your browser does not support location services. Location access is required to check in.';
                                return;
                            }

                            this.capturing = true;

                            // Alpine's bare `$wire` magic only resolves inside expressions
                            // Alpine itself evaluates (e.g. x-on attributes) — it's not
                            // reliably in scope inside a native browser API callback like
                            // getCurrentPosition's. Capturing `this.$wire` (which IS always
                            // reachable via `this`) into a local before the async call
                            // sidesteps that entirely.
                            const $wire = this.$wire;

                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    this.capturing = false;
                                    $wire.checkIn(position.coords.latitude, position.coords.longitude);
                                },
                                () => {
                                    this.capturing = false;
                                    this.locationError = 'Location access was denied. Please allow location access in your browser settings and try again.';
                                },
                                { enableHighAccuracy: true, timeout: 10000 }
                            );
                        },
                    }"
                >
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700" x-show="locationError" x-text="locationError" style="display: none;"></div>

                    <div class="flex gap-2">
                        <x-primary-button type="button" @click="checkIn" x-bind:disabled="capturing || {{ $today?->check_in_at ? 'true' : 'false' }}">
                            <span x-show="! capturing">Check In</span>
                            <span x-show="capturing" style="display: none;">Getting your location&hellip;</span>
                        </x-primary-button>
                        <x-secondary-button wire:click="checkOut" :disabled="! $today?->check_in_at || (bool) $today?->check_out_at">
                            Check Out
                        </x-secondary-button>
                    </div>
                </div>
            @endif
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
