<x-mail::message>
# {{ __('Yeni sipariş: :number', ['number' => $order->number]) }}

{{ __('**Müşteri:** :customer_name', ['customer_name' => $order->customer_name]) }}<br>
{{ __('**E-posta:** :email', ['email' => $order->email]) }}<br>
{{ __('**Telefon:** :phone', ['phone' => $order->phone]) }}<br>
{{ __('**Ödeme:** :payment_method_label — :order', ['payment_method_label' => $order->paymentMethodLabel(), 'order' => $order->payment_status->label()]) }}

@include('mail.shop.partials.order-items', ['order' => $order->loadMissing('items')])

@if ($order->customer_note)
{{ __('**Müşteri notu:** :customer_note', ['customer_note' => $order->customer_note]) }}
@endif

<x-mail::button :url="route('panel.shop.orders.show', $order->id)">
{{ __('Panelde Aç') }}
</x-mail::button>
</x-mail::message>
