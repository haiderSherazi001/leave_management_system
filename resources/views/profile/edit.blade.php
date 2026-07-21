<x-app-layout>
    <x-slot name="header">{{ __('Profile') }}</x-slot>

    <div class="space-y-6">
        {{-- <x-card class="max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </x-card> --}}

        <x-card class="max-w-xl">
            @include('profile.partials.update-password-form')
        </x-card>

        {{-- <x-card class="max-w-xl">
            @include('profile.partials.delete-user-form')
        </x-card> --}}
    </div>
</x-app-layout>
