<x-mail::message>
# {{ __('Sipariş durumunuz güncellendi') }}

{{ __('Merhaba :customer_name,', ['customer_name' => $order->customer_name]) }}

{{ __('**:number** numaralı siparişinizin durumu: **:status**', ['number' => $order->number, 'status' => $order->status->label()]) }}

@if ($note)
{{ $note }}
@endif

@if ($order->tracking_number)
{{ __('**Kargo:** :cargo_company — Takip No: **:tracking_number**', ['cargo_company' => $order->cargo_company, 'tracking_number' => $order->tracking_number]) }}
@endif

<x-mail::button :url="$order->customerUrl()">
{{ __('Siparişi Görüntüle') }}
</x-mail::button>

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
