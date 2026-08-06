<x-slot name="header">{{ __('Apply for Leave') }}</x-slot>

<div class="space-y-6">
    @if (count($upcomingHolidays) > 0)
        <x-card>
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Upcoming Holidays</h3>
            <p class="text-xs text-slate-500 mb-3">The office is closed on these days — no need to apply for leave.</p>

            <ul class="divide-y divide-slate-100">
                @foreach ($upcomingHolidays as $holiday)
                    <li class="py-2.5 flex items-center justify-between gap-3" wire:key="upcoming-holiday-{{ $holiday->id }}">
                        <span class="text-sm text-slate-700">{{ $holiday->name }}</span>
                        <span class="shrink-0 text-xs font-medium text-slate-500">
                            {{ \Illuminate\Support\Carbon::parse($holiday->date)->format('M j, Y') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card>
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Apply for Leave</h3>

        @if ($successMessage)
            <x-alert-banner type="success" class="mb-4">{{ $successMessage }}</x-alert-banner>
        @endif

        @error('form')
            <x-alert-banner type="error" class="mb-4">{{ $message }}</x-alert-banner>
        @enderror

        <form wire:submit="submit" class="space-y-4">
            <div>
                <x-input-label for="leaveTypeId" value="Leave Type" />
                <select id="leaveTypeId" wire:model="leaveTypeId" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
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
                <input id="isHalfDay" type="checkbox" wire:model="isHalfDay" class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                <label for="isHalfDay" class="ms-2 text-sm text-slate-600">Half day</label>
            </div>

            <div>
                <x-input-label for="reason" value="Reason" />
                <textarea id="reason" wire:model="reason" rows="3" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm"></textarea>
                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
            </div>

            <x-primary-button>Submit Request</x-primary-button>
        </form>
    </x-card>

    <x-card>
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Your Leave Balance ({{ now()->year }})</h3>

        @if (count($balances) === 0)
            <p class="text-sm text-slate-500">
                No leave balances have been set up for you yet.
                @unless (Auth::user()->isHr())
                    Contact HR.
                @endunless
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-4 font-medium">Leave Type</th>
                            <th class="py-2 pr-4 font-medium">Allocated</th>
                            <th class="py-2 pr-4 font-medium">Carried Forward</th>
                            <th class="py-2 pr-4 font-medium">Used</th>
                            <th class="py-2 pr-4 font-medium">Remaining</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($balances as $balance)
                            <tr>
                                <td class="py-2.5 pr-4">{{ $balance->leave_type_name }}</td>
                                <td class="py-2.5 pr-4">{{ $balance->allocated_days }}</td>
                                <td class="py-2.5 pr-4">{{ $balance->carried_forward_days }}</td>
                                <td class="py-2.5 pr-4">{{ $balance->used_days }}</td>
                                <td class="py-2.5 pr-4 font-semibold text-slate-900">
                                    {{ $balance->allocated_days + $balance->carried_forward_days - $balance->used_days }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
