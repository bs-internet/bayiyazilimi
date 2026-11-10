<x-mail::message>
# {{ __('Siparişe uygulanamayan ödeme: :number', ['number' => $order->number]) }}

{{ __('**Müşteri:** :customer_name', ['customer_name' => $order->customer_name]) }}<br>
{{ __('**Tutar:** :amount :currency', ['amount' => \App\Support\Money::number($payment->amount), 'currency' => $payment->currency_code]) }}<br>
{{ __('**Ödeme referansı:** :reference', ['reference' => $payment->merchant_oid]) }}<br>
{{ __('**Neden:** :reason', ['reason' => $reason]) }}<br>
{{ __('**Sipariş durumu:** :status', ['status' => $order->status->label()]) }}

@if ($order->account_id !== null)
{{ __('Tutar müşterinin carisine alacak olarak işlendi. Siparişi yeniden açabilir ya da ödemeyi iade edebilirsiniz.') }}
@else
{{ __('Müşterinin cari hesabı olmadığı için tutar müşteriye iade edilmelidir.') }}
@endif

<x-mail::button :url="route('panel.shop.orders.show', $order->id)">
{{ __('Panelde Aç') }}
</x-mail::button>
</x-mail::message>
