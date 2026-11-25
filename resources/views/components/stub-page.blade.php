@props([
    'title',
    'description',
])

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-900 leading-tight">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-medium">{{ __('Under construction') }}</p>
                <p class="mt-1 text-amber-800">{{ $description }}</p>
                <p class="mt-4">
                    <a href="{{ route('dashboard') }}" class="text-teal-700 font-medium hover:underline">{{ __('Back to dashboard') }}</a>
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
