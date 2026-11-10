<x-mail::message>
# {{ __('Yeni teklif talebi: :number', ['number' => $quote->number]) }}

**{{ __('Müşteri:') }}** {{ $quote->customer_name }} @if ($quote->company) ({{ $quote->company }}) @endif<br>
{{ __('**E-posta:** :email', ['email' => $quote->email]) }}<br>
{{ __('**Telefon:** :phone', ['phone' => $quote->phone ?? '-']) }}<br>
{{ __('**Şehir:** :city', ['city' => $quote->city ?? '-']) }}

<x-mail::table>
{{ __('| Ürün | Adet |') }}
|:-----|:----:|
@foreach ($quote->loadMissing('items')->items as $item)
| {{ $item->name }} ({{ $item->sku }}) | {{ $item->quantity }} |
@endforeach
</x-mail::table>

@if ($quote->customer_note)
{{ __('**Not:** :customer_note', ['customer_note' => $quote->customer_note]) }}
@endif

<x-mail::button :url="route('panel.shop.quotes.edit', $quote->id)">
{{ __('Panelde Aç') }}
</x-mail::button>
</x-mail::message>
