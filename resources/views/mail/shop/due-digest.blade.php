@php
    // Tablo satırları yalnız açık olan türlerin sütunlarıyla kurulur (markdown tablosu).
    $header = [__('Bayi')];
    $align = [':--'];
    if ($overdue) { $header[] = __('Vadesi geçen'); $align[] = '--:'; }
    if ($upcoming) { $header[] = __('Vadesi yaklaşan'); $align[] = '--:'; }
    $lines = [];
    foreach ($rows as $r) {
        $cells = [$r['account'].' ('.$r['code'].')'];
        if ($overdue) {
            $cells[] = $r['overdue_total'] ? __(':total · :count borç, en eski :days gün', ['total' => $r['overdue_total'], 'count' => $r['overdue_count'], 'days' => $r['overdue_days']]) : '—';
        }
        if ($upcoming) {
            $cells[] = $r['upcoming_total'] ? __(':total · :count borç', ['total' => $r['upcoming_total'], 'count' => $r['upcoming_count']]) : '—';
        }
        $lines[] = '| '.implode(' | ', array_map(fn ($c) => str_replace('|', '/', e($c)), $cells)).' |';
    }
@endphp
<x-mail::message>
# {{ __('Günlük vade özeti') }}

{{ __('Vadesi geçen ya da yaklaşan borcu olan bayiler (:date):', ['date' => now()->format('d.m.Y')]) }}

<x-mail::table>
| {{ implode(' | ', $header) }} |
| {{ implode(' | ', $align) }} |
@foreach ($lines as $line)
{!! $line !!}
@endforeach
</x-mail::table>

<x-mail::button :url="route('panel.shop.ledger.aging')">
{{ __('Geciken Ödemeler') }}
</x-mail::button>

{{ __('Bu e-postayı Cari Ayarları › Vade hatırlatma e-postaları bölümünden kapatabilirsiniz.') }}
</x-mail::message>
