<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Apply for Leave') }}</h2>

    @if (count($upcomingHolidays) > 0)
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Upcoming Holidays</h3>
            <p class="text-xs text-gray-500 mb-3">The office is closed on these days — no need to apply for leave.</p>

            <ul class="divide-y divide-gray-100">
                @foreach ($upcomingHolidays as $holiday)
                    <li class="py-2.5 flex items-center justify-between gap-3" wire:key="upcoming-holiday-{{ $holiday->id }}">
                        <span class="text-sm text-gray-700">{{ $holiday->name }}</span>
                        <span class="shrink-0 text-xs font-medium text-gray-500">
                            {{ \Illuminate\Support\Carbon::parse($holiday->date)->format('M j, Y') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Apply for Leave</h3>

        @if ($successMessage)
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ $successMessage }}
            </div>
        @endif

        @error('form')
            <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror

        <form wire:submit="submit" class="space-y-4">
            <div>
                <x-input-label for="leaveTypeId" value="Leave Type" />
                <select id="leaveTypeId" wire:model="leaveTypeId" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">Select a leave type</option>
                    @foreach ($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('leaveTypeId')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="startDate" value="Start Date" />
                    <x-text-input id="startDate" type="date" wire:model="startDate" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('startDate')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="endDate" value="End Date" />
                    <x-text-input id="endDate" type="date" wire:model="endDate" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('endDate')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center">
                <input id="isHalfDay" type="checkbox" wire:model="isHalfDay" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <label for="isHalfDay" class="ms-2 text-sm text-gray-600">Half day</label>
            </div>

            <div>
                <x-input-label for="reason" value="Reason" />
                <textarea id="reason" wire:model="reason" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
            </div>

            <x-primary-button>Submit Request</x-primary-button>
        </form>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Your Leave Balance ({{ now()->year }})</h3>

        @if (count($balances) === 0)
            <p class="text-sm text-gray-500">No leave balances have been set up for you yet. Contact HR.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 pr-4">Leave Type</th>
                        <th class="py-2 pr-4">Allocated</th>
                        <th class="py-2 pr-4">Carried Forward</th>
                        <th class="py-2 pr-4">Used</th>
                        <th class="py-2 pr-4">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($balances as $balance)
                        <tr>
                            <td class="py-2 pr-4">{{ $balance->leave_type_name }}</td>
                            <td class="py-2 pr-4">{{ $balance->allocated_days }}</td>
                            <td class="py-2 pr-4">{{ $balance->carried_forward_days }}</td>
                            <td class="py-2 pr-4">{{ $balance->used_days }}</td>
                            <td class="py-2 pr-4 font-medium">
                                {{ $balance->allocated_days + $balance->carried_forward_days - $balance->used_days }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    </div>
</div>
