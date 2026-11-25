<button {{ $attributes->merge(['type' => 'button', 'class' => 'mx-btn-secondary text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
