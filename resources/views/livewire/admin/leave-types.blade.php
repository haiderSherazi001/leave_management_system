<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Leave Types') }}</h2>
            @unless ($showForm)
                <x-primary-button wire:click="startCreate">Add Leave Type</x-primary-button>
            @endunless
        </div>

        @if ($showForm)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ $editingId === null ? 'Add Leave Type' : 'Edit Leave Type' }}
                </h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" wire:model="name" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="code" value="Code" />
                            <x-text-input id="code" wire:model="code" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="yearlyAllocationDays" value="Yearly Allocation (days)" />
                            <x-text-input id="yearlyAllocationDays" type="number" min="0" max="365" wire:model="yearlyAllocationDays" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('yearlyAllocationDays')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="carryForwardMaxDays" value="Carry-Forward Max (days)" />
                            <x-text-input id="carryForwardMaxDays" type="number" min="0" max="365" wire:model="carryForwardMaxDays" class="mt-1 block w-full" :disabled="! $carryForwardEnabled" />
                            <x-input-error :messages="$errors->get('carryForwardMaxDays')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input id="carryForwardEnabled" type="checkbox" wire:model.live="carryForwardEnabled" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <label for="carryForwardEnabled" class="ms-2 text-sm text-gray-600">Allow unused days to carry forward to the next year</label>
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" wire:model="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button>Save</x-primary-button>
                        <x-secondary-button wire:click="cancel">Cancel</x-secondary-button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Code</th>
                        <th class="py-2 pr-4">Yearly Allocation</th>
                        <th class="py-2 pr-4">Carry Forward</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($leaveTypes as $leaveType)
                        <tr wire:key="leave-type-{{ $leaveType->id }}">
                            <td class="py-2 pr-4">{{ $leaveType->name }}</td>
                            <td class="py-2 pr-4">{{ $leaveType->code }}</td>
                            <td class="py-2 pr-4">{{ $leaveType->yearly_allocation_days }}</td>
                            <td class="py-2 pr-4">
                                @if ($leaveType->carry_forward_enabled)
                                    Up to {{ $leaveType->carry_forward_max_days }} day{{ $leaveType->carry_forward_max_days == 1 ? '' : 's' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pr-4">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs font-medium',
                                    'bg-green-100 text-green-800' => $leaveType->is_active,
                                    'bg-gray-200 text-gray-600' => ! $leaveType->is_active,
                                ])>
                                    {{ $leaveType->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <button wire:click="edit({{ $leaveType->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="toggleActive({{ $leaveType->id }})"
                                    wire:confirm="{{ $leaveType->is_active ? 'Deactivate this leave type? Employees will no longer be able to request it.' : 'Reactivate this leave type?' }}"
                                    class="ms-3 text-sm font-medium {{ $leaveType->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}"
                                >
                                    {{ $leaveType->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
