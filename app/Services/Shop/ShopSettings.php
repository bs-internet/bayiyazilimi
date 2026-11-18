<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Setting;
use App\Services\Support\FileService;
use App\Support\Shop\SystemDefaults;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Mağazaya ait ayarların (settings tablosu, `shop` grubu) tek erişim noktası.
 *
 * Değerler çekirdeğin önbellekli Setting deposundan okunur; varsayılanlar
 * burada tanımlıdır. Panelde "Mağaza Ayarları" ekranı bu anahtarları yönetir.
 */
class ShopSettings
{
    public const GROUP = 'shop';

    /**
     * Genel Ayarlar › Kurumsal Bilgiler ekranının yönettiği anahtarlar; Site Ayarları
     * ekranı bunlara dokunmaz.
     */
    public const CORPORATE_KEYS = [
        'store_name', 'tax_office', 'tax_number', 'mersis_number', 'store_email', 'store_phone',
        'store_country_id', 'store_city_id', 'store_district_id', 'store_address', 'store_postcode',
        'etbis_url',
    ];

    /**
     * Genel Ayarlar › Site Erişimi ekranının yönettiği anahtarlar.
     */
    public const ACCESS_KEYS = ['maintenance_mode', 'maintenance_message'];

    /**
     * Tanımlamalar › Banka Hesapları ekranının yönettiği anahtarlar.
     */
    public const PAYMENT_KEYS = ['bank_transfer_enabled'];

    /**
     * Tanımlamalar › Kargo Ayarları ekranının yönettiği anahtarlar.
     */
    public const SHIPPING_KEYS = ['shipping_fee', 'free_shipping_threshold', 'default_product_desi'];

    /**
     * Tanımlamalar › Para Birimleri › TCMB ayarları kartının yönettiği anahtarlar.
     */
    public const RATE_KEYS = ['tcmb_auto_update', 'tcmb_rate_type'];

    /**
     * Stok › Stok Ayarları (varsayılan depo `warehouses.is_default` ile tutulur).
     */
    public const STOCK_KEYS = ['show_out_of_stock', 'low_stock_threshold', 'low_stock_notify', 'traceability_enabled', 'expiry_warning_days', 'stock_reservation_days'];

    /**
     * Tanımlamalar › Satış Ayarları.
     */
    public const SALES_KEYS = ['price_display', 'dealer_price_display', 'quote_valid_days', 'return_days', 'order_notification_email'];

