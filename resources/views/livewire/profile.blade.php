@php
    $user = Auth::user();
@endphp

<x-slot name="header">{{ __('Profile') }}</x-slot>

<div class="space-y-6">
    <x-card>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Personal Information</h3>
            <x-badge color="teal">{{ $user->role->label() }}</x-badge>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-slate-500">Name</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">Email</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">Department</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $user->department?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">Manager</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $user->manager?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">Joined</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $user->joined_at?->format('M j, Y') ?? '—' }}</dd>
            </div>
        </dl>

        @if ($user->isHr())
            <p class="mt-4 text-xs text-slate-400">
                You can update these via <a href="{{ route('admin.employees') }}" class="underline hover:text-slate-600">Admin → Employees</a>.
            </p>
        @else
            <p class="mt-4 text-xs text-slate-400">Contact HR to update these details.</p>
        @endif
    </x-card>

    <x-card>
        <h3 class="text-lg font-semibold text-slate-900 mb-4">My Leave Balances ({{ now()->year }})</h3>

        @if (count($balances) === 0)
            <p class="text-sm text-slate-500">
                No leave balances have been set up for you yet.
                @unless ($user->isHr())
                    Contact HR.
                @endunless
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-4 font-medium">Leave Type</th>
                            <th class="py-2 pr-4 font-medium">Allocated</th>
                            <th class="py-2 pr-4 font-medium">Carried Forward</th>
                            <th class="py-2 pr-4 font-medium">Used</th>
                            <th class="py-2 pr-4 font-medium">Remaining</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($balances as $balance)
                            <tr wire:key="balance-{{ $balance->id }}">
                                <td class="py-2.5 pr-4">{{ $balance->leave_type_name }}</td>
                                <td class="py-2.5 pr-4">{{ $balance->allocated_days }}</td>
                                <td class="py-2.5 pr-4">{{ $balance->carried_forward_days }}</td>
                                <td class="py-2.5 pr-4">{{ $balance->used_days }}</td>
                                <td class="py-2.5 pr-4 font-semibold text-slate-900">
                                    {{ $balance->allocated_days + $balance->carried_forward_days - $balance->used_days }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if (count($teamMembers) > 0)
        <x-card>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">My Team</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-4 font-medium">Name</th>
                            <th class="py-2 pr-4 font-medium">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($teamMembers as $member)
                            <tr wire:key="team-member-{{ $member->id }}">
                                <td class="py-2.5 pr-4">{{ $member->name }}</td>
                                <td class="py-2.5 pr-4">{{ $member->role->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <x-card class="max-w-xl">
        @include('profile.partials.update-password-form')
    </x-card>
</div>
