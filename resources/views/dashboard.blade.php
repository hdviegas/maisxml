@php
    $issuedFromFmt = \Illuminate\Support\Carbon::parse($issuedFrom)->format('d/m/Y');
    $issuedToFmt = \Illuminate\Support\Carbon::parse($issuedTo)->format('d/m/Y');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="mx-section-title">{{ __('Dashboard') }}</p>
            <h2 class="mx-page-title mt-1">{{ __('dashboard.period_scope') }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-page space-y-6">
            <div class="mx-card overflow-hidden p-0">
                <div class="border-b border-slate-100 bg-slate-50/60 px-6 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm leading-relaxed text-slate-600">{{ __('dashboard.period_scope_hint') }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">
                                <span class="tabular-nums text-slate-900">{{ $issuedFromFmt }}</span>
                                <span class="mx-2 text-slate-400">—</span>
                                <span class="tabular-nums text-slate-900">{{ $issuedToFmt }}</span>
                            </p>
                        </div>
                    </div>

                    <form method="get" action="{{ route('dashboard') }}" class="mt-5 flex flex-wrap items-end gap-4">
                        <div>
                            <label for="issued_from" class="mx-label">{{ __('dashboard.issued_from') }}</label>
                            <input type="date" name="issued_from" id="issued_from" value="{{ $issuedFrom }}"
                                class="mx-input w-auto min-w-[10.5rem]" />
                        </div>
                        <div>
                            <label for="issued_to" class="mx-label">{{ __('dashboard.issued_to') }}</label>
                            <input type="date" name="issued_to" id="issued_to" value="{{ $issuedTo }}"
                                class="mx-input w-auto min-w-[10.5rem]" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="mx-btn-primary">{{ __('dashboard.apply_period') }}</button>
                            <a href="{{ route('dashboard') }}" class="mx-btn-secondary">{{ __('dashboard.reset_period') }}</a>
                        </div>
                    </form>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('dashboard.quick_presets') }}</span>
                        @foreach ($periodPresets as $preset)
                            <a href="{{ route('dashboard', ['issued_from' => $preset['issued_from'], 'issued_to' => $preset['issued_to']]) }}"
                                class="mx-preset-link">{{ trans_choice('dashboard.preset_days', $preset['days'], ['count' => $preset['days']]) }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="px-6 py-6">
                    <dl class="grid gap-4 lg:grid-cols-3">
                        <div class="mx-kpi-card pl-5">
                            <dt class="mx-kpi-title">{{ __('dashboard.stat_documents') }}</dt>
                            <dd class="mx-kpi-value">{{ number_format($documentsTotal, 0, ',', '.') }}</dd>
                            <dd class="mt-3">
                                <a href="{{ $documentsIndexUrl }}" class="mx-link-action">{{ __('dashboard.open_list') }} →</a>
                            </dd>
                        </div>
                        <div class="mx-kpi-card mx-kpi-card--warn pl-5">
                            <dt class="mx-kpi-title text-amber-800/90">{{ __('dashboard.stat_sped_diff') }}</dt>
                            <dd class="mx-kpi-value text-amber-950">{{ number_format($spedDiffCount, 0, ',', '.') }}</dd>
                            <dd class="mt-3">
                                <a href="{{ $documentsSpedDiffUrl }}" class="text-sm font-semibold text-amber-800 hover:underline underline-offset-2">{{ __('dashboard.open_list') }} →</a>
                            </dd>
                        </div>
                        <div class="mx-kpi-card mx-kpi-card--neutral pl-5">
                            <dt class="mx-kpi-title">{{ __('dashboard.stat_sped_missing') }}</dt>
                            <dd class="mx-kpi-value">{{ number_format($spedMissingCount, 0, ',', '.') }}</dd>
                            <dd class="mt-3">
                                <a href="{{ $documentsSpedMissingUrl }}" class="mx-link-action">{{ __('dashboard.open_list') }} →</a>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
                        <a href="{{ $importUrl }}" class="mx-btn-primary">{{ __('dashboard.quick_import') }}</a>
                        <a href="{{ $reportsUrl }}" class="mx-btn-secondary">{{ __('dashboard.quick_reports') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