    /**
     * Anahtar => varsayılan değer (çekirdek). Modüller kendi anahtarlarını
     * `shop.settings.defaults` filtresiyle ekler; okuma/yazma için defaults().
     */
    public const DEFAULTS = [
        // Kurumsal bilgiler (WhatsApp, çalışma saatleri, iletişim sayfası ve
        // sosyal medya vitrin modülünün İletişim Bilgileri ekranındadır).
        'store_name' => 'Bayi Yazılımı',
        'store_email' => '',
        'store_phone' => '',
        // Adres: açık adres + ülke / il / ilçe (Tanımlamalar → Bölgeler); tek satır için storeAddressLine().
        'store_address' => '',
        'store_country_id' => '',
        'store_city_id' => '',
        'store_district_id' => '',
        'store_postcode' => '',
        // Sipariş / teklif / iade / ödeme bildirimlerinin yöneticiye gittiği adres (Satış Ayarları).
        'order_notification_email' => '',
        'tax_office' => '',
        'tax_number' => '',
        'mersis_number' => '',
        // ETBİS: kare kod görseli (public diskte yol; Kurumsal Bilgiler ekranından anında yüklenir) ve doğrulama bağlantısı.
        'etbis_qr_path' => '',
        'etbis_url' => '',

        // Kargo (desi fiyatları Kargo Firmaları ekranından; sabit ücret yalnızca aktif firma yoksa kullanılır)
        'shipping_fee' => '0',
        'free_shipping_threshold' => '0',
        'default_product_desi' => '1',

        // Ödeme
        'bank_transfer_enabled' => '1',
        // Havale indirimi (%): ödeme ekranında havale seçilince KDV dahil toplamdan düşülür.
        'bank_transfer_discount_rate' => '0',
        // Yönteme göre en düşük sipariş tutarı (ana para biriminde), JSON: {"bank_transfer": 500}.
        'payment_min_amounts' => '{}',

        // Cari: çek / senet portföyü isteğe bağlıdır (varsayılan kapalı; kapalıyken ekranlarda yer almaz).
        'cheques_enabled' => '0',
        // Vadesi bu kadar gün içinde gelecek çek / senetler "vadesi yaklaşan" sayılır.
        'cheque_warning_days' => '7',
        // Vadesi bu kadar gün içinde gelecek cari borçlar bayiye "vadesi yaklaşan" olarak gösterilir.
        'due_warning_days' => '7',
        // Vade hatırlatması: vadesi yaklaşan (due_warning_days) ve geçen borç için bayiye
        // günlük görevle e-posta gider (shop:send-due-reminders). Varsayılan kapalı.
        'due_reminders_enabled' => '0',
        // Vadesi geçmiş borç hatırlatması bu kadar günde bir yinelenir. 0 = yalnız bir kez.
        'overdue_reminder_repeat_days' => '7',
        // Bayi Cari Hesabım'da kredi limitini (kullanılabilir limit, risk) görsün mü.
        // Limiti tanımlanmamış bayiye hiç gösterilmez.
        'show_credit_limit' => '1',

        // Stok & fiyat
        'show_out_of_stock' => '1',
        // Kritik stok: ürüne özel eşik yoksa bu değer; eşiğe inince yetkili yöneticilere bildirim.
        'low_stock_threshold' => '5',
        'low_stock_notify' => '1',
        // Lot / seri no / SKT takibi (isteğe bağlı özellik). Kapalıyken tüm ürünler
        // takipsiz davranır, ilgili alanlar ve ekranlar gizlenir; kayıtlı lotlar silinmez.
        'traceability_enabled' => '0',
        // SKT'si bu kadar gün içinde dolacak lotlar "SKT yaklaşan" sayılır.
        'expiry_warning_days' => '30',
        // Stok sipariş anında düşülür: ödemesi gelmeyen (havale) ya da onaylanmayan
        // (vadeli) sipariş bu kadar gün sonra kapanır ve stok serbest kalır. 0 = süresiz.
        'stock_reservation_days' => '3',
        'tcmb_auto_update' => '1',
        'tcmb_rate_type' => 'ForexSelling',
        'quote_valid_days' => '15',
        'return_days' => '14',
        // Fiyat gösterimi: gross = KDV dahil, net = KDV hariç (girildiği gibi)
        // Site erişimi: bakım modu (yalnız yöneticiler girer). Siteyi ziyaretçiye
        // açan vitrin modülüdür; modül yoksa site yalnız bayilere açıktır.
        'maintenance_mode' => '0',
        'maintenance_message' => 'Sitemizde bakım çalışması yapılmaktadır. Kısa süre içinde yeniden hizmetinizdeyiz.',
        'price_display' => 'gross',
        'dealer_price_display' => 'net',
    ];

    /**
     * Gizli değerler (API anahtarı, şifre, imza tuzu …): veritabanında şifreli
     * saklanır, all() ile hiçbir yere toplu gönderilmez, boş bırakılırsa üzerine
     * yazılmaz. Eklentiler kendi anahtarlarını `shop.settings.secret_keys`
     * filtresiyle bildirir; bildirmeyi unutan eklentinin anahtarı da adından
     * (SECRET_PATTERN) gizli sayılır.
     */
    public const SECRET_KEYS = [];

    /** Adından gizli olduğu anlaşılan anahtarlar: *_secret, *_password, *_token, *_salt, *_api_key … */
    public const SECRET_PATTERN = '/(^|_)(secret|password|token|salt)$|(^|_)(api|secret|service|merchant|private|client)_key$/';

    /** Çekirdeğin şifrelediği değerin ön eki (eklentinin kendi şifrelediği değerden ayırt etmek için). */
    private const ENCRYPTED_PREFIX = 'enc:v1:';

