<x-mail::message>
# {{ __('Merhaba :name,', ['name' => $user->name]) }}

{{ __('Panel hesabınızın durumu **:status** olarak güncellendi.', ['status' => $status->label()]) }}

{{ __('Bu işlem size ait değilse lütfen destek ekibiyle iletişime geçin.') }}

{{ __('Saygılar,') }}<br>
{{ \App\Support\Branding::name() }}
</x-mail::message>
