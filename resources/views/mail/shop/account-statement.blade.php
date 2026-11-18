<x-mail::message>
# {{ __('Cari hesap ekstreniz') }}

{{ __('Merhaba,') }}

{{ __(':account (:code) carinizin :period dönemine ait ekstresi ektedir.', ['account' => $account->title ?? $account->code, 'code' => $account->code, 'period' => $period]) }}

{{ __('**Dönem sonu bakiye:** :amount (:side)', ['amount' => $closing['formatted'], 'side' => $closing['side_label']]) }}

<x-mail::button :url="route('app.shop.payments.index')">
{{ __('Cari Hesabım') }}
</x-mail::button>

{{ __('Kayıtlarınızla uyuşmayan bir hareket varsa lütfen bize bildirin.') }}

{{ __('Saygılarımızla,') }}<br>
{{ \App\Services\Shop\ShopSettings::get('store_name') }}
</x-mail::message>
