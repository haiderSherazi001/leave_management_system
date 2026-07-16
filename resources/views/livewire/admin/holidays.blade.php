<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Holidays') }}</h2>
            @unless ($showForm)
                <x-primary-button wire:click="startCreate">Add Holiday</x-primary-button>
            @endunless
        </div>

        @if ($showForm)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ $editingId === null ? 'Add Holiday' : 'Edit Holiday' }}
                </h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="date" value="Date" />
                            <x-text-input id="date" type="date" wire:model="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" wire:model="name" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
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
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($holidays as $holiday)
                        <tr wire:key="holiday-{{ $holiday->id }}">
                            <td class="py-2 pr-4">{{ \Illuminate\Support\Carbon::parse($holiday->date)->format('M j, Y') }}</td>
                            <td class="py-2 pr-4">{{ $holiday->name }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <button wire:click="edit({{ $holiday->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="delete({{ $holiday->id }})"
                                    wire:confirm="Delete this holiday?"
                                    class="ms-3 text-sm font-medium text-red-600 hover:text-red-900"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 text-sm text-gray-500">No holidays configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
