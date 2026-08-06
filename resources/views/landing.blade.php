<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LeaveDesk') }}</title>
        <meta name="description" content="LeaveDesk is a simple leave and attendance system for small companies — online leave requests, manager approvals, daily check-in, and payroll-ready reporting.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        {{-- Header --}}
        <header class="sticky top-0 z-20 bg-slate-900/90 backdrop-blur border-b border-white/10">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <span class="text-xl font-bold tracking-tight text-white">
                    Leave<span class="text-teal-400">Desk</span>
                </span>

                <nav class="hidden sm:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-slate-300 hover:text-white">{{ __('Features') }}</a>
                    <a href="#how-it-works" class="text-sm font-medium text-slate-300 hover:text-white">{{ __('How it works') }}</a>
                    <a href="#roles" class="text-sm font-medium text-slate-300 hover:text-white">{{ __('Who it\'s for') }}</a>
                </nav>

                <div class="flex items-center gap-4">
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-200 hover:text-white">
                        {{ __('Log in') }}
                    </a>
                    <a href="{{ route('register-company') }}" class="text-sm font-medium bg-teal-500 hover:bg-teal-400 text-white px-4 py-2 rounded-lg transition">
                        {{ __('Get started') }}
                    </a>
                </div>
            </div>
        </header>

        {{-- Hero --}}
        <section class="bg-gradient-to-br from-slate-900 via-teal-900 to-emerald-800">
            <div class="max-w-6xl mx-auto px-6 pt-16 pb-24 text-center">
                <span class="inline-block text-xs font-semibold tracking-wide uppercase text-teal-300 bg-teal-400/10 border border-teal-400/20 rounded-full px-3 py-1">
                    {{ __('Built for small teams') }}
                </span>

                <h1 class="mt-6 text-4xl sm:text-5xl font-bold tracking-tight text-white max-w-3xl mx-auto">
                    {{ __('Leave and attendance, without the spreadsheet') }}
                </h1>
                <p class="mt-5 text-lg text-slate-300 max-w-2xl mx-auto">
                    {{ __('LeaveDesk replaces WhatsApp messages and Excel trackers with one place for your team to request leave, check in, and see where they stand — and one place for you to approve, track, and report on all of it.') }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register-company') }}" class="w-full sm:w-auto bg-teal-500 hover:bg-teal-400 text-white font-semibold px-6 py-3 rounded-lg transition">
                        {{ __('Set up your company') }}
                    </a>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto text-slate-200 hover:text-white font-semibold px-6 py-3">
                        {{ __('Log in') }} &rarr;
                    </a>
                </div>

                <p class="mt-4 text-xs text-slate-400">{{ __('Free to set up. No credit card required.') }}</p>
            </div>
        </section>

        {{-- How it works --}}
        <section id="how-it-works" class="bg-white py-20">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center max-w-2xl mx-auto">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Up and running in minutes') }}</h2>
                    <p class="mt-3 text-slate-500">{{ __('No IT setup, no spreadsheets to migrate — just create your company and start inviting people.') }}</p>
                </div>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-3 gap-10">
                    <div class="relative text-center sm:text-left">
                        <span class="text-5xl font-bold text-teal-100">01</span>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ __('Set up your company') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Create your company\'s workspace and your own HR/Admin account — takes about a minute.') }}</p>
                    </div>
                    <div class="relative text-center sm:text-left">
                        <span class="text-5xl font-bold text-teal-100">02</span>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ __('Configure and invite') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Set up leave types, holidays, and departments, then add your team — each person gets an email invite to set their own password.') }}</p>
                    </div>
                    <div class="relative text-center sm:text-left">
                        <span class="text-5xl font-bold text-teal-100">03</span>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ __('Everyone gets to work') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Employees request leave and check in, managers approve, and HR reports on all of it — no spreadsheet in sight.') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section id="features" class="bg-slate-50 py-20 border-y border-slate-100">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center max-w-2xl mx-auto">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Everything a small company needs') }}</h2>
                    <p class="mt-3 text-slate-500">{{ __('Leave, attendance, and reporting — in one place, with the approval trail already built in.') }}</p>
                </div>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @php
                        $features = [
                            [
                                'title' => 'Online leave requests',
                                'description' => 'Employees apply for leave in a few clicks and instantly see it checked against their real balance.',
                                'icon' => 'calendar',
                            ],
                            [
                                'title' => 'Manager approvals',
                                'description' => 'Managers get notified, approve or reject with a note, and every decision is recorded — who, when, and why.',
                                'icon' => 'check-badge',
                            ],
                            [
                                'title' => 'Daily check-in & attendance',
                                'description' => 'Employees check in and out for the day, with late arrivals flagged automatically against your work schedule.',
                                'icon' => 'clock',
                            ],
                            [
                                'title' => 'Approved leave, auto-linked',
                                'description' => 'Approved leave writes straight into the attendance record, so there\'s one single source of truth — never two.',
                                'icon' => 'link',
                            ],
                            [
                                'title' => 'Team calendar & holidays',
                                'description' => 'Managers see who\'s out and when, with company holidays and weekends already factored in.',
                                'icon' => 'users',
                            ],
                            [
                                'title' => 'Payroll-ready exports',
                                'description' => 'HR exports attendance and leave data ready for payroll, without reconciling three different spreadsheets first.',
                                'icon' => 'chart-bar',
                            ],
                        ];
                    @endphp

                    @foreach ($features as $feature)
                        <div class="bg-white border border-slate-200 rounded-2xl p-6">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                                @switch($feature['icon'])
                                    @case('calendar')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 5.25h15a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75h-15a.75.75 0 01-.75-.75V6a.75.75 0 01.75-.75zM8.25 12h.008v.008H8.25V12zm3.75 0h.008v.008H12V12zm3.75 0h.008v.008h-.008V12zM8.25 15.75h.008v.008H8.25v-.008zm3.75 0h.008v.008H12v-.008zm3.75 0h.008v.008h-.008v-.008z" /></svg>
                                        @break
                                    @case('check-badge')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l1.75 1.75L15 10.5m5.653 3.484a11.978 11.978 0 01-6.153 6.734L12 21.75l-2.5-1.032a11.978 11.978 0 01-6.153-6.734A11.842 11.842 0 013 10.5a11.842 11.842 0 01.347-3.484A11.978 11.978 0 0112 3.75a11.978 11.978 0 018.653 3.266A11.842 11.842 0 0121 10.5c0 1.19-.116 2.353-.347 3.484z" /></svg>
                                        @break
                                    @case('clock')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" /></svg>
                                        @break
                                    @case('link')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                                        @break
                                    @case('users')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                                        @break
                                    @case('chart-bar')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                                        @break
                                @endswitch
                            </div>
                            <h3 class="mt-4 font-semibold text-slate-900">{{ __($feature['title']) }}</h3>
                            <p class="mt-1.5 text-sm text-slate-500">{{ __($feature['description']) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Roles --}}
        <section id="roles" class="bg-white py-20">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center max-w-2xl mx-auto">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('One app, three very different days') }}</h2>
                    <p class="mt-3 text-slate-500">{{ __('Everyone sees exactly what they need — nothing more.') }}</p>
                </div>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="rounded-2xl border border-slate-200 p-8">
                        <span class="text-xs font-semibold tracking-wide uppercase text-teal-600">{{ __('Employee') }}</span>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ __('Apply, check in, done') }}</h3>
                        <ul class="mt-4 space-y-3 text-sm text-slate-600">
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Apply for leave online, no forms or messages') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('See remaining leave balance at a glance') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Check in and out for the day') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Track every request\'s status in real time') }}</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-8">
                        <span class="text-xs font-semibold tracking-wide uppercase text-teal-600">{{ __('Manager') }}</span>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ __('Approve with context') }}</h3>
                        <ul class="mt-4 space-y-3 text-sm text-slate-600">
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Get notified the moment a request comes in') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Approve or reject with a note, on the spot') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('See the whole team\'s calendar at a glance') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Review your team\'s attendance history') }}</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-8">
                        <span class="text-xs font-semibold tracking-wide uppercase text-teal-600">{{ __('HR / Admin') }}</span>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ __('Configure once, run itself') }}</h3>
                        <ul class="mt-4 space-y-3 text-sm text-slate-600">
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Set leave types, policies, and holidays') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Manage employees and departments') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Final sign-off on every leave request') }}</li>
                            <li class="flex gap-2"><span class="text-teal-500">&check;</span> {{ __('Export payroll-ready attendance data') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        {{-- Final CTA --}}
        <section class="bg-gradient-to-br from-slate-900 via-teal-900 to-emerald-800">
            <div class="max-w-4xl mx-auto px-6 py-20 text-center">
                <h2 class="text-3xl font-bold tracking-tight text-white">{{ __('Ready to put the spreadsheet away?') }}</h2>
                <p class="mt-3 text-slate-300">{{ __('Set up your company\'s workspace in a couple of minutes — free to start.') }}</p>
                <div class="mt-8">
                    <a href="{{ route('register-company') }}" class="inline-block bg-teal-500 hover:bg-teal-400 text-white font-semibold px-8 py-3 rounded-lg transition">
                        {{ __('Set up your company') }}
                    </a>
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="bg-slate-950">
            <div class="max-w-6xl mx-auto px-6 py-10 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span class="text-lg font-bold tracking-tight text-white">
                    Leave<span class="text-teal-400">Desk</span>
                </span>
                <div class="flex items-center gap-6 text-sm text-slate-400">
                    <a href="{{ route('login') }}" class="hover:text-white">{{ __('Log in') }}</a>
                    <a href="{{ route('register-company') }}" class="hover:text-white">{{ __('Get started') }}</a>
                </div>
                <p class="text-xs text-slate-500">&copy; {{ now()->year }} {{ config('app.name', 'LeaveDesk') }}</p>
            </div>
        </footer>
    </body>
</html>
