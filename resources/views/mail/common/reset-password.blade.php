<x-mail::message>
# {{ __('Merhaba,') }}

{{ __('Hesabınız için bir şifre sıfırlama talebi aldık.') }}

<x-mail::button :url="$resetUrl">
{{ __('Şifreyi Sıfırla') }}
</x-mail::button>

{{ __('Bu bağlantı :expire_minutes dakika içinde geçerliliğini yitirecek.', ['expire_minutes' => $expireMinutes]) }}

{{ __('Bu işlemi siz başlatmadıysanız herhangi bir işlem yapmanız gerekmez.') }}

{{ __('Saygılar,') }}<br>
{{ \App\Support\Branding::name() }}

<x-slot:subcopy>
{{ __('Şifreyi Sıfırla butonuna tıklayamıyorsanız aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:') }}
[{{ $resetUrl }}]({{ $resetUrl }})
</x-slot:subcopy>
</x-mail::message>
