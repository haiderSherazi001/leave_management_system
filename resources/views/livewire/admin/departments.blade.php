<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Departments') }}</h2>
            @unless ($showForm)
                <x-primary-button wire:click="startCreate">Add Department</x-primary-button>
            @endunless
        </div>

        @if ($showForm)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
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
                            <select id="managerId" wire:model="managerId" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
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
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Manager</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($departments as $department)
                        <tr wire:key="department-{{ $department->id }}">
                            <td class="py-2 pr-4">{{ $department->name }}</td>
                            <td class="py-2 pr-4">{{ $department->manager_name ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs font-medium',
                                    'bg-green-100 text-green-800' => $department->is_active,
                                    'bg-gray-200 text-gray-600' => ! $department->is_active,
                                ])>
                                    {{ $department->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <button wire:click="edit({{ $department->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="toggleActive({{ $department->id }})"
                                    wire:confirm="{{ $department->is_active ? 'Deactivate this department?' : 'Reactivate this department?' }}"
                                    class="ms-3 text-sm font-medium {{ $department->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}"
                                >
                                    {{ $department->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