    /**
     * Çekirdek + modüllerin (`shop.settings.defaults`) anahtarları ve varsayılanları.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return once(fn () => [...self::DEFAULTS, ...hooks()->apply('shop.settings.defaults', [])]);
    }

    /**
     * Gizli anahtarlar: çekirdek listesi + eklentilerin `shop.settings.secret_keys`
     * filtresi + adı SECRET_PATTERN'e uyan tanımlı anahtarlar.
     *
     * @return list<string>
     */
    public static function secretKeys(): array
    {
        return once(function (): array {
            $declared = hooks()->apply('shop.settings.secret_keys', []);
            $byName = array_filter(array_keys(self::defaults()), fn (string $key): bool => preg_match(self::SECRET_PATTERN, $key) === 1);

            return array_values(array_unique([...self::SECRET_KEYS, ...(is_array($declared) ? $declared : []), ...$byName]));
        });
    }

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::secretKeys(), true) || preg_match(self::SECRET_PATTERN, $key) === 1;
    }

    public static function get(string $key): ?string
    {
        $value = Setting::get($key);

        if ($value !== null && str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            try {
                return Crypt::decryptString(substr($value, strlen(self::ENCRYPTED_PREFIX)));
            } catch (DecryptException) {
                // Uygulama anahtarı değişmiş (APP_PREVIOUS_KEYS verilmemiş): değer okunamaz, yeniden girilmeli.
                return '';
            }
        }

        return $value ?? (self::defaults()[$key] ?? null);
    }

    /**
     * Gizli değeri şifreli biçime çevirir (zaten şifreliyse dokunmaz). Değer
     * eklentinin kendi şifrelediği metin olabilir; get() yalnız çekirdeğin
     * katmanını açar, eklentinin katmanı eklentiye kalır.
     */
    public static function encryptValue(string $value): string
    {
        if ($value === '' || str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        return self::ENCRYPTED_PREFIX.Crypt::encryptString($value);
    }

    /**
     * Mağaza adresinin tek satırı: "açık adres, posta kodu ilçe / il[, ülke]".
     * Ülke yalnızca sistemin varsayılan ülkesinden farklıysa yazılır.
     */
    public static function storeAddressLine(): string
    {
        $district = District::query()->whereKey(self::get('store_district_id') ?: null)->value('name');
        $city = City::query()->whereKey(self::get('store_city_id') ?: null)->value('name');
        $countryId = self::get('store_country_id') ?: null;
        $country = $countryId && $countryId !== SystemDefaults::countryId()
            ? Country::query()->whereKey($countryId)->value('name')
            : null;

        $place = trim(self::get('store_postcode').' '.implode(' / ', array_filter([$district, $city])));

        return implode(', ', array_filter([trim((string) self::get('store_address')), $place, $country]));
    }

    /**
     * ETBİS kare kod görselinin herkese açık adresi (yüklenmemişse null).
     */
    public static function etbisQrUrl(): ?string
    {
        $path = self::get('etbis_qr_path');

        return $path ? app(FileService::class)->publicUrl($path) : null;
    }

    public static function bool(string $key): bool
    {
        return in_array(self::get($key), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Lot / seri no / SKT takibi açık mı (Stok Ayarları)?
     */
    public static function traceability(): bool
    {
        return self::bool('traceability_enabled');
    }

    public static function float(string $key): float
    {
        return (float) str_replace(',', '.', (string) self::get($key));
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /**
     * Gizli olmayan tüm ayarlar. Gizli anahtarlar (secretKeys()) bilerek dışarıda
     * kalır: bu dizi ekranlara / vitrine toplu gönderilebilir. Gizli değer yalnız
     * get() ile, adıyla okunur.
     *
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        $result = [];

        foreach (array_keys(self::defaults()) as $key) {
            if (! self::isSecret($key)) {
                $result[$key] = self::get($key);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::defaults())) {
                continue;
            }

            $secret = self::isSecret($key);

            if ($secret && ($value === null || $value === '')) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            if ($secret) {
                $value = self::encryptValue((string) $value);
            }

            // Boşaltılan alan '' olarak saklanır; null kalsaydı get() varsayılana
            // dönerdi ve varsayılanı dolu bir alan (ör. sosyal medya) temizlenemezdi.
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'group' => self::GROUP],
            );
        }
    }
}
