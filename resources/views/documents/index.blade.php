@php
    $exportParams = array_filter([
        'q' => request('q'),
        'branch_id' => request('branch_id'),
        'operation_type' => request('operation_type'),
        'issued_from' => request('issued_from'),
        'issued_to' => request('issued_to'),
        'numero' => request('numero'),
        'serie' => request('serie'),
        'modelo' => request('modelo'),
        'emitter' => request('emitter'),
        'recipient' => request('recipient'),
        'sped_reconcile' => request('sped_reconcile'),
    ], fn ($v) => $v !== null && $v !== '');
    $activeFiltersCount = count($exportParams);
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

            <div class="space-y-6">
                <form method="get" action="{{ route('documents.index') }}" class="mx-card overflow-hidden p-0">
                    <div class="border-b border-slate-100 bg-slate-50/60 px-6 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="mx-section-title">Filtros</p>
                                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Refine por emissão, filial, operação e conferência SPED. Exportações respeitam os mesmos critérios.</p>
                            </div>
                            <span class="mx-chip mx-chip--accent tabular-nums">{{ $activeFiltersCount }} ativos</span>
                        </div>
                    </div>
                    <div class="space-y-6 px-6 py-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                        <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                            <div class="grow">
                                <label for="doc-q"
                                    class="mx-label">{{ __('documents.search_chave') }}</label>
                                <input id="doc-q" type="search" name="q" value="{{ request('q') }}" maxlength="44"
                                    inputmode="numeric"
                                    class="mx-input max-w-md tabular-nums" />
                            </div>
                            <div>
                                <label for="doc-branch"
                                    class="mx-label">{{ __('documents.filter_branch') }}</label>
                                <select id="doc-branch" name="branch_id"
                                    class="mx-select max-w-xs">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" @selected((string) request('branch_id') === (string) $b->id)>
                                            {{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="doc-op"
                                    class="mx-label">{{ __('documents.filter_operation') }}</label>
                                <select id="doc-op" name="operation_type"
                                    class="mx-select max-w-xs">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach ($operationTypes as $op)
                                        <option value="{{ $op->value }}" @selected((string) request('operation_type') === (string) $op->value)>
                                            {{ $op->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 border-l border-slate-200 pl-6 text-sm lg:pl-8">
                            <a href="{{ route('documents.export.csv', $exportParams) }}"
                                class="mx-link-action">{{ __('documents.export_csv') }}</a>
                            <a href="{{ route('documents.export.pdf', $exportParams) }}"
                                class="mx-link-action">{{ __('documents.export_pdf') }}</a>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-4 border-t border-slate-100 pt-6 lg:flex-row lg:flex-wrap lg:items-end">
                        <div>
                            <label for="doc-issued-from"
                                class="mx-label">{{ __('documents.filter_issued_from') }}</label>
                            <input id="doc-issued-from" type="date" name="issued_from" value="{{ request('issued_from') }}"
                                class="mx-input max-w-[11rem]" />
                        </div>
                        <div>
                            <label for="doc-issued-to"
                                class="mx-label">{{ __('documents.filter_issued_to') }}</label>
                            <input id="doc-issued-to" type="date" name="issued_to" value="{{ request('issued_to') }}"
                                class="mx-input max-w-[11rem]" />
                        </div>
                        <div>
                            <label for="doc-numero"
                                class="mx-label">{{ __('documents.filter_numero') }}</label>
                            <input id="doc-numero" type="number" name="numero" min="1" value="{{ request('numero') }}"
                                class="mx-input max-w-[8rem] tabular-nums" />
                        </div>
                        <div>
                            <label for="doc-serie"
                                class="mx-label">{{ __('documents.filter_serie') }}</label>
                            <input id="doc-serie" type="number" name="serie" min="0" value="{{ request('serie') }}"
                                class="mx-input max-w-[8rem] tabular-nums" />
                        </div>
                        <div>
                            <label for="doc-modelo"
                                class="mx-label">{{ __('documents.filter_modelo') }}</label>
                            <select id="doc-modelo" name="modelo"
                                class="mx-select max-w-xs">
                                <option value="">{{ __('All') }}</option>
                                <option value="55" @selected(request('modelo') === '55')>55</option>
                                <option value="65" @selected(request('modelo') === '65')>65</option>
                                <option value="66" @selected(request('modelo') === '66')>66</option>
                            </select>
                        </div>
                        <div class="grow">
                            <label for="doc-emitter"
                                class="mx-label">{{ __('documents.filter_emitter') }}</label>
                            <input id="doc-emitter" type="text" name="emitter" value="{{ request('emitter') }}"
                                inputmode="numeric" autocomplete="off"
                                class="mx-input max-w-md tabular-nums" />
                        </div>
                        <div class="grow">
                            <label for="doc-recipient"
                                class="mx-label">{{ __('documents.filter_recipient') }}</label>
                            <input id="doc-recipient" type="text" name="recipient" value="{{ request('recipient') }}"
                                inputmode="numeric" autocomplete="off"
                                class="mx-input max-w-md tabular-nums" />
                        </div>
                        <div>
                            <label for="doc-sped-rec"
                                class="mx-label">{{ __('documents.filter_sped_reconcile') }}</label>
                            <select id="doc-sped-rec" name="sped_reconcile"
                                class="mx-select max-w-xs">
                                @foreach ($spedReconcileOptions as $val => $label)
                                    <option value="{{ $val }}" @selected(request('sped_reconcile', '') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2 lg:pb-0.5">
                            <button type="submit" class="mx-btn-primary">{{ __('documents.apply_filters') }}</button>
                            <a href="{{ route('documents.index') }}" class="mx-btn-secondary">Limpar</a>
                        </div>
                    </div>
                    </div>
                </form>

                <form method="post" action="{{ route('documents.bulk_zip') }}" class="space-y-4" id="bulk-zip-form">
                    @csrf

                    <div class="mx-card flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <div>
                            <p class="mx-section-title">Resultados</p>
                            <p class="mt-1 text-sm text-slate-600">
                                <span class="text-lg font-semibold tabular-nums text-slate-900">{{ number_format($documents->total(), 0, ',', '.') }}</span>
                                registros ·
                                página <span class="font-semibold tabular-nums text-slate-900">{{ $documents->currentPage() }}</span>
                                de <span class="tabular-nums">{{ max(1, $documents->lastPage()) }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="mx-btn-primary">
                                {{ __('documents.bulk_zip') }}
                            </button>
                            <span class="max-w-md text-xs leading-snug text-slate-500">{{ __('documents.bulk_zip_hint') }}</span>
                        </div>
                    </div>

                    <div class="mx-table-wrap">
                        <table class="min-w-full text-left">
                            <thead class="sticky top-0 z-20 border-b border-slate-200 shadow-sm">
                                <tr>
                                    <th scope="col"
                                        class="w-12 px-3 py-3.5 text-center">
                                        <label for="select-all-docs" class="sr-only">{{ __('documents.col_select') }}</label>
                                        <input id="select-all-docs" type="checkbox"
                                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header min-w-[15rem]">{{ __('documents.col_chave') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header min-w-[8.5rem]">{{ __('documents.col_issue') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header">{{ __('documents.col_model') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header">{{ __('documents.col_branch') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header">{{ __('documents.col_operation_short') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header text-center">{{ __('documents.col_sped') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header text-center">{{ __('documents.col_sped_reconcile') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header text-right">{{ __('documents.col_total') }}
                                    </th>
                                    <th scope="col"
                                        class="mx-table-header text-right">{{ __('documents.col_actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($documents as $doc)
                                    <tr class="mx-table-row">
                                        <td class="w-12 px-3 py-3.5 text-center align-middle">
                                            @can('view', $doc)
                                                <input type="checkbox" name="ids[]" value="{{ $doc->id }}"
                                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                            @endcan
                                        </td>
                                        <td class="mx-table-cell max-w-[18rem]">
                                            @can('view', $doc)
                                                <a href="{{ route('documents.lookup_chave', $doc->chave) }}"
                                                    class="break-all font-mono text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline">{{ $doc->chave }}</a>
                                            @else
                                                <span class="font-mono text-sm">{{ $doc->chave }}</span>
                                            @endcan
                                        </td>
                                        <td class="mx-table-cell whitespace-nowrap tabular-nums text-slate-600">
                                            {{ $doc->issued_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="mx-table-cell tabular-nums">
                                            {{ $doc->modelo ?? '—' }}</td>
                                        <td class="mx-table-cell text-slate-600">
                                            {{ $doc->branch?->name ?? '—' }}</td>
                                        <td class="mx-table-cell">
                                            {{ $doc->operation_type?->label() ?? '—' }}</td>
                                        <td class="px-4 py-3.5 text-center align-middle">
                                            @if (! empty($doc->sped_matched))
                                                <span
                                                    class="inline-flex rounded-md bg-brand-100 px-2 py-0.5 text-[0.6875rem] font-bold uppercase tracking-wide text-brand-900">{{ __('documents.sped_yes') }}</span>
                                            @else
                                                <span class="text-sm text-slate-400">{{ __('documents.sped_no') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-center align-middle">
                                            @switch($doc->sped_reconcile)
                                                @case('ok')
                                                    <span
                                                        class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-[0.6875rem] font-bold uppercase tracking-wide text-emerald-900">{{ __('documents.reconcile_ok') }}</span>
                                                @break

                                                @case('diff')
                                                    <span
                                                        class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-[0.6875rem] font-bold uppercase tracking-wide text-amber-950">{{ __('documents.reconcile_diff') }}</span>
                                                @break

                                                @case('unknown')
                                                    <span class="text-sm text-slate-500">{{ __('documents.reconcile_unknown') }}</span>
                                                @break

                                                @default
                                                    <span class="text-sm text-slate-400">{{ __('documents.reconcile_none') }}</span>
                                            @endswitch
                                        </td>
                                        <td class="mx-table-cell text-right font-semibold tabular-nums text-slate-900">
                                            @if ($doc->total !== null)
                                                R$ {{ number_format((float) $doc->total, 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-right align-middle">
                                            <div class="flex flex-col items-end gap-1.5">
                                                @can('view', $doc)
                                                    <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1 text-xs font-semibold">
                                                        <a href="{{ route('documents.view_xml', $doc) }}" target="_blank" rel="noopener"
                                                            class="text-slate-600 hover:text-brand-700 hover:underline">{{ __('documents.view_xml') }}</a>
                                                        <span class="text-slate-300">·</span>
                                                        <a href="{{ route('documents.danfe', $doc) }}" target="_blank" rel="noopener"
                                                            class="text-amber-800 hover:underline">{{ __('documents.download_danfe') }}</a>
                                                        <span class="text-slate-300">·</span>
                                                        <a href="{{ route('documents.download', $doc) }}"
                                                            class="text-brand-700 hover:underline">{{ __('documents.download') }}</a>
                                                    </div>
                                                @endcan
                                                @can('delete', $doc)
                                                    <form method="post" action="{{ route('documents.destroy', $doc) }}" class="js-confirm-delete"
                                                        data-confirm-message="{{ __('documents.delete_confirm') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="text-xs font-semibold text-rose-700 hover:text-rose-800 hover:underline">{{ __('documents.delete') }}</button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10"
                                                class="px-4 py-14 text-center text-sm text-slate-500">{{ __('documents.empty') }}</td>
                                        </tr>
                                    @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <div>
                    {{ $documents->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
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

            document.querySelectorAll('.js-confirm-delete').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const message = form.getAttribute('data-confirm-message') || 'Confirmar exclusao?';
                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>
</x-app-layout>
