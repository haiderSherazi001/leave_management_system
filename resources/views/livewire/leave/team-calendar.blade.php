<x-slot name="header">{{ __('Team Calendar') }}</x-slot>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    <x-card class="lg:col-span-2">
        <div class="mb-4 flex gap-4 text-sm text-slate-500">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span> Approved leave
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-slate-600"></span> Holiday
            </span>
        </div>

        <div id="team-calendar" wire:ignore wire:key="team-calendar"></div>

        <script>
            window.teamCalendarInitialEvents = @js($initialEvents);
        </script>

        @vite('resources/js/team-calendar.js')
    </x-card>

    <x-card>
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Upcoming Holidays</h3>

        @if (count($upcomingHolidays) === 0)
            <p class="text-sm text-slate-500">No upcoming holidays scheduled.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($upcomingHolidays as $holiday)
                    <li class="py-2.5 flex items-center justify-between gap-3" wire:key="upcoming-holiday-{{ $holiday->id }}">
                        <span class="text-sm text-slate-700">{{ $holiday->name }}</span>
                        <span class="shrink-0 text-xs font-medium text-slate-500">
                            {{ \Illuminate\Support\Carbon::parse($holiday->date)->format('M j') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
