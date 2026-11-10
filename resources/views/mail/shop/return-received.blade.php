<x-mail::message>
# {{ __('İade talebiniz alındı') }}

{{ __('Merhaba :customer_name,', ['customer_name' => $returnRequest->order->customer_name]) }}

{{ __('**:number** numaralı siparişiniz için **:number2** numaralı iade talebiniz alınmıştır. Talebiniz incelendikten sonra size bilgi vereceğiz.', ['number' => $returnRequest->order->number, 'number2' => $returnRequest->number]) }}

<x-mail::table>
{{ __('| Ürün | Adet |') }}
|:-----|:----:|
@foreach ($returnRequest->loadMissing('items.orderItem')->items as $item)
| {{ $item->orderItem?->name }} ({{ $item->orderItem?->sku }}) | {{ $item->quantity }} |
@endforeach
</x-mail::table>

{{ __('**İade nedeni:** :returnrequest', ['returnrequest' => $returnRequest->reason->label()]) }}

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
