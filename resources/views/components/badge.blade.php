@props(['color' => 'slate'])

@php
$colors = [
    'emerald' => 'bg-emerald-100 text-emerald-700',
    'amber' => 'bg-amber-100 text-amber-700',
    'red' => 'bg-red-100 text-red-700',
    'teal' => 'bg-teal-100 text-teal-700',
    'slate' => 'bg-slate-100 text-slate-600',
];
$colorClasses = $colors[$color] ?? $colors['slate'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {$colorClasses}"]) }}>
    {{ $slot }}
</span>
