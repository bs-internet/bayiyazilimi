<x-mail::message>
# {{ __('Teklif :number: :quote', ['number' => $quote->number, 'quote' => $quote->status->label()]) }}

**{{ __('Müşteri:') }}** {{ $quote->customer_name }} @if ($quote->company) ({{ $quote->company }}) @endif<br>
{{ __('**E-posta:** :email', ['email' => $quote->email]) }}<br>
{{ __('**Tutar:** :currency_code', ['currency_code' => \App\Models\Currency::withTrashed()->where('code', $quote->currency_code)->first()?->format($quote->grand_total) ?? $quote->grand_total.' '.$quote->currency_code]) }}

@switch($quote->status)
@case(\App\Enums\Shop\QuoteStatus::Approved)
{{ __('Müşteri teklifi **onayladı**; ödemesi bekleniyor.') }}
@break
@case(\App\Enums\Shop\QuoteStatus::Revision)
{{ __('Müşteri **revize istedi**. Teklifi düzenleyip yeniden gönderebilirsiniz.') }}
@break
@case(\App\Enums\Shop\QuoteStatus::Rejected)
{{ __('Müşteri teklifi **reddetti**.') }}
@break
@default
{{ __('Müşteri talebini **geri çekti**.') }}
@endswitch

@if ($note)
{{ __('**Müşterinin notu:** :note', ['note' => $note]) }}
@endif

<x-mail::button :url="route('panel.shop.quotes.edit', $quote->id)">
{{ __('Panelde Aç') }}
</x-mail::button>
</x-mail::message>
