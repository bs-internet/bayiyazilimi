@php
    $s = $ledger['statement'];
    $title = $account->title ?? $member?->name ?? $account->code;
    $sideMark = fn (array $b) => $b['side'] === 'zero' ? '' : ($b['side'] === 'debit' ? __('(B)') : __('(A)'));
    $fmtDate = fn (?string $d) => $d ? \Carbon\CarbonImmutable::parse($d)->format('d.m.Y') : '';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Cari Ekstre — :title', ['title' => $title]) }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; margin: 24px; font-size: 12px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
        td.num, th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: 700; border-top: 2px solid #111; }
        .reversed { color: #888; text-decoration: line-through; }
        .actions { margin-bottom: 16px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">{{ __('Yazdır / PDF olarak kaydet') }}</button></div>
    <header>
        <div>
            <h1>{{ __('Cari Hesap Ekstresi') }}</h1>
            <div><strong>{{ $title }}</strong> ({{ $account->code }})</div>
            @if ($account->tax_number)
                <div class="muted">{{ __('VD: :office · VN: :number', ['office' => $account->tax_office, 'number' => $account->tax_number]) }}</div>
            @endif
        </div>
        <div style="text-align:right">
            <div><strong>{{ \App\Support\Branding::name() }}</strong></div>
            <div class="muted">{{ __('Dönem: :from – :to', ['from' => $ledger['filters']['from'] ? $fmtDate($ledger['filters']['from']) : __('Başlangıç'), 'to' => $ledger['filters']['to'] ? $fmtDate($ledger['filters']['to']) : now()->format('d.m.Y')]) }}</div>
            <div class="muted">{{ __('Para birimi: :currency · Düzenlenme: :date', ['currency' => $ledger['currency'], 'date' => now()->format('d.m.Y H:i')]) }}</div>
        </div>
    </header>

    <table>
        <thead>
            <tr><th>{{ __('Tarih') }}</th><th>{{ __('Vade') }}</th><th>{{ __('Tür') }}</th><th>{{ __('Açıklama') }}</th><th class="num">{{ __('Borç') }}</th><th class="num">{{ __('Alacak') }}</th><th class="num">{{ __('Bakiye') }}</th></tr>
        </thead>
        <tbody>
            <tr><td></td><td></td><td>{{ __('Devir') }}</td><td>{{ __('Dönem başı bakiye') }}</td><td></td><td></td><td class="num">{{ $s['opening']['formatted'] }} {{ $sideMark($s['opening']) }}</td></tr>
            @foreach (array_reverse($s['rows']) as $row)
                <tr class="{{ $row['reversed'] ? 'reversed' : '' }}">
                    <td>{{ $fmtDate($row['date']) }}</td>
                    <td>{{ $fmtDate($row['due_date']) }}</td>
                    <td>{{ $row['type_label'] }}{{ $row['is_reversal'] ? ' '.__('(iptal)') : '' }}</td>
                    <td>{{ $row['description'] }}@if ($row['original']) <span class="muted">· {{ $row['original'] }}</span>@endif</td>
                    <td class="num">{{ $row['debit'] }}</td>
                    <td class="num">{{ $row['credit'] }}</td>
                    <td class="num">{{ $row['balance']['formatted'] }} {{ $sideMark($row['balance']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">{{ __('Dönem toplamı / bakiye (:side)', ['side' => $s['closing']['side_label']]) }}</td>
                <td class="num">{{ $s['debit_total'] }}</td>
                <td class="num">{{ $s['credit_total'] }}</td>
                <td class="num">{{ $s['closing']['formatted'] }}</td>
            </tr>
        </tbody>
    </table>
    <p class="muted">{{ __('B: borç bakiyesi (müşteri borçlu), A: alacak bakiyesi (müşteri alacaklı).') }}</p>
</body>
</html>
