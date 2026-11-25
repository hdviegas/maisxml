<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">
            {{ __('reports.title') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-page space-y-6">
            <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                <div class="mx-card p-6">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('reports.exports_heading') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('reports.exports_hint') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('documents.export.csv') }}"
                        class="mx-btn-primary">
                        {{ __('reports.export_csv') }}
                    </a>
                    <a href="{{ route('documents.export.pdf') }}"
                        class="mx-btn-secondary">
                        {{ __('reports.export_pdf') }}
                    </a>
                </div>
                </div>

                <div class="mx-card p-6">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('reports.activity_heading') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('reports.activity_scope_hint') }}</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">{{ __('reports.col_when') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">{{ __('reports.col_action') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">{{ __('reports.col_detail') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">{{ __('reports.col_user') }}</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse ($activityLogs as $log)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 tabular-nums text-slate-700">
                                        {{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-800">{{ $log->action }}</td>
                                    <td class="max-w-xs truncate px-3 py-2 font-mono text-xs text-slate-600">{{ $log->detailLabel() }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $log->user?->email ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-500">{{ __('reports.activity_empty') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
