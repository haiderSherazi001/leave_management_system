<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

final class CalendarService
{
    /**
     * Hardcoded so leave and holiday events are never visually ambiguous —
     * holidays are gray (deliberately not a "leave type color") and leave
     * requests are indigo, applied as real background/border colors (not
     * just CSS classNames) so FullCalendar renders them distinctly even
     * before any page-specific CSS loads.
     */
    private const LEAVE_COLOR = '#6366f1';

    private const HOLIDAY_COLOR = '#4b5563';

    public function __construct(
        private readonly LeaveRequestService $leaveRequests,
        private readonly HolidayService $holidays,
    ) {}

    /**
     * Team-scoped approved leave plus company holidays, shaped as
     * FullCalendar event objects for the given manager and date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function teamEventsBetween(int $managerId, string $start, string $end): array
    {
        $events = [];

        foreach ($this->leaveRequests->approvedForTeamBetween($managerId, $start, $end) as $request) {
            $events[] = [
                'id' => 'leave-'.$request->id,
                'title' => $request->employee_name.' — '.$request->leave_type_name,
                'start' => $request->start_date,
                'end' => $this->exclusiveEnd($request->end_date),
                'allDay' => true,
                'classNames' => ['fc-event-leave'],
                'backgroundColor' => self::LEAVE_COLOR,
                'borderColor' => self::LEAVE_COLOR,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'type' => 'leave',
                    'reason' => $request->reason,
                    'isHalfDay' => (bool) $request->is_half_day,
                ],
            ];
        }

        foreach ($this->holidays->betweenDates($start, $end) as $holiday) {
            $events[] = [
                'id' => 'holiday-'.$holiday->id,
                // Prefixed so it never reads like a leave-request event at a
                // glance, on the calendar grid or in the overflow popover.
                'title' => 'Holiday: '.$holiday->name,
                'start' => $holiday->date,
                'end' => $this->exclusiveEnd($holiday->date),
                'allDay' => true,
                'classNames' => ['fc-event-holiday'],
                'backgroundColor' => self::HOLIDAY_COLOR,
                'borderColor' => self::HOLIDAY_COLOR,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'type' => 'holiday',
                ],
            ];
        }

        return $events;
    }

    /**
     * FullCalendar treats an all-day event's `end` as exclusive, so an
     * inclusive end_date needs +1 day or the last day renders as uncovered.
     */
    private function exclusiveEnd(string $date): string
    {
        return CarbonImmutable::parse($date)->addDay()->toDateString();
    }
}
