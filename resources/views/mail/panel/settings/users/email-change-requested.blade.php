<x-mail::message>
# {{ __('Merhaba :name,', ['name' => $user->name]) }}

{{ __('Panel hesabınızın e-posta adresi için değişiklik talebi oluşturuldu.') }}

{{ __('Mevcut e-posta adresi: **:email**', ['email' => $user->email]) }}  
{{ __('Yeni e-posta adresi: **:email**', ['email' => $email]) }}

{{ __('Bu değişikliği onaylamak için aşağıdaki bağlantıya tıklayın. Bağlantıya tıkladığınızda e-posta adresiniz değiştirilecek, yeni adresiniz onaylanmış sayılacak ve hesabınıza ait diğer oturumlar kapatılacaktır.') }}

<x-mail::button :url="$confirmationUrl">
{{ __('E-posta Adresimi Değiştir') }}
</x-mail::button>

{{ __('Bu talebi siz beklemiyorsanız bağlantıya tıklamayın ve destek ekibiyle iletişime geçin.') }}

{{ __('Saygılar,') }}<br>
{{ \App\Support\Branding::name() }}

<x-slot:subcopy>
{{ __('E-posta Adresimi Değiştir butonuna tıklayamıyorsanız aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:') }}
[{{ $confirmationUrl }}]({{ $confirmationUrl }})
</x-slot:subcopy>
</x-mail::message>
