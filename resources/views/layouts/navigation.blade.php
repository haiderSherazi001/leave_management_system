@php
    // Same destination the post-login/email-verification redirects use
    // (User::homeRouteName()) — keeps the sidebar and the auth flow agreeing
    // on where "home" is for a given role.
    $dashboardRoute = Auth::user()->homeRouteName();
@endphp

<!-- Mobile backdrop -->
<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-linear duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
    style="display: none;"
    @click="sidebarOpen = false"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 -translate-x-full transform flex-col bg-slate-900 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
    :class="sidebarOpen && 'translate-x-0'"
>
    <div class="flex h-16 shrink-0 items-center justify-between px-5">
        <a href="{{ route($dashboardRoute) }}" class="text-lg font-bold tracking-tight text-white">
            Leave<span class="text-teal-400">Desk</span>
        </a>
        <button type="button" class="text-slate-400 hover:text-white lg:hidden" @click="sidebarOpen = false">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        <div class="space-y-1">
            <x-nav-link :href="route($dashboardRoute)" :active="request()->routeIs($dashboardRoute)">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                {{ __('Dashboard') }}
            </x-nav-link>
            <x-nav-link :href="route('attendance.check-in')" :active="request()->routeIs('attendance.check-in')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ __('Attendance') }}
            </x-nav-link>
            <x-nav-link :href="route('leave.apply')" :active="request()->routeIs('leave.apply')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.876L5.999 12zm0 0h7.5" />
                </svg>
                {{ __('Apply for Leave') }}
            </x-nav-link>
            <x-nav-link :href="route('leave.my-requests')" :active="request()->routeIs('leave.my-requests')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75M8.25 6.75h7.5c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 014.875 19.125V7.875c0-.621.504-1.125 1.125-1.125h2.25m0-1.5v1.5m0-1.5A1.125 1.125 0 019.375 4.5h.75c.621 0 1.125.504 1.125 1.125m-2.25 0v1.5c0 .621.504 1.125 1.125 1.125h.75c.621 0 1.125-.504 1.125-1.125v-1.5" />
                </svg>
                {{ __('My Requests') }}
            </x-nav-link>
        </div>

        @if (Auth::user()->isManager())
            <div class="space-y-1">
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Manager') }}</p>
                <x-nav-link :href="route('leave.approvals')" :active="request()->routeIs('leave.approvals')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Approvals') }}
                </x-nav-link>
                <x-nav-link :href="route('leave.team-calendar')" :active="request()->routeIs('leave.team-calendar')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    {{ __('Team Calendar') }}
                </x-nav-link>
            </div>
        @endif

        @if (Auth::user()->isHr())
            <div class="space-y-1">
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('HR / Admin') }}</p>
                <x-nav-link :href="route('admin.leave-approvals')" :active="request()->routeIs('admin.leave-approvals')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M2.25 13.5v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6M2.25 13.5V9a2.25 2.25 0 012.25-2.25h.879a1.5 1.5 0 001.06-.44l2.122-2.12a1.5 1.5 0 011.06-.44h6.256a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44h.879A2.25 2.25 0 0121.75 9v4.5" />
                    </svg>
                    {{ __('Leave Approvals') }}
                </x-nav-link>
                <x-nav-link :href="route('admin.employees')" :active="request()->routeIs('admin.employees')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    {{ __('Employees') }}
                </x-nav-link>
                <x-nav-link :href="route('admin.departments')" :active="request()->routeIs('admin.departments')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                    {{ __('Departments') }}
                </x-nav-link>
                <x-nav-link :href="route('admin.leave-types')" :active="request()->routeIs('admin.leave-types')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z" />
                    </svg>
                    {{ __('Leave Types') }}
                </x-nav-link>
                <x-nav-link :href="route('admin.work-schedule')" :active="request()->routeIs('admin.work-schedule')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.397-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{ __('Work Schedule') }}
                </x-nav-link>
                <x-nav-link :href="route('admin.holidays')" :active="request()->routeIs('admin.holidays')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-13.5 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008V21zm3-3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008V21zm3-3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008V21z" />
                    </svg>
                    {{ __('Holidays') }}
                </x-nav-link>
            </div>
        @endif
    </nav>
</aside>
