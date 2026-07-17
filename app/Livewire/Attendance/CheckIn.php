<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Services\AttendanceService;
use App\Services\HolidayService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CheckIn extends Component
{
    public ?string $errorMessage = null;

    public function checkIn(AttendanceService $service): void
    {
        $this->errorMessage = null;

        try {
            $service->checkIn((int) Auth::id(), now()->toDateString());
        } catch (DomainException $exception) {
            $this->errorMessage = $exception->getMessage();
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

        return view('livewire.attendance.check-in', [
            'today' => $service->findForDate($userId, now()->toDateString()),
            'history' => $service->historyForUser($userId, 14),
            'todayHoliday' => $holidays->forDate(now()->toDateString()),
        ]);
    }
}
