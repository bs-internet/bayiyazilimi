<x-mail::message>
# {{ __('Yeni iade talebi: :number', ['number' => $returnRequest->number]) }}

{{ __('**Sipariş:** :number', ['number' => $returnRequest->order->number]) }}<br>
{{ __('**Müşteri:** :customer_name (:email)', ['customer_name' => $returnRequest->order->customer_name, 'email' => $returnRequest->order->email]) }}<br>
{{ __('**Neden:** :returnrequest', ['returnrequest' => $returnRequest->reason->label()]) }}

<x-mail::table>
{{ __('| Ürün | Adet |') }}
|:-----|:----:|
@foreach ($returnRequest->loadMissing('items.orderItem')->items as $item)
| {{ $item->orderItem?->name }} ({{ $item->orderItem?->sku }}) | {{ $item->quantity }} |
@endforeach
</x-mail::table>

@if ($returnRequest->customer_note)
{{ __('**Müşteri notu:** :customer_note', ['customer_note' => $returnRequest->customer_note]) }}
@endif

<x-mail::button :url="route('panel.shop.returns.show', $returnRequest->id)">
{{ __('Panelde Aç') }}
</x-mail::button>
</x-mail::message>
