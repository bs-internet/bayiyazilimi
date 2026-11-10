<x-mail::message>
# {{ __('Teklif talebiniz alındı') }}

{{ __('Merhaba :customer_name,', ['customer_name' => $quote->customer_name]) }}

{{ __('**:number** numaralı teklif talebiniz bize ulaştı. Ekibimiz en kısa sürede size özel fiyat teklifimizi iletecektir.', ['number' => $quote->number]) }}

<x-mail::table>
{{ __('| Ürün | Adet |') }}
|:-----|:----:|
@foreach ($quote->loadMissing('items')->items as $item)
| {{ $item->name }} ({{ $item->sku }}) | {{ $item->quantity }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$quote->customerUrl()">
{{ __('Teklifi Görüntüle') }}
</x-mail::button>

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
