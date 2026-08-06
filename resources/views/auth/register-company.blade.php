<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-semibold text-slate-800">Set up your company</h1>
        <p class="mt-1 text-sm text-slate-500">Create your company's workspace and your own HR/Admin account.</p>
    </div>

    <form method="POST" action="{{ route('register-company.store') }}">
        @csrf

        <div>
            <x-input-label for="company_name" :value="__('Company Name')" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name" :value="old('company_name')" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="company_address" :value="__('Company Address (optional)')" />
            <textarea id="company_address" name="company_address" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">{{ old('company_address') }}</textarea>
            <x-input-error :messages="$errors->get('company_address')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="company_website" :value="__('Company Website (optional)')" />
            <x-text-input id="company_website" class="block mt-1 w-full" type="url" name="company_website" :value="old('company_website')" autocomplete="url" placeholder="https://example.com" />
            <x-input-error :messages="$errors->get('company_website')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="name" :value="__('Your Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center py-2.5">
                {{ __('Create Company') }}
            </x-primary-button>
        </div>

        <div class="mt-4 text-center">
            <a class="text-sm text-slate-600 hover:text-slate-900 underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500" href="{{ route('login') }}">
                {{ __('Already have an account? Log in') }}
            </a>
        </div>
    </form>
</x-guest-layout>
