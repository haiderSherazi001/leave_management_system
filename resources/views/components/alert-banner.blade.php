@props(['type' => 'error'])

@php
    $styles = $type === 'success'
        ? 'bg-emerald-50 text-emerald-700 focus:ring-emerald-500'
        : 'bg-red-50 text-red-700 focus:ring-red-500';
@endphp

{{--
    wire:key gets a fresh random value every render so Livewire's diffing
    always treats this as a brand new element (even if the message text is
    identical to last time) - that's what makes x-init re-fire and move
    focus here on every button press, not just the first time the alert
    ever appears.
--}}
<div
    {{ $attributes->merge(['class' => "rounded-lg p-4 text-sm outline-none focus:ring-2 focus:ring-offset-2 $styles"]) }}
    role="{{ $type === 'success' ? 'status' : 'alert' }}"
    tabindex="-1"
    wire:key="alert-{{ \Illuminate\Support\Str::random(8) }}"
    x-init="$el.focus()"
>
    {{ $slot }}
</div>
