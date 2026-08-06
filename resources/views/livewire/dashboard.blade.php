<x-slot name="header">{{ __('Dashboard') }}</x-slot>

<div class="space-y-6">
    <x-card class="bg-gradient-to-r from-teal-700 to-emerald-700 border-0">
        <h3 class="text-xl font-semibold text-white">Welcome back, {{ str(Auth::user()->name)->before(' ') }} 👋</h3>
        <p class="mt-1 text-sm text-teal-50">
            Here's quick access to the things you use most.
        </p>
    </x-card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('attendance.check-in') }}" class="group">
            <x-card class="h-full transition hover:border-teal-300 hover:shadow-md">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-medium text-slate-900 group-hover:text-teal-700">Attendance</p>
                        <p class="text-sm text-slate-500">Check in or out for today</p>
                    </div>
                </div>
            </x-card>
        </a>

        <a href="{{ route('leave.apply') }}" class="group">
            <x-card class="h-full transition hover:border-teal-300 hover:shadow-md">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-medium text-slate-900 group-hover:text-teal-700">Apply for Leave</p>
                        <p class="text-sm text-slate-500">Submit a new leave request</p>
                    </div>
                </div>
            </x-card>
        </a>

        <a href="{{ route('leave.my-requests') }}" class="group">
            <x-card class="h-full transition hover:border-teal-300 hover:shadow-md">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75M8.25 6.75h7.5c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 014.875 19.125V7.875c0-.621.504-1.125 1.125-1.125h2.25" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-medium text-slate-900 group-hover:text-teal-700">My Requests</p>
                        <p class="text-sm text-slate-500">Track your leave request status</p>
                    </div>
                </div>
            </x-card>
        </a>

        @if (Auth::user()->isManager())
            <a href="{{ route('leave.approvals') }}" class="group">
                <x-card class="h-full transition hover:border-teal-300 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-slate-900 group-hover:text-teal-700">Approvals</p>
                            <p class="text-sm text-slate-500">Review your team's pending requests</p>
                        </div>
                    </div>
                </x-card>
            </a>

            <a href="{{ route('leave.team-calendar') }}" class="group">
                <x-card class="h-full transition hover:border-teal-300 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-slate-900 group-hover:text-teal-700">Team Calendar</p>
                            <p class="text-sm text-slate-500">See who's off and when</p>
                        </div>
                    </div>
                </x-card>
            </a>
        @endif
    </div>

    @if ($showWelcome)
        <x-modal name="welcome" :show="true" focusable>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Welcome to LeaveDesk, {{ str(Auth::user()->name)->before(' ') }} 👋</h2>
                <p class="mt-2 text-sm text-slate-600">Here's the quick version of how this works:</p>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                    <li>Apply for leave online — it's checked against your real balance right away.</li>
                    <li>Check in and out each day from the Attendance page.</li>
                    <li>Track the status of anything you've requested under My Requests.</li>
                    @if (Auth::user()->isManager())
                        <li>As a manager, you'll be notified here whenever someone on your team requests leave, and you can approve or reject it with a note.</li>
                    @endif
                </ul>
                <div class="mt-6 flex justify-end">
                    <x-primary-button wire:click="dismissWelcome">Got it, thanks!</x-primary-button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
