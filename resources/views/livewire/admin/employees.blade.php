<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Employees') }}</h2>
            @unless ($showForm)
                <x-primary-button wire:click="startCreate">Add Employee</x-primary-button>
            @endunless
        </div>

        @if ($showForm)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
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
                            <select id="role" wire:model.live="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($roles as $roleOption)
                                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('role')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="departmentId" value="Department" />
                            <select id="departmentId" wire:model="departmentId" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
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
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">None</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}">
                                        {{ $manager->name }}{{ $manager->is_active ? '' : ' (inactive)' }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($role === 'hr')
                                <p class="mt-1 text-xs text-gray-500">HR accounts do not have a manager.</p>
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
            </div>
        @endif

        @if ($errorMessage)
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                {{ $errorMessage }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Role</th>
                        <th class="py-2 pr-4">Department</th>
                        <th class="py-2 pr-4">Manager</th>
                        <th class="py-2 pr-4">Joined</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($employees as $employee)
                        <tr wire:key="employee-{{ $employee->id }}">
                            <td class="py-2 pr-4">{{ $employee->name }}</td>
                            <td class="py-2 pr-4">{{ $employee->email }}</td>
                            <td class="py-2 pr-4">{{ ucfirst($employee->role) }}</td>
                            <td class="py-2 pr-4">
                                {{ $employee->department_name ?? '—' }}
                                @if ($employee->department_name && ! $employee->department_is_active)
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Deactivated</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">
                                {{ $employee->manager_name ?? '—' }}
                                @if ($employee->manager_name && ! $employee->manager_is_active)
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Deactivated</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ \Illuminate\Support\Carbon::parse($employee->joined_at)->format('M j, Y') }}</td>
                            <td class="py-2 pr-4">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs font-medium',
                                    'bg-green-100 text-green-800' => $employee->is_active,
                                    'bg-gray-200 text-gray-600' => ! $employee->is_active,
                                ])>
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <button wire:click="edit({{ $employee->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="toggleActive({{ $employee->id }})"
                                    wire:confirm="{{ $employee->is_active ? 'Deactivate this employee? They will no longer be able to log in.' : 'Reactivate this employee?' }}"
                                    class="ms-3 text-sm font-medium {{ $employee->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}"
                                >
                                    {{ $employee->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
