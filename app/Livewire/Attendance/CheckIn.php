<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Services\AttendanceService;
use App\Services\HolidayService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CheckIn extends Component
{
    public ?string $errorMessage = null;

    /**
     * Coordinates are captured by the browser's Geolocation API and passed
     * straight into this call from JS (see the view) — nothing is trusted
     * from a hidden form field, and the actual radius check happens
     * server-side in AttendanceService, never in the client.
     */
    public function checkIn(AttendanceService $service, float $latitude, float $longitude): void
    {
        $this->errorMessage = null;

        try {
            $service->checkIn((int) Auth::id(), now()->toDateString(), $latitude, $longitude);
        } catch (DomainException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (ValidationException $exception) {
            $this->errorMessage = $exception->validator->errors()->first();
        }
    }

    public function checkOut(AttendanceService $service): void
    {
        $this->errorMessage = null;

        try {
            $service->checkOut((int) Auth::id(), now()->toDateString());
        } catch (DomainException $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function render(AttendanceService $service, HolidayService $holidays): View
    {
        $userId = (int) Auth::id();
        $today = $service->findForDate($userId, now()->toDateString());
        $history = $service->historyForUser($userId, 14);

        foreach ($history as $record) {
            $record->duration_label = $service->formatDuration(
                $service->minutesWorked($record->check_in_at, $record->check_out_at, $record->status)
            );
        }

        return view('livewire.attendance.check-in', [
            'today' => $today,
            'todayDuration' => $today !== null
                ? $service->formatDuration($service->minutesWorked($today->check_in_at, $today->check_out_at, $today->status))
                : null,
            'history' => $history,
            'todayHoliday' => $holidays->forDate(now()->toDateString()),
        ]);
    }
}
