<x-mail::message>
@php($currency = \App\Models\Currency::withTrashed()->where('code', $quote->currency_code)->first())
@php($status = $quote->status)
@php($S = \App\Enums\Shop\QuoteStatus::class)
@switch($status)
@case($S::Sent)
# {{ __('Teklifiniz hazır') }}
@break
@case($S::Approved)
# {{ __('Teklif onayınız alındı') }}
@break
@case($S::Revision)
# {{ __('Revize isteğiniz alındı') }}
@break
@case($S::Rejected)
# {{ __('Teklifi reddettiniz') }}
@break
@default
# {{ $byCustomer ? __('Teklif talebiniz geri çekildi') : __('Teklifiniz iptal edildi') }}
@endswitch

{{ __('Merhaba :customer_name,', ['customer_name' => $quote->customer_name]) }}

@switch($status)
@case($S::Sent)
{{ __('**:number** numaralı teklifimiz aşağıdadır. Teklif **:date** tarihine kadar geçerlidir.', ['number' => $quote->number, 'date' => $quote->valid_until?->format('d.m.Y')]) }}
@break
@case($S::Approved)
{{ __('**:number** numaralı teklifi onayladınız. Ödemeyi aşağıdaki bağlantıdan tamamladığınızda siparişiniz oluşur. Teklif **:date** tarihine kadar ödenebilir.', ['number' => $quote->number, 'date' => $quote->valid_until?->format('d.m.Y')]) }}
@break
@case($S::Revision)
{{ __('**:number** numaralı teklif için revize isteğiniz satış ekibimize iletildi. Güncel teklifimiz hazır olduğunda size e-postayla bildireceğiz.', ['number' => $quote->number]) }}
@break
@case($S::Rejected)
{{ __('**:number** numaralı teklifi reddettiniz. Farklı bir ihtiyacınız olursa yeni teklif talebi oluşturabilirsiniz.', ['number' => $quote->number]) }}
@break
@default
@if ($byCustomer)
{{ __('**:number** numaralı teklif talebiniz geri çekildi.', ['number' => $quote->number]) }}
@else
{{ __('**:number** numaralı teklif iptal edilmiştir.', ['number' => $quote->number]) }}
@endif
@endswitch

@if (in_array($status, [$S::Sent, $S::Approved], true))
<x-mail::table>
| {{ __('Ürün') }} | {{ __('Miktar') }} | {{ __('Birim (KDV hariç)') }} | {{ __('Tutar (KDV dahil)') }} |
|:-----|:----:|------:|------:|
@foreach ($quote->loadMissing('items')->items as $item)
| {{ $item->name }} | {{ $item->quantity }} {{ $item->unit }} | {{ $currency?->format($item->unit_price) }} | {{ $currency?->format($item->line_total) }} |
@endforeach
| **{{ __('Kargo') }}** | | | {{ $quote->shipping_total->isPositive() ? $currency?->format($quote->shipping_total) : __('Ücretsiz') }} |
| **{{ __('Genel Toplam') }}** | | | **{{ $currency?->format($quote->grand_total) }}** |
</x-mail::table>
@endif

@if ($status === $S::Sent)
{{ __('Teklifi aşağıdaki bağlantıdan **onaylayabilir**, **revize isteyebilir** ya da **reddedebilirsiniz**. Onayladığınız teklifin ödemesini aynı sayfadan yapabilirsiniz.') }}
@endif

@if ($note)
{{ __('**Not:** :note', ['note' => $note]) }}
@elseif ($quote->admin_note && $status === $S::Sent)
{{ __('**Not:** :admin_note', ['admin_note' => $quote->admin_note]) }}
@endif

<x-mail::button :url="$quote->customerUrl()">
{{ $status === $S::Approved ? __('Ödemeye Geç') : __('Teklifi Görüntüle') }}
</x-mail::button>

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
