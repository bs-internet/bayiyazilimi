<x-mail::message>
# {{ $overdue !== [] ? __('Vadesi geçen borcunuz var') : __('Vadesi yaklaşan borcunuz var') }}

{{ __('Merhaba,') }}

{{ __(':account (:code) carinizde aşağıdaki borçların vadesi geçti ya da yaklaşıyor.', ['account' => $account->title ?? $account->code, 'code' => $account->code]) }}

<x-mail::table>
| {{ __('Sipariş') }} | {{ __('Vade') }} | {{ __('Durum') }} | {{ __('Kalan') }} |
|:--|:--|:--|--:|
@foreach ($overdue as $d)
| {{ $d['order']['number'] ?? __('Borç') }} | {{ \Carbon\CarbonImmutable::parse($d['due_date'])->format('d.m.Y') }} | {{ __(':days gün geçti', ['days' => $d['days']]) }} | {{ $d['open'] }} |
@endforeach
@foreach ($upcoming as $d)
| {{ $d['order']['number'] ?? __('Borç') }} | {{ \Carbon\CarbonImmutable::parse($d['due_date'])->format('d.m.Y') }} | {{ $d['days'] === 0 ? __('Bugün') : __(':days gün kaldı', ['days' => $d['days']]) }} | {{ $d['open'] }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('app.shop.payments.index')">
{{ __('Cari Hesabım') }}
</x-mail::button>

{{ __('Ödemenizi yaptıysanız bu e-postayı dikkate almayın.') }}

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
