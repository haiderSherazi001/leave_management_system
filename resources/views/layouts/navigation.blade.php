<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Brand -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight text-slate-800">
                        LeaveDesk
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('attendance.check-in')" :active="request()->routeIs('attendance.check-in')">
                        {{ __('Attendance') }}
                    </x-nav-link>
                    <x-nav-link :href="route('leave.apply')" :active="request()->routeIs('leave.apply')">
                        {{ __('Apply for Leave') }}
                    </x-nav-link>
                    <x-nav-link :href="route('leave.my-requests')" :active="request()->routeIs('leave.my-requests')">
                        {{ __('My Requests') }}
                    </x-nav-link>
                    @if (Auth::user()->isManager())
                        <x-nav-link :href="route('leave.approvals')" :active="request()->routeIs('leave.approvals')">
                            {{ __('Approvals') }}
                        </x-nav-link>
                        <x-nav-link :href="route('leave.team-calendar')" :active="request()->routeIs('leave.team-calendar')">
                            {{ __('Team Calendar') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->isHr())
                        <div class="hidden sm:flex sm:items-center h-full">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button type="button" class="inline-flex items-center h-full px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                        {{ __('Admin') }}
                                        <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('admin.employees')">{{ __('Employees') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.departments')">{{ __('Departments') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.leave-types')">{{ __('Leave Types') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.work-schedule')">{{ __('Work Schedule') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.holidays')">{{ __('Holidays') }}</x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('attendance.check-in')" :active="request()->routeIs('attendance.check-in')">
                {{ __('Attendance') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('leave.apply')" :active="request()->routeIs('leave.apply')">
                {{ __('Apply for Leave') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('leave.my-requests')" :active="request()->routeIs('leave.my-requests')">
                {{ __('My Requests') }}
            </x-responsive-nav-link>
            @if (Auth::user()->isManager())
                <x-responsive-nav-link :href="route('leave.approvals')" :active="request()->routeIs('leave.approvals')">
                    {{ __('Approvals') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('leave.team-calendar')" :active="request()->routeIs('leave.team-calendar')">
                    {{ __('Team Calendar') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->isHr())
                <x-responsive-nav-link :href="route('admin.employees')" :active="request()->routeIs('admin.employees')">
                    {{ __('Employees') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.departments')" :active="request()->routeIs('admin.departments')">
                    {{ __('Departments') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.leave-types')" :active="request()->routeIs('admin.leave-types')">
                    {{ __('Leave Types') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.work-schedule')" :active="request()->routeIs('admin.work-schedule')">
                    {{ __('Work Schedule') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.holidays')" :active="request()->routeIs('admin.holidays')">
                    {{ __('Holidays') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
