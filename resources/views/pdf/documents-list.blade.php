<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 8px 0; }
        .meta { font-size: 8px; color: #555; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; font-weight: 600; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ __('documents.pdf_generated_at') }}: {{ $generated_at }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('documents.col_chave') }}</th>
                <th>{{ __('documents.col_issue') }}</th>
                <th>{{ __('documents.col_model') }}</th>
                <th>{{ __('documents.col_branch') }}</th>
                <th class="num">{{ __('documents.col_total') }}</th>
                <th>{{ __('documents.col_cfop') }}</th>
                <th>SPED</th>
                <th>{{ __('documents.col_sped_reconcile') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($documents as $doc)
                <tr>
                    <td>{{ $doc->chave }}</td>
                    <td>{{ $doc->issued_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $doc->modelo ?? '—' }}</td>
                    <td>{{ $doc->branch?->name ?? '—' }}</td>
                    <td class="num">
                        @if ($doc->total !== null)
                            {{ number_format((float) $doc->total, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $doc->cfop ?? '—' }}</td>
                    <td>{{ ! empty($doc->sped_matched) ? __('documents.sped_yes') : __('documents.sped_no') }}</td>
                    <td>
                        @switch($doc->sped_reconcile ?? 'none')
                            @case('ok')
                                {{ __('documents.reconcile_ok') }}
                                @break
                            @case('diff')
                                {{ __('documents.reconcile_diff') }}
                                @break
                            @case('unknown')
                                {{ __('documents.reconcile_unknown') }}
                                @break
                            @default
                                {{ __('documents.reconcile_none') }}
                        @endswitch
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
