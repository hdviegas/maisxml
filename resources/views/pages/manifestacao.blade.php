<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-900 dark:text-gray-100 leading-tight">
            {{ __('manifestacao.title') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6 text-sm text-gray-700 dark:text-gray-300">
            <p>{{ __('manifestacao.p1') }}</p>
            <p>{{ __('manifestacao.p2') }}</p>
            <ul class="list-disc space-y-2 ps-5">
                <li>{{ __('manifestacao.li1') }}</li>
                <li>{{ __('manifestacao.li2') }}</li>
                <li>{{ __('manifestacao.li3') }}</li>
            </ul>
            <p class="rounded-md border border-teal-200/80 bg-teal-50 px-4 py-3 text-teal-950 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-100">
                {{ __('manifestacao.planned') }}
            </p>
            <p>
                <a href="{{ route('documents.index') }}" class="font-medium text-teal-700 hover:underline dark:text-teal-400">{{ __('manifestacao.back_docs') }}</a>
            </p>
        </div>
    </div>
</x-app-layout>
