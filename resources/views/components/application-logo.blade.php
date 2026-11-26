@props([
    'variant' => 'header',
])

@php
    $src = $variant === 'footer'
        ? asset('images/logo-rodape.png')
        : asset('images/logo.png');
    $alt = (string) config('app.name', 'Mais XML');
    $defaultClass = $variant === 'footer'
        ? 'block w-auto max-w-full object-contain object-left sm:h-6'
        : 'block w-auto max-w-full object-contain object-left';
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    width="119"
    height="20"
    decoding="async"
    {{ $attributes->merge(['class' => $defaultClass]) }}
/>
