<x-slot name="header">{{ __('Holidays') }}</x-slot>

<div class="space-y-6">
    <div class="flex items-center justify-end">
        @unless ($showForm)
            <x-primary-button wire:click="startCreate">Add Holiday</x-primary-button>
        @endunless
    </div>

    @if ($showForm)
        <x-card data-autofocus-form>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">
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
        </x-card>
    @endif

    <x-card>
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="search" value="Search" />
                <x-text-input id="search" type="search" wire:model.live.debounce.400ms="search" placeholder="Holiday name…" class="mt-1 block w-full" />
            </div>

            <div>
                <x-input-label for="yearFilter" value="Year" />
                <select id="yearFilter" wire:model.live="yearFilter" class="mt-1 block w-full border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm">
                    <option value="">All years</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            @if ($search !== '' || $yearFilter !== null)
                <x-secondary-button wire:click="clearFilters">Clear</x-secondary-button>
            @endif
        </div>
    </x-card>

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-3 px-6 font-medium">Date</th>
                        <th class="py-3 px-6 font-medium">Name</th>
                        <th class="py-3 px-6"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($holidays as $holiday)
                        <tr wire:key="holiday-{{ $holiday->id }}" class="hover:bg-slate-50">
                            <td class="py-3 px-6 text-slate-600">{{ \Illuminate\Support\Carbon::parse($holiday->date)->format('M j, Y') }}</td>
                            <td class="py-3 px-6 font-medium text-slate-900">{{ $holiday->name }}</td>
                            <td class="py-3 px-6 whitespace-nowrap">
                                <button wire:click="edit({{ $holiday->id }})" class="text-teal-600 hover:text-teal-800 text-sm font-medium">
                                    Edit
                                </button>
                                <button
                                    wire:click="delete({{ $holiday->id }})"
                                    wire:confirm="Delete this holiday?"
                                    class="ms-3 text-sm font-medium text-red-600 hover:text-red-800"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 px-6 text-sm text-slate-500">
                                {{ $search !== '' || $yearFilter !== null ? 'No holidays match these filters.' : 'No holidays configured yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
