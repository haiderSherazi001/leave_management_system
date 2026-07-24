<x-slot name="header">{{ __('Employees') }}</x-slot>

<div class="space-y-6">
    <div class="flex items-center justify-end">
        @unless ($showForm)
            <x-primary-button wire:click="startCreate">Add Employee</x-primary-button>
        @endunless
    </div>

    @if ($showForm)
        <x-card data-autofocus-form>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">
                {{ $editingId === null ? 'Add Employee' : 'Edit Employee' }}
            </h3>

            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" wire:model="name" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" type="email" wire:model="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="password" :value="$editingId === null ? 'Password' : 'New Password (leave blank to keep current)'" />
                    <x-text-input id="password" type="password" wire:model="password" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" wire:model.live="role" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                            @foreach ($roles as $roleOption)
                                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="departmentId" value="Department" />
                        <select id="departmentId" wire:model="departmentId" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                            <option value="">None</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">
                                    {{ $department->name }}{{ $department->is_active ? '' : ' (inactive)' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('departmentId')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="managerId" value="Manager" />
                        <select
                            id="managerId"
                            wire:model="managerId"
                            @disabled($role === 'hr')
                            class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm disabled:bg-slate-100 disabled:text-slate-400"
                        >
                            <option value="">None</option>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->id }}">
                                    {{ $manager->name }}{{ $manager->is_active ? '' : ' (inactive)' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($role === 'hr')
                            <p class="mt-1 text-xs text-slate-500">HR accounts do not have a manager.</p>
                        @endif
                        <x-input-error :messages="$errors->get('managerId')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="joinedAt" value="Join Date" />
                    <x-text-input id="joinedAt" type="date" wire:model="joinedAt" class="mt-1 block w-full sm:w-1/3" />
                    <x-input-error :messages="$errors->get('joinedAt')" class="mt-2" />
                </div>

                <div class="flex gap-2">
                    <x-primary-button>Save</x-primary-button>
                    <x-secondary-button type="button" wire:click="cancel">Cancel</x-secondary-button>
                </div>
            </form>
        </x-card>
    @endif

    @if ($errorMessage)
        <x-alert-banner type="error">{{ $errorMessage }}</x-alert-banner>
    @endif

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-3 px-6 font-medium">Name</th>
                        <th class="py-3 px-6 font-medium">Email</th>
                        <th class="py-3 px-6 font-medium">Role</th>
                        <th class="py-3 px-6 font-medium">Department</th>
                        <th class="py-3 px-6 font-medium">Manager</th>
                        <th class="py-3 px-6 font-medium">Joined</th>
                        <th class="py-3 px-6 font-medium">Status</th>
                        <th class="py-3 px-6"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($employees as $employee)
                        <tr wire:key="employee-{{ $employee->id }}" class="hover:bg-slate-50">
                            <td class="py-3 px-6 font-medium text-slate-900">{{ $employee->name }}</td>
                            <td class="py-3 px-6 text-slate-600">{{ $employee->email }}</td>
                            <td class="py-3 px-6 text-slate-600">{{ ucfirst($employee->role) }}</td>
                            <td class="py-3 px-6 text-slate-600">
                                {{ $employee->department_name ?? '—' }}
                                @if ($employee->department_name && ! $employee->department_is_active)
                                    <x-badge color="red" class="ml-1">Deactivated</x-badge>
                                @endif
                            </td>
                            <td class="py-3 px-6 text-slate-600">
                                {{ $employee->manager_name ?? '—' }}
                                @if ($employee->manager_name && ! $employee->manager_is_active)
                                    <x-badge color="red" class="ml-1">Deactivated</x-badge>
                                @endif
                            </td>
                            <td class="py-3 px-6 text-slate-600">{{ \Illuminate\Support\Carbon::parse($employee->joined_at)->format('M j, Y') }}</td>
                            <td class="py-3 px-6">
                                <x-badge :color="$employee->is_active ? 'emerald' : 'slate'">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-6 whitespace-nowrap">
                                <button wire:click="edit({{ $employee->id }})" class="text-teal-600 hover:text-teal-800 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="toggleActive({{ $employee->id }})"
                                    wire:confirm="{{ $employee->is_active ? 'Deactivate this employee? They will no longer be able to log in.' : 'Reactivate this employee?' }}"
                                    class="ms-3 text-sm font-medium {{ $employee->is_active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}"
                                >
                                    {{ $employee->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</div>
