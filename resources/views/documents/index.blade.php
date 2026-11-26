@php
    $selectedBranchIds = \App\Services\Documents\DocumentListFilters::parseBranchIds(request('branch_id'));
    $exportParams = array_filter([
        'q' => request('q'),
        'branch_id' => $selectedBranchIds,
        'operation_type' => request('operation_type'),
        'issued_from' => $issuedFrom,
        'issued_to' => $issuedTo,
        'numero' => request('numero'),
        'serie' => request('serie'),
        'modelo' => request('modelo'),
        'emitter' => request('emitter'),
        'recipient' => request('recipient'),
        'sped_reconcile' => request('sped_reconcile'),
    ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    $activeFiltersCount = count($exportParams) - ($periodIsDefault ? 2 : 0);
    $advancedFilterKeys = ['numero', 'serie', 'modelo', 'emitter', 'recipient', 'sped_reconcile'];
    $advancedActiveCount = collect($advancedFilterKeys)
        ->filter(fn ($k) => request($k) !== null && request($k) !== '')
        ->count();
    $advancedOpen = $advancedActiveCount > 0;
    $partyCell = function (?string $name, ?string $doc) use ($branchNamesByCnpj): array {
        $digits = \App\Support\Tenant\Cnpj::digits($doc);
        // Filial do usuário: sempre o nome fantasia; demais partes: o nome que veio na NF.
        $label = $digits !== '' && isset($branchNamesByCnpj[$digits])
            ? $branchNamesByCnpj[$digits]
            : trim((string) $name);

        return [$label, $digits !== '' ? \App\Support\Tenant\Cnpj::format($digits) : ''];
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="mx-section-title">{{ __('Documents') }}</p>
            <h2 class="mx-page-title mt-1">{{ __('documents.title_index') }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-page space-y-6">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"
                    role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->has('ids'))
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"
                    role="alert">
                    {{ $errors->first('ids') }}
                </div>
            @endif

            <div class="space-y-6">

                <form method="get" action="{{ route('documents.index') }}" class="mx-card p-0" id="doc-filters-form">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-2xl border-b border-slate-100 bg-slate-50/60 px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <p class="mx-section-title">Filtros</p>
                            <span class="mx-chip mx-chip--accent px-2 py-0.5 tabular-nums">{{ $activeFiltersCount }} {{ $activeFiltersCount === 1 ? 'ativo' : 'ativos' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('documents.export.csv', $exportParams) }}" class="mx-btn-xs">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                {{ __('documents.export_csv') }}
                            </a>
                            <a href="{{ route('documents.export.pdf', $exportParams) }}" class="mx-btn-xs">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                {{ __('documents.export_pdf') }}
                            </a>
                        </div>
                    </div>

                    <div class="space-y-3 px-4 py-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[9.5rem_9.5rem_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1.2fr)_auto] lg:items-end">
                            <div>
                                <label for="doc-issued-from" class="mx-label">{{ __('documents.filter_issued_from') }}</label>
                                <input id="doc-issued-from" type="date" name="issued_from" value="{{ $issuedFrom }}"
                                    class="mx-input-sm" />
                            </div>
                            <div>
                                <label for="doc-issued-to" class="mx-label">{{ __('documents.filter_issued_to') }}</label>
                                <input id="doc-issued-to" type="date" name="issued_to" value="{{ $issuedTo }}"
                                    class="mx-input-sm" />
                            </div>
                            <div>
                                <label for="doc-branch" class="mx-label">{{ __('documents.filter_branch') }}</label>
                                <select id="doc-branch" name="branch_id[]" multiple data-multiselect
                                    data-placeholder="Todas as filiais" data-summary-singular="filial selecionada"
                                    data-summary-plural="filiais selecionadas" class="mx-select-sm">
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" @selected(in_array((int) $b->id, $selectedBranchIds, true))>
                                            {{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="doc-op" class="mx-label">{{ __('documents.filter_operation') }}</label>
                                <select id="doc-op" name="operation_type" class="mx-select-sm">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach ($operationTypes as $op)
                                        <option value="{{ $op->value }}" @selected((string) request('operation_type') === (string) $op->value)>
                                            {{ $op->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="doc-q" class="mx-label">{{ __('documents.search_chave') }}</label>
                                <input id="doc-q" type="search" name="q" value="{{ request('q') }}" maxlength="44"
                                    inputmode="numeric" class="mx-input-sm tabular-nums" />
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-1">
                                <button type="submit" class="mx-btn-primary mx-btn-sm">{{ __('documents.apply_filters') }}</button>
                                <a href="{{ route('documents.index') }}" class="mx-btn-secondary mx-btn-sm">Limpar</a>
                                <button type="button" id="doc-adv-toggle" class="mx-btn-secondary mx-btn-sm"
                                    aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}" aria-controls="doc-advanced-filters"
                                    data-label-more="{{ __('documents.more_filters') }}"
                                    data-label-less="{{ __('documents.less_filters') }}"
                                    data-count="{{ $advancedActiveCount }}">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                                    <span id="doc-adv-toggle-label">{{ $advancedOpen ? __('documents.less_filters') : __('documents.more_filters') }}@if ($advancedActiveCount > 0) ({{ $advancedActiveCount }})@endif</span>
                                </button>
                            </div>
                        </div>

                        <div id="doc-advanced-filters" @if (! $advancedOpen) hidden @endif
                            class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                            <div>
                                <label for="doc-numero" class="mx-label">{{ __('documents.filter_numero') }}</label>
                                <input id="doc-numero" type="number" name="numero" min="1" value="{{ request('numero') }}"
                                    class="mx-input-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="doc-serie" class="mx-label">{{ __('documents.filter_serie') }}</label>
                                <input id="doc-serie" type="number" name="serie" min="0" value="{{ request('serie') }}"
                                    class="mx-input-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="doc-modelo" class="mx-label">{{ __('documents.filter_modelo') }}</label>
                                <select id="doc-modelo" name="modelo" class="mx-select-sm">
                                    <option value="">{{ __('All') }}</option>
                                    <option value="55" @selected(request('modelo') === '55')>55</option>
                                    <option value="65" @selected(request('modelo') === '65')>65</option>
                                    <option value="66" @selected(request('modelo') === '66')>66</option>
                                </select>
                            </div>
                            <div>
                                <label for="doc-emitter" class="mx-label">{{ __('documents.filter_emitter') }}</label>
                                <input id="doc-emitter" type="text" name="emitter" value="{{ request('emitter') }}"
                                    inputmode="numeric" autocomplete="off" class="mx-input-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="doc-recipient" class="mx-label">{{ __('documents.filter_recipient') }}</label>
                                <input id="doc-recipient" type="text" name="recipient" value="{{ request('recipient') }}"
                                    inputmode="numeric" autocomplete="off" class="mx-input-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="doc-sped-rec" class="mx-label">{{ __('documents.filter_sped_reconcile') }}</label>
                                <select id="doc-sped-rec" name="sped_reconcile" class="mx-select-sm">
                                    @foreach ($spedReconcileOptions as $val => $label)
                                        <option value="{{ $val }}" @selected(request('sped_reconcile', '') === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                <form method="post" action="{{ route('documents.bulk_zip') }}" class="space-y-3" id="bulk-zip-form">
                    @csrf

                    <div class="mx-card flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <p class="text-sm text-slate-600">
                            <span class="mr-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Resultados</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ number_format($documents->total(), 0, ',', '.') }}</span>
                            registros ·
                            página <span class="font-semibold tabular-nums text-slate-900">{{ $documents->currentPage() }}</span>
                            de <span class="tabular-nums">{{ max(1, $documents->lastPage()) }}</span>
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="hidden max-w-xs text-xs leading-snug text-slate-500 md:inline">{{ __('documents.bulk_zip_hint') }}</span>
                            <button type="submit" class="mx-btn-primary mx-btn-sm" title="{{ __('documents.bulk_zip_hint') }}">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                {{ __('documents.bulk_zip') }}
                            </button>
                            <button type="submit" class="mx-btn-secondary mx-btn-sm" formaction="{{ route('documents.bulk_danfe') }}" formtarget="_blank"
                                title="{{ __('documents.bulk_danfe_hint') }}">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                                {{ __('documents.bulk_danfe') }}
                            </button>
                        </div>
                    </div>

                    <div class="mx-table-wrap mx-table-wrap--scroll">
                        <table class="mx-table--compact min-w-full text-left">
                            <thead class="border-b border-slate-200">
                                <tr>
                                    <th scope="col" class="mx-table-header w-10 text-center">
                                        <label for="select-all-docs" class="sr-only">{{ __('documents.col_select') }}</label>
                                        <input id="select-all-docs" type="checkbox"
                                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                    </th>
                                    <th scope="col" class="mx-table-header">{{ __('documents.col_chave') }}</th>
                                    <th scope="col" class="mx-table-header">{{ __('documents.col_issue') }}</th>
                                    <th scope="col" class="mx-table-header whitespace-nowrap">{{ __('documents.col_numero') }}</th>
                                    <th scope="col" class="mx-table-header">{{ __('documents.col_emitter') }}</th>
                                    <th scope="col" class="mx-table-header">{{ __('documents.col_recipient') }}</th>
                                    <th scope="col" class="mx-table-header">{{ __('documents.col_operation_short') }}</th>
                                    <th scope="col" class="mx-table-header text-right">{{ __('documents.col_total') }}</th>
                                    <th scope="col" class="mx-table-header text-right">{{ __('documents.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($documents as $doc)
                                    <tr class="mx-table-row">
                                        <td class="mx-table-cell-center w-10">
                                            @can('view', $doc)
                                                <input type="checkbox" name="ids[]" value="{{ $doc->id }}"
                                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                            @endcan
                                        </td>
                                        <td class="mx-table-cell whitespace-nowrap">
                                            @can('view', $doc)
                                                <a href="{{ route('documents.lookup_chave', $doc->chave) }}"
                                                    class="font-mono text-xs font-medium text-brand-700 hover:text-brand-800 hover:underline">{{ $doc->chave }}</a>
                                            @else
                                                <span class="font-mono text-xs">{{ $doc->chave }}</span>
                                            @endcan
                                        </td>
                                        <td class="mx-table-cell whitespace-nowrap tabular-nums text-slate-600">
                                            {{-- xml.data_nf é DATE: sem hora e sem conversão de fuso (evitaria virar o dia anterior). --}}
                                            {{ $doc->issued_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="mx-table-cell whitespace-nowrap tabular-nums">
                                            @if ($doc->numero !== null)
                                                {{ $doc->numero }}@if ($doc->serie !== null) / {{ $doc->serie }}@endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        @foreach ([[$doc->social_emit, $doc->cnpj_emit], [$doc->social_dest, $doc->cnpj_dest]] as [$partyName, $partyDoc])
                                            @php [$label, $fmt] = $partyCell($partyName, $partyDoc); @endphp
                                            <td class="mx-table-cell max-w-[14rem] text-slate-600" @if ($label !== '') title="{{ $label }}" @endif>
                                                @if ($label === '' && $fmt === '')
                                                    —
                                                @else
                                                    @if ($label !== '')
                                                        <div class="truncate">{{ $label }}</div>
                                                    @endif
                                                    @if ($fmt !== '')
                                                        <div class="truncate text-xs tabular-nums text-slate-500">{{ $fmt }}</div>
                                                    @endif
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="mx-table-cell whitespace-nowrap">{{ $doc->operation_type?->label() ?? '—' }}</td>
                                        <td class="mx-table-cell whitespace-nowrap text-right font-semibold tabular-nums text-slate-900">
                                            @if ($doc->total !== null)
                                                {{ number_format((float) $doc->total, 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="mx-table-cell">
                                            <div class="flex justify-end"
                                                x-data="{ open: false, top: 0, left: 0,
                                                    toggle() { this.open ? this.close() : this.show(); },
                                                    show() {
                                                        this.open = true;
                                                        this.$nextTick(() => {
                                                            const b = this.$refs.btn.getBoundingClientRect();
                                                            const m = this.$refs.menu.getBoundingClientRect();
                                                            const gap = 4;
                                                            this.left = Math.max(8, Math.min(b.right - m.width, window.innerWidth - m.width - 8));
                                                            this.top = (b.bottom + m.height + gap > window.innerHeight && b.top - m.height - gap > 0)
                                                                ? b.top - m.height - gap : b.bottom + gap;
                                                            this.$refs.menu.querySelector('[role=menuitem]')?.focus();
                                                        });
                                                    },
                                                    step(d) {
                                                        const items = [...this.$refs.menu.querySelectorAll('[role=menuitem]')];
                                                        const i = items.indexOf(document.activeElement);
                                                        items[(i + d + items.length) % items.length]?.focus();
                                                    },
                                                    close(focusBtn = false) { this.open = false; if (focusBtn) this.$refs.btn.focus(); } }"
                                                @keydown.escape.window="open && close(true)"
                                                @scroll.window="open && close()"
                                                @resize.window="open && close()">
                                                <button type="button" x-ref="btn" @click="toggle()"
                                                    aria-haspopup="menu" :aria-expanded="open.toString()"
                                                    class="mx-btn-xs" title="{{ __('documents.col_actions') }}" aria-label="{{ __('documents.col_actions') }}">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm7.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm7.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" /></svg>
                                                    <span class="hidden 2xl:inline">{{ __('documents.col_actions') }}</span>
                                                </button>
                                                <div x-ref="menu" x-show="open" style="display:none" role="menu" @click.outside="close()"
                                                    @keydown.arrow-down.prevent="step(1)" @keydown.arrow-up.prevent="step(-1)"
                                                    :style="`top:${top}px;left:${left}px`"
                                                    class="fixed z-50 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                                                    @can('view', $doc)
                                                        <a href="{{ route('documents.view_xml', $doc) }}" target="_blank" rel="noopener" role="menuitem" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                                            <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" /></svg>
                                                            {{ __('documents.view_xml') }}
                                                        </a>
                                                        <a href="{{ route('documents.danfe', $doc) }}" target="_blank" rel="noopener" role="menuitem" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                                            <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                                            {{ __('documents.download_danfe') }}
                                                        </a>
                                                        <a href="{{ route('documents.download', $doc) }}" role="menuitem" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                                            <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                                            {{ __('documents.download') }}
                                                        </a>
                                                    @endcan
                                                    @can('delete', $doc)
                                                        <button type="submit" form="delete-doc-{{ $doc->id }}" role="menuitem"
                                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                                                            <svg class="h-4 w-4 shrink-0 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                                            {{ __('documents.delete') }}
                                                        </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9"
                                            class="px-4 py-14 text-center text-sm text-slate-500">{{ __('documents.empty') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                @foreach ($documents as $doc)
                    @can('delete', $doc)
                        <form id="delete-doc-{{ $doc->id }}" method="post" action="{{ route('documents.destroy', $doc) }}"
                            class="js-confirm-delete hidden" data-confirm-message="{{ __('documents.delete_confirm') }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endcan
                @endforeach

                <div>
                    {{ $documents->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const bulkForm = document.getElementById('bulk-zip-form');
            if (bulkForm) {
                bulkForm.addEventListener('submit', (event) => {
                    if (!bulkForm.querySelector('input[name="ids[]"]:checked')) {
                        event.preventDefault();
                        window.alert(@js(__('documents.bulk_select_none')));
                    }
                });
            }

            const selectAll = document.getElementById('select-all-docs');
            const rowChecks = Array.from(document.querySelectorAll('input[name="ids[]"]'));
            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    rowChecks.forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                });
                rowChecks.forEach((checkbox) => {
                    checkbox.addEventListener('change', () => {
                        selectAll.checked = rowChecks.length > 0 && rowChecks.every((item) => item.checked);
                    });
                });
            }

            const advToggle = document.getElementById('doc-adv-toggle');
            const advPanel = document.getElementById('doc-advanced-filters');
            if (advToggle && advPanel) {
                const advLabel = document.getElementById('doc-adv-toggle-label');
                advToggle.addEventListener('click', () => {
                    const open = advPanel.hasAttribute('hidden');
                    advPanel.toggleAttribute('hidden', !open);
                    advToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    const count = parseInt(advToggle.dataset.count || '0', 10);
                    advLabel.textContent = (open ? advToggle.dataset.labelLess : advToggle.dataset.labelMore)
                        + (count > 0 ? ' (' + count + ')' : '');
                });
            }

            document.querySelectorAll('.js-confirm-delete').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const message = form.getAttribute('data-confirm-message') || 'Confirmar exclusão?';
                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>
</x-app-layout>
