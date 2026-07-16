<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Team Calendar') }}</h2>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="mb-4 flex gap-4 text-sm text-gray-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> Approved leave
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Holiday
                </span>
            </div>

            <div id="team-calendar" wire:ignore wire:key="team-calendar"></div>

            <script>
                window.teamCalendarInitialEvents = @js($initialEvents);
            </script>

            @vite('resources/js/team-calendar.js')
        </div>
    </div>
</div>
