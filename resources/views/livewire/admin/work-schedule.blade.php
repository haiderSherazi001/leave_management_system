<x-slot name="header">{{ __('Work Schedule') }}</x-slot>

<div class="space-y-6">
    @if ($successMessage)
        <x-alert-banner type="success">{{ $successMessage }}</x-alert-banner>
    @endif

    <x-card>
        <form wire:submit="save" class="space-y-4">
            <div>
                <x-input-label value="Working Days" />
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach ([0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'] as $value => $label)
                        <label class="inline-flex items-center">
                            <input type="checkbox" wire:model="workingDays" value="{{ $value }}" class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                            <span class="ms-2 text-sm text-slate-600">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('workingDays')" class="mt-2" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <x-input-label for="startTime" value="Start Time" />
                    <x-text-input id="startTime" type="time" wire:model="startTime" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('startTime')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="endTime" value="End Time" />
                    <x-text-input id="endTime" type="time" wire:model="endTime" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('endTime')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="graceMinutes" value="Grace Period (minutes)" />
                    <x-text-input id="graceMinutes" type="number" min="0" max="120" wire:model="graceMinutes" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('graceMinutes')" class="mt-2" />
                </div>
            </div>

            <x-primary-button>Save</x-primary-button>
        </form>
    </x-card>
</div>
