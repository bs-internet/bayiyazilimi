<x-mail::message>
# {{ __('Merhaba :name,', ['name' => $user->name]) }}

{{ __('Panel hesabınız oluşturuldu.') }}

{{ __('Şifrenizi belirlemek ve hesabınızı kullanmaya başlamak için aşağıdaki bağlantıya tıklayın.') }}

@if ($verifiesEmail)
{{ __('Bağlantıya tıkladığınızda e-posta adresiniz önce onaylanacak, ardından şifrenizi belirleyeceğiniz ekrana yönlendirileceksiniz.') }}
@else
{{ __('E-posta adresiniz onaylanmış durumda; bağlantı sizi doğrudan şifre belirleme ekranına yönlendirecek.') }}
@endif

<x-mail::button :url="$welcomeUrl">
{{ __('Şifremi Belirle') }}
</x-mail::button>

{{ __('Bu bağlantı :expire_minutes dakika içinde geçerliliğini yitirecek.', ['expire_minutes' => $expireMinutes]) }}

{{ __('Bu hesabı beklemiyorsanız bağlantıya tıklamayın ve destek ekibiyle iletişime geçin.') }}

{{ __('Saygılar,') }}<br>
{{ \App\Support\Branding::name() }}

<x-slot:subcopy>
{{ __('Şifremi Belirle butonuna tıklayamıyorsanız aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:') }}
[{{ $welcomeUrl }}]({{ $welcomeUrl }})
</x-slot:subcopy>
</x-mail::message>
