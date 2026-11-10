@php($currency = $order->currency())
<x-mail::table>
| {{ __('Ürün') }} | {{ __('Adet') }} | {{ __('Tutar') }} |
|:-----|:----:|------:|
@foreach ($order->items as $item)
| {{ $item->name }} ({{ $item->sku }}) | {{ $item->quantity }} | {{ $currency?->format($item->line_total) ?? \App\Support\Money::number($item->line_total) }} |
@endforeach
| **{{ __('Ara Toplam (KDV hariç)') }}** | | {{ $currency?->format($order->subtotal) }} |
| **{{ __('KDV') }}** | | {{ $currency?->format($order->tax_total) }} |
| **{{ __('Kargo') }}** | | {{ $order->shipping_total->isPositive() ? $currency?->format($order->shipping_total) : __('Ücretsiz') }} |
| **{{ __('Genel Toplam') }}** | | **{{ $currency?->format($order->grand_total) }}** |
</x-mail::table>
