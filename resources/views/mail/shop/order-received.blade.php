<x-mail::message>
# {{ __('Siparişiniz alındı') }}

{{ __('Merhaba :customer_name,', ['customer_name' => $order->customer_name]) }}

{{ __('**:number** numaralı siparişiniz alınmıştır. Teşekkür ederiz.', ['number' => $order->number]) }}

@include('mail.shop.partials.order-items', ['order' => $order->loadMissing('items')])

{{ __('**Ödeme yöntemi:** :payment_method_label', ['payment_method_label' => $order->paymentMethodLabel()]) }}

@if ($order->payment_method === 'bank_transfer' && $order->payment_status !== \App\Enums\Shop\PaymentStatus::Paid)
{{ __('Ödemenizi aşağıdaki hesaplardan birine, açıklama kısmına sipariş numaranızı (**:number**) yazarak yapabilirsiniz:', ['number' => $order->number]) }}

@foreach (\App\Models\BankAccount::forDisplay() as $account)
**{{ $account['bank_name'] }}**{{ $account['branch'] ? ' — '.$account['branch'] : '' }}{{ $account['currency_code'] ? ' ('.$account['currency_code'].')' : '' }}<br>
{{ __('Hesap sahibi: :account_holder', ['account_holder' => $account['account_holder']]) }}<br>
{{ __('IBAN: :iban', ['iban' => $account['iban']]) }}

@endforeach
@endif

<x-mail::button :url="$order->customerUrl()">
{{ __('Siparişi Görüntüle') }}
</x-mail::button>

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
