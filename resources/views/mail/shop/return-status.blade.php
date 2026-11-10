<x-mail::message>
# {{ __('İade talebiniz: :returnrequest', ['returnrequest' => $returnRequest->status->label()]) }}

{{ __('Merhaba :customer_name,', ['customer_name' => $returnRequest->order->customer_name]) }}

{{ __('**:number** numaralı iade talebinizin durumu **:returnrequest** olarak güncellendi.', ['number' => $returnRequest->number, 'returnrequest' => $returnRequest->status->label()]) }}

@if ($returnRequest->status === \App\Enums\Shop\ReturnStatus::Approved)
{{ __('Ürün(ler)i orijinal ambalajında ve faturasıyla birlikte bize gönderebilirsiniz. Gönderim adresi: :address', ['address' => \App\Services\Shop\ShopSettings::storeAddressLine()]) }}
@endif

@if ($returnRequest->refund_amount !== null && $returnRequest->status === \App\Enums\Shop\ReturnStatus::Refunded)
{{ __('**İade tutarı:** :amount :currency_code', ['amount' => \App\Support\Money::number($returnRequest->refund_amount), 'currency_code' => $returnRequest->order->currency_code]) }}
@endif

@if ($returnRequest->admin_note)
{{ __('**Not:** :admin_note', ['admin_note' => $returnRequest->admin_note]) }}
@endif

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
