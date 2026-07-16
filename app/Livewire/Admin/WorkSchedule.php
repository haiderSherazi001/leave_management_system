<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\WorkScheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WorkSchedule extends Component
{
    /** @var array<int, int> */
    public array $workingDays = [1, 2, 3, 4, 5];

    public string $startTime = '09:00';

    public string $endTime = '17:00';

    public int $graceMinutes = 0;

    public ?string $successMessage = null;

    public function mount(WorkScheduleService $service): void
    {
        abort_unless(Auth::user()->isHr(), 403);

        $schedule = $service->get();

        if ($schedule !== null) {
            $this->workingDays = $schedule->working_days;
            $this->startTime = substr((string) $schedule->start_time, 0, 5);
            $this->endTime = substr((string) $schedule->end_time, 0, 5);
            $this->graceMinutes = $schedule->grace_minutes;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'workingDays' => ['required', 'array', 'min:1'],
            'workingDays.*' => ['integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'graceMinutes' => ['required', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function save(WorkScheduleService $service): void
    {
        $this->successMessage = null;
        $validated = $this->validate();

        $service->save(
            workingDays: $validated['workingDays'],
            startTime: $validated['startTime'].':00',
            endTime: $validated['endTime'].':00',
            graceMinutes: $validated['graceMinutes'],
        );

        $this->successMessage = 'Work schedule updated.';
    }

    public function render(): View
    {
        return view('livewire.admin.work-schedule');
    }
}
