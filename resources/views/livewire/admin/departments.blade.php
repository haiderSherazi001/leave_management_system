<x-slot name="header">{{ __('Departments') }}</x-slot>

<div class="space-y-6">
    <div class="flex items-center justify-end">
        @unless ($showForm)
            <x-primary-button wire:click="startCreate">Add Department</x-primary-button>
        @endunless
    </div>

    @if ($showForm)
        <x-card data-autofocus-form>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">
                {{ $editingId === null ? 'Add Department' : 'Edit Department' }}
            </h3>

            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" wire:model="name" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="managerId" value="Department Manager" />
                        <select id="managerId" wire:model="managerId" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                            <option value="">None</option>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->id }}">
                                    {{ $manager->name }}{{ $manager->is_active ? '' : ' (inactive)' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('managerId')" class="mt-2" />
                    </div>
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
                <x-text-input id="search" type="search" wire:model.live.debounce.400ms="search" placeholder="Department name…" class="mt-1 block w-full" />
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
                        <th class="py-3 px-6 font-medium">Manager</th>
                        <th class="py-3 px-6 font-medium">Status</th>
                        <th class="py-3 px-6"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($departments as $department)
                        <tr wire:key="department-{{ $department->id }}" class="hover:bg-slate-50">
                            <td class="py-3 px-6 font-medium text-slate-900">{{ $department->name }}</td>
                            <td class="py-3 px-6 text-slate-600">
                                {{ $department->manager_name ?? '—' }}
                                @if ($department->manager_name && ! $department->manager_is_active)
                                    <x-badge color="red" class="ml-1">Deactivated</x-badge>
                                @endif
                            </td>
                            <td class="py-3 px-6">
                                <x-badge :color="$department->is_active ? 'emerald' : 'slate'">
                                    {{ $department->is_active ? 'Active' : 'Inactive' }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-6 whitespace-nowrap">
                                <button wire:click="edit({{ $department->id }})" class="text-teal-600 hover:text-teal-800 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="toggleActive({{ $department->id }})"
                                    wire:confirm="{{ $department->is_active ? 'Deactivate this department?' : 'Reactivate this department?' }}"
                                    class="ms-3 text-sm font-medium {{ $department->is_active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}"
                                >
                                    {{ $department->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 px-6 text-sm text-slate-500">No departments match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
