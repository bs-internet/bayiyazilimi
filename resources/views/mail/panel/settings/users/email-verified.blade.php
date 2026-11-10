<x-mail::message>
# {{ __('Merhaba :name,', ['name' => $user->name]) }}

{{ __('Panel hesabınıza ait e-posta adresi bir yönetici tarafından onaylandı.') }}

{{ __('Bu işlem size ait değilse lütfen destek ekibiyle iletişime geçin.') }}

{{ __('Saygılar,') }}<br>
{{ \App\Support\Branding::name() }}
</x-mail::message>
