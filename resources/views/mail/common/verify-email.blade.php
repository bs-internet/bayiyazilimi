<x-mail::message>
# {{ __('Merhaba,') }}

{{ __('Hesabınızı kullanmaya devam etmek için e-posta adresinizi doğrulamanız gerekiyor.') }}

<x-mail::button :url="$verificationUrl">
{{ __('E-posta Adresimi Doğrula') }}
</x-mail::button>

{{ __('Bu hesabı siz oluşturmadıysanız herhangi bir işlem yapmanız gerekmez.') }}

{{ __('Saygılar,') }}<br>
{{ \App\Support\Branding::name() }}

<x-slot:subcopy>
{{ __('E-posta Adresimi Doğrula butonuna tıklayamıyorsanız aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:') }}
[{{ $verificationUrl }}]({{ $verificationUrl }})
</x-slot:subcopy>
</x-mail::message>
