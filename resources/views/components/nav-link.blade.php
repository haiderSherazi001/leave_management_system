@props(['active' => false])

@php
$classes = $active
    ? 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium bg-slate-800 text-white'
    : 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/60 hover:text-white transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
