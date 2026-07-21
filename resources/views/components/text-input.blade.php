@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-teal-500 focus:ring-teal-500 rounded-lg shadow-sm disabled:bg-slate-100 disabled:text-slate-400']) }}>
