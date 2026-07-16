<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\HolidayService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Holidays extends Component
{
    public ?int $editingId = null;

    public string $date = '';

    public string $name = '';

    public bool $showForm = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'date', 'name']);
        $this->showForm = true;
    }

    public function edit(int $holidayId): void
    {
        $holiday = DB::table('holidays')->where('id', $holidayId)->first();

        if ($holiday === null) {
            return;
        }

        $this->editingId = $holiday->id;
        $this->date = $holiday->date;
        $this->name = $holiday->name;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'date', 'name']);
    }

    public function save(HolidayService $service): void
    {
        $validated = $this->validate();

        if ($this->editingId === null) {
            $service->create($validated['date'], $validated['name']);
        } else {
            $service->update($this->editingId, $validated['date'], $validated['name']);
        }

        $this->cancel();
    }

    public function delete(int $holidayId, HolidayService $service): void
    {
        $service->delete($holidayId);
    }

    public function render(HolidayService $service): View
    {
        return view('livewire.admin.holidays', [
            'holidays' => $service->list(),
        ]);
    }
}
