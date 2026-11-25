<button {{ $attributes->merge(['type' => 'submit', 'class' => 'mx-btn-primary text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
