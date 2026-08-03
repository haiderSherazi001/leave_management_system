<x-slot name="header">{{ __('Leave Types') }}</x-slot>

<div class="space-y-6">
    <div class="flex items-center justify-end">
        @unless ($showForm)
            <x-primary-button wire:click="startCreate">Add Leave Type</x-primary-button>
        @endunless
    </div>

    @if ($showForm)
        <x-card data-autofocus-form>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">
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
                    <input id="carryForwardEnabled" type="checkbox" wire:model.live="carryForwardEnabled" class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                    <label for="carryForwardEnabled" class="ms-2 text-sm text-slate-600">Allow unused days to carry forward to the next year</label>
                </div>

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <textarea id="description" wire:model="description" rows="2" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div class="flex gap-2">
                    <x-primary-button>Save</x-primary-button>
                    <x-secondary-button wire:click="cancel">Cancel</x-secondary-button>
                </div>
            </form>
        </x-card>
    @endif

    <x-card>
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="search" value="Search" />
                <x-text-input id="search" type="search" wire:model.live.debounce.400ms="search" placeholder="Name or code…" class="mt-1 block w-full" />
            </div>

            <div>
                <x-input-label for="statusFilter" value="Status" />
                <select id="statusFilter" wire:model.live="statusFilter" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            @if ($search !== '' || $statusFilter !== '')
                <x-secondary-button wire:click="clearFilters">Clear</x-secondary-button>
            @endif
        </div>
    </x-card>

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-3 px-6 font-medium">Name</th>
                        <th class="py-3 px-6 font-medium">Code</th>
                        <th class="py-3 px-6 font-medium">Yearly Allocation</th>
                        <th class="py-3 px-6 font-medium">Carry Forward</th>
                        <th class="py-3 px-6 font-medium">Status</th>
                        <th class="py-3 px-6"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leaveTypes as $leaveType)
                        <tr wire:key="leave-type-{{ $leaveType->id }}" class="hover:bg-slate-50">
                            <td class="py-3 px-6 font-medium text-slate-900">{{ $leaveType->name }}</td>
                            <td class="py-3 px-6 text-slate-600">{{ $leaveType->code }}</td>
                            <td class="py-3 px-6 text-slate-600">{{ $leaveType->yearly_allocation_days }}</td>
                            <td class="py-3 px-6 text-slate-600">
                                @if ($leaveType->carry_forward_enabled)
                                    Up to {{ $leaveType->carry_forward_max_days }} day{{ $leaveType->carry_forward_max_days == 1 ? '' : 's' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-6">
                                <x-badge :color="$leaveType->is_active ? 'emerald' : 'slate'">
                                    {{ $leaveType->is_active ? 'Active' : 'Inactive' }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-6 whitespace-nowrap">
                                <button wire:click="edit({{ $leaveType->id }})" class="text-teal-600 hover:text-teal-800 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="toggleActive({{ $leaveType->id }})"
                                    wire:confirm="{{ $leaveType->is_active ? 'Deactivate this leave type? Employees will no longer be able to request it.' : 'Reactivate this leave type?' }}"
                                    class="ms-3 text-sm font-medium {{ $leaveType->is_active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}"
                                >
                                    {{ $leaveType->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 px-6 text-sm text-slate-500">No leave types match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
