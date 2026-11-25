@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'block w-full border-l-4 border-brand-600 bg-brand-50 py-2.5 ps-3 pe-4 text-start text-base font-semibold text-brand-900 transition'
        : 'block w-full border-l-4 border-transparent py-2.5 ps-3 pe-4 text-start text-base font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
