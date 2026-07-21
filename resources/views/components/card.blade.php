@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => "bg-white rounded-xl border border-slate-200 shadow-sm {$padding}"]) }}>
    {{ $slot }}
</div>
