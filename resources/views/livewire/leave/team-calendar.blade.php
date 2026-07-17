<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Team Calendar') }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4 flex gap-4 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> Approved leave
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-gray-600"></span> Holiday
                    </span>
                </div>

                <div id="team-calendar" wire:ignore wire:key="team-calendar"></div>

                <script>
                    window.teamCalendarInitialEvents = @js($initialEvents);
                </script>

                @vite('resources/js/team-calendar.js')
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Upcoming Holidays</h3>

                @if (count($upcomingHolidays) === 0)
                    <p class="text-sm text-gray-500">No upcoming holidays scheduled.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($upcomingHolidays as $holiday)
                            <li class="py-2.5 flex items-center justify-between gap-3" wire:key="upcoming-holiday-{{ $holiday->id }}">
                                <span class="text-sm text-gray-700">{{ $holiday->name }}</span>
                                <span class="shrink-0 text-xs font-medium text-gray-500">
                                    {{ \Illuminate\Support\Carbon::parse($holiday->date)->format('M j') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
