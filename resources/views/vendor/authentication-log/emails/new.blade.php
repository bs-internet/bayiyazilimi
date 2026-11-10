@component('mail::message')
# {{ __('Merhaba!') }}

{{ __(':app hesabınıza yeni bir cihazdan giriş yapıldı.', ['app' => \App\Support\Branding::name()]) }}

> {{ __('**Hesap:** :email', ['email' => $account->email]) }}<br>
> {{ __('**Zaman:** :time', ['time' => $time->toCookieString()]) }}<br>
> {{ __('**IP Adresi:** :ip_address', ['ip_address' => $ipAddress]) }}<br>
> {{ __('**Tarayıcı:** :browser', ['browser' => $browser]) }}

{{ __('Bu giriş size aitse bu uyarıyı dikkate almayabilirsiniz. Hesabınızda şüpheli bir hareket olduğunu düşünüyorsanız lütfen şifrenizi değiştirin.') }}

{{ __('Saygılar,') }}<br>{{ \App\Support\Branding::name() }}
@endcomponent
