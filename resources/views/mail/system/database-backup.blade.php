<x-mail::message>
# {{ __('Veritabanı yedeği') }}

{{ __('**Dosya:** :file', ['file' => $fileName]) }}<br>
{{ __('**Boyut (sıkıştırılmış):** :size MB', ['size' => $sizeMb]) }}

@if ($archive !== null)
{{ __('Yedek ekteki zip dosyasındadır; zip şifrelidir (AES-256). Şifre sunucudaki .env dosyasında BAYIYAZILIMI_BACKUP_MAIL_PASSWORD satırıdır. Açmak için 7-Zip ya da WinRAR kullanın (Windows\'un kendi zip açıcısı AES şifreli dosyayı açamaz).') }}
@else
{{ __('Yedek e-posta ekine sığmayacak kadar büyük olduğu için eklenmedi; sunucuda storage/app/private/backups altında duruyor.') }}
@endif
</x-mail::message>
