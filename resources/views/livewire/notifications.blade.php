<x-slot name="header">{{ __('Notifications') }}</x-slot>

<div class="space-y-6">
    <x-card padding="p-0" class="overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <span class="text-sm text-slate-500">{{ $notifications->total() }} total</span>
            <button type="button" wire:click="markAllAsRead" class="text-sm font-medium text-teal-600 hover:text-teal-800">
                Mark all as read
            </button>
        </div>

        @forelse ($notifications as $notification)
            @include('livewire.partials.notification-item')
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">No notifications yet.</p>
        @endforelse
    </x-card>

    {{ $notifications->links() }}
</div>
