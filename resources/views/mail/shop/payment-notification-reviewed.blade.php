<x-mail::message>
@if ($paymentNotification->status === \App\Enums\Shop\PaymentNotificationStatus::Approved)
# {{ __('Havale bildiriminiz onaylandı') }}

{{ __(':date tarihli :amount tutarındaki ödemeniz carinize işlendi.', ['date' => $paymentNotification->transfer_date->format('d.m.Y'), 'amount' => \App\Support\Money::format($paymentNotification->amount)]) }}
@else
# {{ __('Havale bildiriminiz reddedildi') }}

{{ __(':date tarihli :amount tutarındaki bildiriminiz onaylanmadı.', ['date' => $paymentNotification->transfer_date->format('d.m.Y'), 'amount' => \App\Support\Money::format($paymentNotification->amount)]) }}

{{ __('**Neden:** :reject_reason', ['reject_reason' => $paymentNotification->reject_reason]) }}
@endif

<x-mail::button :url="route('app.shop.payments.index')">
{{ __('Cari Hesabım') }}
</x-mail::button>
</x-mail::message>
