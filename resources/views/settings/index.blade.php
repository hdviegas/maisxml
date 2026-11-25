<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-page">
            <div class="space-y-10">
                <p class="text-sm text-slate-600">{{ __('settings.intro') }}</p>

                @forelse ($organizations as $org)
                    <div class="mx-card overflow-hidden">
                        <div class="mx-card-header">
                            <h3 class="text-base font-semibold text-slate-900">{{ $org->name }}</h3>
                            @if ($org->trade_name)
                                <p class="text-sm text-slate-600">{{ $org->trade_name }}</p>
                            @endif
                            <p class="mt-1 text-sm tabular-nums text-slate-700">{{ __('settings.cnpj') }}:
                                {{ $org->cnpj }}</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th
                                            class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">{{ __('settings.col_branch') }}</th>
                                        <th
                                            class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">{{ __('settings.col_branch_cnpj') }}</th>
                                        <th
                                            class="px-4 py-2 text-center text-xs font-semibold uppercase text-slate-500">{{ __('settings.col_entrada') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($org->branches->sortBy('name') as $branch)
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-2 text-slate-900">{{ $branch->name }}
                                            </td>
                                            <td class="px-4 py-2 tabular-nums text-slate-700">
                                                {{ $branch->cnpj }}</td>
                                            <td class="px-4 py-2 text-center">
                                                @if ($branch->entrada_enabled)
                                                    <span
                                                        class="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-900">{{ __('settings.entrada_yes') }}</span>
                                                @else
                                                    <span class="text-slate-400">{{ __('settings.entrada_no') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No organizations for user') }}</p>
                @endforelse

                <div
                    class="rounded-lg border border-dashed border-slate-300 bg-slate-50/80 px-4 py-4 text-sm text-slate-600">
                    <p class="font-medium text-slate-800">{{ __('settings.certificates_heading') }}</p>
                    <p class="mt-1">{{ __('settings.certificates_hint') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
