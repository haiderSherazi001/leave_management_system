<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Services\CalendarService;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TeamCalendar extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->isManager(), 403);
    }

    /**
     * Called by the FullCalendar bridge (resources/js/team-calendar.js)
     * whenever the visible range changes. Dispatches fresh event data as a
     * browser event rather than a public property, so the wire:ignore'd
     * calendar container is never touched by Livewire's morph step.
     */
    public function loadEventsForRange(string $start, string $end, CalendarService $service): void
    {
        $events = $service->teamEventsBetween((int) Auth::id(), $start, $end);

        $this->dispatch('calendar-events-updated', events: $events);
    }

    public function render(CalendarService $service, HolidayService $holidays): View
    {
        $start = CarbonImmutable::now()->startOfMonth()->toDateString();
        $end = CarbonImmutable::now()->endOfMonth()->toDateString();

        return view('livewire.leave.team-calendar', [
            'initialEvents' => $service->teamEventsBetween((int) Auth::id(), $start, $end),
            'upcomingHolidays' => $holidays->upcoming(5),
        ]);
    }
}
