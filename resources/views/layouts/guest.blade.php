<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Mais XML') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 antialiased">
        <div class="flex min-h-screen flex-col items-center bg-gradient-to-b from-slate-100 via-white to-slate-50">
            {{-- Logo + formulário centralizados no espaço livre; o rodapé fica embaixo sem empurrá-los ao topo. --}}
            <main class="flex w-full flex-1 flex-col items-center justify-center px-4 py-12">
                <a href="/" class="block rounded-xl outline-none ring-brand-500/0 transition hover:ring-2 focus-visible:ring-2">
                    <x-application-logo />
                </a>

                <div class="mx-card mt-8 w-full px-8 py-7 sm:max-w-md">
                    {{ $slot }}
                </div>
            </main>

            <footer class="flex justify-center px-4 py-8">
                <x-application-logo variant="footer" />
            </footer>
        </div>
    </body>
</html>
