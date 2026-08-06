<x-slot name="header">{{ __('Company Details') }}</x-slot>

<div class="space-y-6">
    @if ($successMessage)
        <x-alert-banner type="success">{{ $successMessage }}</x-alert-banner>
    @endif

    <x-card>
        <p class="text-sm text-slate-500 mb-4">
            These details identify your company within LeaveDesk.
        </p>

        <form wire:submit="save" class="space-y-4 max-w-xl">
            <div>
                <x-input-label for="name" value="Company Name" />
                <x-text-input id="name" type="text" wire:model="name" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="address" value="Address" />
                <textarea
                    id="address"
                    wire:model="address"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm"
                    placeholder="Street, city, state, postal code"
                ></textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="website" value="Website" />
                <x-text-input id="website" type="url" wire:model="website" class="mt-1 block w-full" placeholder="https://example.com" />
                <x-input-error :messages="$errors->get('website')" class="mt-2" />
            </div>

            <div>
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>
    </x-card>
</div>
