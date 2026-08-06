<x-slot name="header">{{ __('HR Dashboard') }}</x-slot>

<div class="space-y-6">
    @if ($stalePendingHrAlert)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 flex items-center justify-between gap-4">
            <span>⚠️ {{ $stalePendingHrAlert['message'] }}</span>
            <a href="{{ route($stalePendingHrAlert['route']) }}" wire:navigate class="shrink-0 font-semibold underline hover:text-amber-900">
                Review now
            </a>
        </div>
    @endif

    @foreach ($setupAlerts as $alert)
        <div wire:key="setup-alert-{{ $alert['key'] }}" class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 flex items-center justify-between gap-4">
            <span>💡 {{ $alert['message'] }}</span>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route($alert['route']) }}" wire:navigate class="font-semibold text-teal-700 underline hover:text-teal-900">
                    Set this up
                </a>
                <button
                    type="button"
                    wire:click="dismissSetupAlert('{{ $alert['key'] }}')"
                    class="text-slate-400 hover:text-slate-600"
                    aria-label="Dismiss"
                >
                    &times;
                </button>
            </div>
        </div>
    @endforeach

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card>
            <p class="text-sm font-medium text-slate-500">Present Today</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['presentToday'] }}</p>
        </x-card>

        <x-card>
            <p class="text-sm font-medium text-slate-500">Late Check-ins Today</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['lateToday'] }}</p>
        </x-card>

        <x-card>
            <p class="text-sm font-medium text-slate-500">On Leave Today</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['onLeaveToday'] }}</p>
        </x-card>

        <a href="{{ route('admin.leave-approvals', ['tab' => 'awaiting_manager']) }}" wire:navigate>
            <x-card class="hover:border-teal-300 transition">
                <p class="text-sm font-medium text-slate-500">Awaiting Manager</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['pendingManager'] }}</p>
            </x-card>
        </a>

        <a href="{{ route('admin.leave-approvals', ['tab' => 'pending']) }}" wire:navigate>
            <x-card class="hover:border-teal-300 transition">
                <p class="text-sm font-medium text-slate-500">Awaiting HR</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['pendingHr'] }}</p>
            </x-card>
        </a>
    </div>

    <x-card>
        <h3 class="text-sm font-semibold text-slate-900 mb-1">Export Attendance</h3>
        <p class="text-xs text-slate-500 mb-4">Download a payroll-ready spreadsheet for a date range — every working day is included, even ones with no check-in.</p>

        <form method="GET" action="{{ route('admin.attendance.export') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="export-start" value="Start Date" />
                <x-text-input id="export-start" name="start" type="date" value="{{ now()->startOfMonth()->toDateString() }}" class="mt-1 block" />
            </div>
            <div>
                <x-input-label for="export-end" value="End Date" />
                <x-text-input id="export-end" name="end" type="date" value="{{ now()->toDateString() }}" class="mt-1 block" />
            </div>
            <x-primary-button>Export to Excel</x-primary-button>
            <x-secondary-button type="submit" formaction="{{ route('admin.attendance.export-pdf') }}">Export to PDF</x-secondary-button>
        </form>
    </x-card>

    @if ($showWelcome)
        <x-modal name="welcome" :show="true" focusable>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Welcome to LeaveDesk, {{ str(Auth::user()->name)->before(' ') }} 👋</h2>
                <p class="mt-2 text-sm text-slate-600">
                    You're set up as HR/Admin for {{ Auth::user()->company->name }}. A few things to know:
                </p>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                    <li>Any setup you still need to finish shows up as a reminder right on this dashboard — no rush, do it whenever suits you.</li>
                    <li>Once you've added leave types and invited your team, they can start applying for leave and checking in.</li>
                    <li>Every leave request eventually needs your final sign-off — you'll see those here too.</li>
                </ul>
                <div class="mt-6 flex justify-end">
                    <x-primary-button wire:click="dismissWelcome">Got it, thanks!</x-primary-button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
