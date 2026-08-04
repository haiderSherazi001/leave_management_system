<x-slot name="header">{{ __('Office Location') }}</x-slot>

<div class="space-y-6">
    @if ($successMessage)
        <x-alert-banner type="success">{{ $successMessage }}</x-alert-banner>
    @endif

    @if ($latitude === null || $longitude === null)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            No office location has been set yet — employees cannot check in until one is saved here. Click anywhere on the map, drag the pin once placed, or use "Use My Current Location" below.
        </div>
    @endif

    <x-card>
        <p class="text-sm text-slate-500 mb-4">
            Employees must check in from within this radius (shown as the shaded circle). Click anywhere on the map to place the pin, drag it afterward, or use your current location to set it.
        </p>

        <div
            id="office-location-map"
            wire:ignore
            wire:key="office-location-map"
            class="h-96 w-full rounded-lg border border-slate-200"
        ></div>

        <script>
            window.officeLocationInitial = @js([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radiusMeters' => $radiusMeters,
            ]);
        </script>

        @vite('resources/js/office-location-map.js')

        <form wire:submit="save" class="mt-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="latitude" value="Latitude" />
                    <x-text-input id="latitude" type="number" step="any" wire:model.blur="latitude" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="longitude" value="Longitude" />
                    <x-text-input id="longitude" type="number" step="any" wire:model.blur="longitude" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="radiusMeters" value="Check-in Radius (meters)" />
                    <x-text-input id="radiusMeters" type="number" min="10" max="5000" wire:model.blur="radiusMeters" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('radiusMeters')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="label" value="Location Name (optional)" />
                <x-text-input id="label" type="text" wire:model="label" class="mt-1 block w-full" placeholder="e.g. Head Office" />
                <x-input-error :messages="$errors->get('label')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>Save</x-primary-button>

                {{-- Same geolocation pattern as the employee check-in page: the
                     call lives directly in @click so Alpine reliably injects
                     $wire, captured into a local before the async callback. --}}
                <x-secondary-button
                    x-data="{ locating: false }"
                    @click="
                        locating = true;
                        let livewire = $wire;

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                locating = false;
                                livewire.setCoordinates(position.coords.latitude, position.coords.longitude);
                            },
                            () => { locating = false; },
                            { enableHighAccuracy: true, timeout: 10000 }
                        );
                    "
                >
                    <span x-show="! locating">Use My Current Location</span>
                    <span x-show="locating" style="display: none;">Locating&hellip;</span>
                </x-secondary-button>
            </div>
        </form>
    </x-card>
</div>
