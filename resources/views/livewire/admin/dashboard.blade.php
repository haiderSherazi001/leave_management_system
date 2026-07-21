<x-slot name="header">{{ __('HR Dashboard') }}</x-slot>

<div class="space-y-6">
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

        <x-card>
            <p class="text-sm font-medium text-slate-500">Pending Requests</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['pendingRequests'] }}</p>
        </x-card>
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
</div>
