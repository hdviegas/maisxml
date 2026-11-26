<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 28px 36px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { font-size: 8px; color: #444; margin: 0 0 8px 0; line-height: 1.5; }
        .notice { background: #fff4d6; border: 1px solid #e0b84a; padding: 4px 6px; margin: 0 0 8px 0; font-size: 8px; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #ccc; padding: 2px 3px; text-align: left; vertical-align: middle; overflow: hidden; }
        th { background: #eceff3; font-weight: bold; font-size: 7.5px; }
        tbody tr:nth-child(even) td { background: #f8f9fb; }
        .num { text-align: right; white-space: nowrap; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.2px; white-space: nowrap; }
        .totals td { background: #eceff3; font-weight: bold; border-top: 2px solid #888; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        @if ($organization !== '')
            <strong>{{ __('documents.pdf_organization') }}:</strong> {{ $organization }} &nbsp;|&nbsp;
        @endif
        <strong>{{ __('documents.pdf_user') }}:</strong> {{ $user_name }} &nbsp;|&nbsp;
        <strong>{{ __('documents.pdf_generated_at') }}:</strong> {{ $generated_at }}<br>
        <strong>{{ __('documents.pdf_filters') }}:</strong>
        {{ count($filter_lines) ? implode('  •  ', $filter_lines) : __('documents.pdf_no_filters') }}
    </div>

    @if ($truncated)
        <div class="notice">
            {{ __('documents.pdf_truncated', ['shown' => number_format(count($rows), 0, ',', '.'), 'total' => number_format($matched_count, 0, ',', '.'), 'sum' => number_format($matched_total, 2, ',', '.')]) }}
        </div>
    @endif

    @if (count($rows) === 0)
        <p>{{ __('documents.pdf_empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 23%">{{ __('documents.col_chave') }}</th>
                    <th style="width: 6%">{{ __('documents.col_issue') }}</th>
                    <th style="width: 3.5%">{{ __('documents.col_model') }}</th>
                    <th style="width: 7%">{{ __('documents.pdf_col_number') }}</th>
                    <th style="width: 11%">{{ __('documents.col_branch') }}</th>
                    <th style="width: 7.5%">{{ __('documents.col_operation') }}</th>
                    <th style="width: 15%">{{ __('documents.pdf_col_emitter') }}</th>
                    <th style="width: 15%">{{ __('documents.pdf_col_recipient') }}</th>
                    @if ($with_sped)
                        <th style="width: 6%">{{ __('documents.col_sped_reconcile') }}</th>
                    @endif
                    <th class="num" style="width: 8%">{{ __('documents.col_total') }} (R$)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="mono">{{ $row['chave'] }}</td>
                        <td>{{ $row['issued_at']?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $row['modelo'] ?? '—' }}</td>
                        <td>{{ $row['numero'] ?? '—' }}{{ $row['serie'] !== null ? ' / '.$row['serie'] : '' }}</td>
                        <td>{{ $row['branch'] ?? '—' }}</td>
                        <td>{{ $row['operation'] ?? '—' }}</td>
                        <td>{{ $row['emitter'] !== '' ? $row['emitter'] : '—' }}</td>
                        <td>{{ $row['recipient'] !== '' ? $row['recipient'] : '—' }}</td>
                        @if ($with_sped)
                            <td>{{ __('documents.reconcile_'.($row['sped'] ?? 'none')) }}</td>
                        @endif
                        <td class="num">{{ $row['total'] !== null ? number_format($row['total'], 2, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totals">
                    <td colspan="{{ $with_sped ? 9 : 8 }}">{{ __('documents.pdf_totals', ['count' => number_format(count($rows), 0, ',', '.')]) }}</td>
                    <td class="num">{{ number_format($shown_total, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>
