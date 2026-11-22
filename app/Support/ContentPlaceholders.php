<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BankAccount;
use App\Services\Shop\ShopSettings;
use Closure;

/**
 * Sözleşme ve içerik sayfası metinlerindeki yer tutucular: `{unvan}`,
 * `{vergi_no}`, `{banka_hesaplari}` … gösterilirken Kurumsal Bilgiler ve
 * Banka Hesapları'ndaki güncel değerlerle doldurulur; bilgi tek yerden değişir.
 * Eklentiler `content.placeholders` filtresiyle yer tutucu ekler
 * (`['anahtar' => ['label' => …, 'value' => fn () => 'güvenli HTML']]`).
 */
final class ContentPlaceholders
{
    /** Kendi paragrafını tablo gibi blok bir içerikle değiştiren yer tutucular. */
    private const BLOCK = ['banka_hesaplari'];

    /**
     * Metni temizlenmiş HTML olarak alır, yer tutucuları doldurur. Değerler
     * kaçışlanır; boş değer boş metin olur.
     */
    public static function fill(string $html): string
    {
        if (! str_contains($html, '{')) {
            return $html;
        }

        foreach (self::definitions() as $key => $definition) {
            $token = '{'.$key.'}';

            if (! str_contains($html, $token)) {
                continue;
            }

            $value = (string) ($definition['value'])();

            // Tek başına bir paragraftaki blok yer tutucu paragrafın yerine geçer
            // (<p> içinde <table> geçersiz HTML'dir).
            if (in_array($key, self::BLOCK, true)) {
                $html = (string) preg_replace('#<p>\s*'.preg_quote($token, '#').'\s*</p>#u', $value, $html);
            }

            $html = str_replace($token, $value, $html);
        }

        return $html;
    }

    /**
     * Düzenleme ekranındaki ipucu listesi.
     *
     * @return array<int, array{token: string, label: string}>
     */
    public static function catalog(): array
    {
        return collect(self::definitions())
            ->map(fn (array $definition, string $key) => ['token' => '{'.$key.'}', 'label' => $definition['label']])
            ->values()->all();
    }

    /**
     * @return array<string, array{label: string, value: Closure(): string}>
     */
    private static function definitions(): array
    {
        $setting = fn (string $key) => fn (): string => e(trim((string) ShopSettings::get($key)));

        /** @var array<string, array{label: string, value: Closure(): string}> */
        return hooks()->apply('content.placeholders', [
            'unvan' => ['label' => __('Firma ünvanı'), 'value' => $setting('store_name')],
            'vergi_dairesi' => ['label' => __('Vergi dairesi'), 'value' => $setting('tax_office')],
            'vergi_no' => ['label' => __('Vergi no'), 'value' => $setting('tax_number')],
            'mersis_no' => ['label' => __('MERSİS no'), 'value' => $setting('mersis_number')],
            'adres' => ['label' => __('Adres (tek satır)'), 'value' => fn (): string => e(ShopSettings::storeAddressLine())],
            'telefon' => ['label' => __('Telefon'), 'value' => $setting('store_phone')],
            'eposta' => ['label' => __('E-posta'), 'value' => $setting('store_email')],
            // Metin içinde okunur biçim: "dayland.com.tr" (şema ve sondaki / olmadan).
            'web' => ['label' => __('Site adresi'), 'value' => fn (): string => e((string) parse_url(url('/'), PHP_URL_HOST))],
            'banka_hesaplari' => ['label' => __('Aktif banka hesapları (tablo)'), 'value' => fn (): string => self::bankAccounts()],
        ]);
    }

    private static function bankAccounts(): string
    {
        $accounts = BankAccount::forDisplay();

        if ($accounts === []) {
            return '';
        }

        $rows = collect($accounts)->map(fn (array $a) => '<tr>'
            .'<td>'.e($a['bank_name']).($a['branch'] ? '<br>'.e($a['branch']) : '').'</td>'
            .'<td>'.e($a['account_holder']).'</td>'
            .'<td>'.e($a['iban']).($a['account_number'] ? '<br>'.e(__('Hesap no: :number', ['number' => $a['account_number']])) : '').'</td>'
            .'<td>'.e($a['currency_code'] ?? '').'</td>'
            .'</tr>')->implode('');

        return '<table><thead><tr>'
            .'<th>'.e(__('Banka')).'</th>'
            .'<th>'.e(__('Hesap sahibi')).'</th>'
            .'<th>'.e(__('IBAN')).'</th>'
            .'<th>'.e(__('Para birimi')).'</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
    }
}
