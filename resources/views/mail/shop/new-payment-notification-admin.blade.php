<x-mail::message>
# {{ __('Yeni havale bildirimi') }}

{{ __('**Müşteri:** :name', ['name' => $paymentNotification->account()->withoutGlobalScopes()->value('title') ?? $paymentNotification->user?->name]) }}<br>
{{ __('**Tutar:** :amount', ['amount' => \App\Support\Money::format($paymentNotification->amount)]) }}<br>
{{ __('**Havale tarihi:** :date', ['date' => $paymentNotification->transfer_date->format('d.m.Y')]) }}<br>
@if ($paymentNotification->sender_name)
{{ __('**Gönderen:** :sender_name', ['sender_name' => $paymentNotification->sender_name]) }}<br>
@endif
@if ($paymentNotification->order_id)
{{ __('**Sipariş:** :number', ['number' => $paymentNotification->order()->withoutGlobalScopes()->value('number')]) }}<br>
@endif

@if ($paymentNotification->description)
{{ __('**Açıklama:** :description', ['description' => $paymentNotification->description]) }}
@endif

<x-mail::button :url="route('panel.shop.payment-notifications.index')">
{{ __('Panelde Aç') }}
</x-mail::button>
</x-mail::message>
