<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CompanySettings extends Component
{
    public string $name = '';

    public ?string $address = null;

    public ?string $website = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);

        $company = Auth::user()->company;

        $this->name = $company->name;
        $this->address = $company->address;
        $this->website = $company->website;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->successMessage = null;
        $validated = $this->validate();

        // Livewire binds an empty textarea/input to '', not null - normalized
        // here so a cleared field actually stores null, not an empty string.
        $validated['address'] = $validated['address'] !== '' ? $validated['address'] : null;
        $validated['website'] = $validated['website'] !== '' ? $validated['website'] : null;

        Auth::user()->company->update($validated);

        $this->successMessage = 'Company details updated.';
    }

    public function render(): View
    {
        return view('livewire.admin.company-settings');
    }
}
