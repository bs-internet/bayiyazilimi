<x-mail::message>
# {{ __('Merhaba,') }}

{{ __('Hesabiniza daha once kullanilmayan bir cihazdan giris yapildi.') }}

<x-mail::panel>
{{ __('IP adresi: :ip_address', ['ip_address' => $ipAddress]) }}

{{ __('Zaman: :login_at', ['login_at' => $loginAt]) }}

{{ __('Tarayici: :agent', ['agent' => $userAgent ?? __('Bilinmiyor')]) }}
</x-mail::panel>

{{ __('Bu girisi siz yaptiysaniz herhangi bir islem yapmaniz gerekmez.') }}

{{ __('Bu girisi siz yapmadiysaniz lutfen sifrenizi degistirin ve destek ekibiyle iletisime gecin.') }}

{{ __('Saygilar,') }}<br>
{{ \App\Support\Branding::name() }}
</x-mail::message>
