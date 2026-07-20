<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('HR Dashboard') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-indigo-500">
                <p class="text-sm font-medium text-gray-500">Present Today</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['presentToday'] }}</p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-amber-500">
                <p class="text-sm font-medium text-gray-500">Late Check-ins Today</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['lateToday'] }}</p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                <p class="text-sm font-medium text-gray-500">On Leave Today</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['onLeaveToday'] }}</p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                <p class="text-sm font-medium text-gray-500">Pending Requests</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['pendingRequests'] }}</p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Export Attendance</h3>
            <p class="text-xs text-gray-500 mb-4">Download a payroll-ready spreadsheet for a date range — every working day is included, even ones with no check-in.</p>

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
            </form>
        </div>
    </div>
</div>
