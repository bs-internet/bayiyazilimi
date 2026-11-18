<x-mail::message>
# {{ __('Sistemde hata oluştu') }}

{{ __('**Hata:** :type', ['type' => $error->type]) }}<br>
{{ __('**Yer:** :location', ['location' => $error->location]) }}<br>
@if ($error->url !== null)
{{ __('**İstek:** :url', ['url' => $error->url]) }}<br>
@endif
{{ __('**Zaman:** :time', ['time' => $error->occurredAt->format('d.m.Y H:i:s')]) }}

<x-mail::panel>
{{ $error->message }}
</x-mail::panel>

{{ __('Ayrıntılar sunucudaki storage/logs günlüğündedir. Aynı hata için :minutes dakika içinde yeniden e-posta gönderilmez.', ['minutes' => (int) config('bayiyazilimi.error_alerts.throttle', 60)]) }}
</x-mail::message>
