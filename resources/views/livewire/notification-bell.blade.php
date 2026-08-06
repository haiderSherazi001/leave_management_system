<div wire:poll.30s>
    <x-dropdown align="right" width="w-80">
        <x-slot name="trigger">
            <button class="relative flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                @if ($unreadCount > 0)
                    <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                    </span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="flex items-center justify-between px-4 py-2 border-b border-slate-100">
                <span class="text-sm font-semibold text-slate-900">Notifications</span>
                @if ($unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-teal-600 hover:text-teal-800">
                        Mark all as read
                    </button>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($notifications as $notification)
                    @include('livewire.partials.notification-item')
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-400">No notifications yet.</p>
                @endforelse
            </div>

            <a href="{{ route('notifications.index') }}" wire:navigate class="block px-4 py-2.5 text-center text-sm font-medium text-teal-600 hover:text-teal-800 border-t border-slate-100">
                View all
            </a>
        </x-slot>
    </x-dropdown>
</div>
