<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\OfficeLocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class OfficeLocation extends Component
{
    public ?float $latitude = null;

    public ?float $longitude = null;

    /**
     * A generic starting suggestion, not tied to any real place - unlike
     * latitude/longitude, there's no sense in which a radius is "somebody
     * else's office", so defaulting it is harmless. HR still has to
     * explicitly place the pin (or use their current location) before this
     * screen represents a real, saveable location.
     */
    public int $radiusMeters = 100;

    public ?string $label = null;

    public ?string $successMessage = null;

    public function mount(OfficeLocationService $service): void
    {
        abort_unless(Auth::user()->isHr(), 403);

        $location = $service->get();

        if ($location !== null) {
            $this->latitude = $location->latitude;
            $this->longitude = $location->longitude;
            $this->radiusMeters = $location->radius_meters;
            $this->label = $location->label;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radiusMeters' => ['required', 'integer', 'min:10', 'max:5000'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Called from the map's marker-drag/click handler (see
     * office-location-map.js) and from "Use my current location" in the
     * Blade view - both talk back to this component the same way
     * team-calendar.js already does for FullCalendar, since the map is a
     * wire:ignore'd DOM subtree Livewire's own re-render never touches. The
     * map already knows its own new position when *it* triggers this, so
     * re-broadcasting back to it is a harmless no-op then - but it's the
     * only way "Use my current location" (which the map has no idea about)
     * ever reaches the map at all.
     */
    public function setCoordinates(float $latitude, float $longitude): void
    {
        $this->latitude = round($latitude, 7);
        $this->longitude = round($longitude, 7);
        $this->broadcastLocationChange();
    }

    /**
     * The reverse direction: when latitude/longitude/radius are edited
     * directly in the fields (wire:model.blur), tell the map to reposition
     * the pin - it can't see these PHP property changes on its own.
     */
    public function updatedLatitude(): void
    {
        $this->broadcastLocationChange();
    }

    public function updatedLongitude(): void
    {
        $this->broadcastLocationChange();
    }

    public function updatedRadiusMeters(): void
    {
        $this->broadcastLocationChange();
    }

    private function broadcastLocationChange(): void
    {
        $this->dispatch('office-location-changed', latitude: $this->latitude, longitude: $this->longitude, radiusMeters: $this->radiusMeters);
    }

    public function save(OfficeLocationService $service): void
    {
        $this->successMessage = null;
        $validated = $this->validate();

        $service->save(
            latitude: $validated['latitude'],
            longitude: $validated['longitude'],
            radiusMeters: $validated['radiusMeters'],
            label: $validated['label'],
        );

        $this->successMessage = 'Office location updated.';
    }

    public function render(): View
    {
        return view('livewire.admin.office-location');
    }
}
