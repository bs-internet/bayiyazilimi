# Bayi Yazılımı

Toptan satış yapan firmalar için **ücretsiz ve açık kaynak** bayi (B2B) yazılımı.
Bayileriniz kendi fiyatlarıyla sipariş verir, teklif ister, carisini takip eder;
siz siparişi, stoğu, depoyu ve tahsilatı tek panelden yönetirsiniz.

[bayiyazilimi.com](https://bayiyazilimi.com) · Geliştiren: Bülent Sakarya

## Neler yapar

**Bayi paneli**
- Bayiye özel fiyat, iskonto ve kampanyalarla sipariş; hızlı sipariş ve kayıtlı sepetler
- Teklif isteme, teklifi onaylayıp siparişe çevirme
- Cari hesap ekstresi, havale bildirimi, kartla ödeme (ödenecek siparişleri seçerek)
- Sipariş, iade ve sözleşme takibi

**Yönetim paneli**
- **Satış:** sipariş ve teklif yönetimi, bayi grupları, kademeli / birleşebilen / limitli kampanyalar
- **Cari ve tahsilat:** cari defter, peşin ve vadeli çalışma, kredi limiti, tahsilatı siparişlere bağlama,
  çek / senet portföyü, çoklu para birimi ve TCMB kurları
- **Stok ve depo:** çoklu depo, raf lokasyonları, mal kabul, tedarikçiler, lot / seri / son kullanma
  tarihi, sayım, toplama listesi, stok rezervasyonu
- **Katalog:** ürün, kategori, marka, birim, kargo; Excel ile toplu ürün aktarımı ve dışa aktarım
- **Raporlar** ve Excel çıktıları
- **Kullanıcılar:** rol ve yetki yönetimi, iki adımlı doğrulama, etkinlik ve oturum kayıtları
- **Entegrasyon:** ERP / muhasebe yazılımları için REST API (`/api/v1`, yetkili API anahtarlarıyla)

## Eklentiler

Çekirdek tamamen ücretsizdir. Mağaza ön yüzü (B2C vitrin), sanal pos (PayTR, iyzico),
muhasebe entegrasyonları, plasiyer ekranı gibi ek özellikler ayrı paket olarak dağıtılan
eklentilerle gelir. Kurulu sistemde **Ayarlar → Eklentiler** ekranında tüm eklentiler
listelenir ve kurulu olanlar buradan açılıp kapatılır.

Eklenti listesi, fiyatlar ve destek paketleri: [bayiyazilimi.com](https://bayiyazilimi.com)

## Gereksinimler

- PHP 8.4 ya da üzeri ve Composer
- MySQL 8+ ya da MariaDB 10.6+ (başka veritabanı desteklenmez)
- Node.js 22 LTS (en az 20.19) — ön yüzü derlemek için
- Cron (zamanlanmış görevler) ve kuyruk çalıştırıcı (e-postalar)

Ücretli eklentiler sunucuda Composer ve `npm run build` çalıştırmayı gerektirir; bunlar için
en az 2 CPU, 2 GB RAM, 20 GB disk olan bir VPS önerilir.

## Kurulum

Önce boş bir veritabanı oluşturun (`utf8mb4`). Kurulumda iki seçenek sunulur:
**Boş yükle** (yalnız ülke, il / ilçe, para birimi, vergi, rol ve ayarlar gibi temel veriler)
ya da **Örnek verileri yükle** (örnek ürünler, bayi hesabı ve sipariş). Yönetici hesabını
kurulumda siz belirlersiniz.

### Komut satırından (önerilen)

```bash
git clone https://github.com/bs-internet/bayiyazilimi.git
cd bayiyazilimi
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan bayi:setup
```

`bayi:setup` veritabanı bilgilerini, site adresini ve yönetici hesabını sorar; `.env`
dosyasını yazar, tabloları kurar ve sistemi canlı kipe alır.

### Tarayıcıdan

Bağımlılıkları kurulmuş ve ön yüzü derlenmiş dosyaları (`vendor/` ve `public/build/` dahil)
sunucuya yükleyip site adresini açın; paylaşımlı hostingde de çalışır. Kurulum sihirbazı (`/kurulum`)
gereksinimleri kontrol eder, veritabanı bilgilerini ve yönetici hesabını alır. Kurulum
bitince sihirbaz kapanır. Dosyaları yükledikten sonra kurulumu bekletmeden tamamlayın.

## Canlı ortam ayarları

**Zamanlanmış görevler.** Kur güncelleme, süresi dolan kart ödemeleri, stok
rezervasyonları ve lot süreleri zamanlayıcıyla çalışır. Cron'a ekleyin:

```
* * * * * cd /yol/bayiyazilimi && php artisan schedule:run >> /dev/null 2>&1
```

**Kuyruk.** E-postalar ve bildirimler kuyrukla gönderilir. Supervisor gibi bir süreç
yöneticisiyle sürekli çalıştırın:

```bash
php artisan queue:work --tries=3
```

**E-posta.** Gönderim ayarları `.env` dosyasındaki `MAIL_*` satırlarındadır (SMTP sunucusu,
kullanıcı, şifre, gönderen adresi).

**Proxy / CDN.** Sunucu Cloudflare ya da bir ters proxy arkasındaysa proxy adreslerini
yazın (`cloudflare` Cloudflare aralıklarının tamamıdır; başka proxy varsa virgülle eklenir:
`cloudflare,10.0.0.1`); yoksa istek sınırları ve kayıtlardaki IP adresleri yanlış olur:

```
BAYIYAZILIMI_TRUSTED_PROXIES=cloudflare
```

**İçerik güvenlik politikası (CSP).** Sayfalar, yalnız sistemin kendi betiklerinin
çalışmasına izin veren bir CSP başlığı gönderir. Varsayılan kip `report`: tarayıcı hiçbir
şeyi engellemez, kurala uymayanları bildirir; bildirimler `storage/logs` altındaki günlüğe
"CSP ihlali" olarak yazılır. Panel, bayi paneli, ödeme ve (varsa) vitrin bir süre
kullanıldıktan sonra günlükte ihlal görülmüyorsa engellemeyi açın:

```
BAYIYAZILIMI_CSP=enforce
```

## Güncelleme

Yeni sürümün dosyalarını alın (`git pull` ya da yüklenen paket), ardından:

```bash
composer install --no-dev --optimize-autoloader
php artisan bayi:update --build
```

`bayi:update` siteyi bakıma alır, veritabanı yedeği alır (`storage/app/private/backups`,
sunucuda `mysqldump` varsa), tabloları günceller, eklentilerin kurulum adımlarını çalıştırır,
önbelleği temizler ve kuyruğu yeniden başlatır. Komut satırı olmayan sunucularda Super Admin
panelde çıkan **Güncellemeyi tamamla** düğmesiyle aynı işi yapar.

## Eklenti kurulumu

```bash
composer require bsinternet/<eklenti>
php artisan bayi:install <eklenti>     # tablolar, yetkiler, yayımlanan dosyalar
npm run build
```

Kaldırırken sıra önemlidir; `bayi:uninstall` paketi silmeden **önce** çalışır:

```bash
php artisan bayi:uninstall <eklenti>   # verileri silmek için: --purge-data
composer remove bsinternet/<eklenti>
npm run build
```

Ücretli eklentiler için önce eklentinin deposu tanımlanır
(`composer config repositories.<eklenti> vcs <adres>`); adres eklentiyle birlikte verilir.

## Geliştirme

```bash
composer setup                 # bağımlılıklar, .env, anahtar, tablolar, ön yüz
php artisan migrate --seed     # geliştirme verileri — giriş: admin@admin.com / password
composer dev                   # sunucu + kuyruk + Vite
composer ci:check              # lint + biçim + tip kontrolü + testler
```

Laravel 13 · React 19 · TypeScript · Inertia.js v3 · Tailwind CSS v4 · shadcn/ui · Pest 4.
Mimari ve kurallar [AGENTS.md](AGENTS.md), katkı akışı [CONTRIBUTING.md](CONTRIBUTING.md) dosyasındadır.

## Güvenlik açığı bildirimi

Güvenlik açıklarını herkese açık issue olarak yazmayın. GitHub'da bu deponun
**Security → Report a vulnerability** bölümünden gizli olarak bildirin.

## Lisans

[GNU AGPLv3](LICENSE) ve [ek koşullar](EK-KOSULLAR.md) · © 2026 Bülent Sakarya

- Ücretsiz kullanabilir, kendi sunucunuza kurabilir, değiştirebilir ve değiştirilmiş sürümü
  satabilirsiniz. Değiştirilmiş sürümü dağıtan ya da internet üzerinden hizmet olarak sunan,
  kaynak kodunu da kullanıcılarına vermek zorundadır.
- Giriş ekranındaki ve yönetim panelindeki "Bayi Yazılımı altyapısıyla çalışır · © Bülent Sakarya"
  atfı kaldırılamaz. Değiştirilmiş sürüm "Bayi Yazılımı" adıyla ürün olarak sunulamaz.
- Eklenti sistemiyle yazılıp ayrı paket olarak dağıtılan eklentiler AGPL'ye bağlı değildir;
  dilediğiniz lisansla dağıtabilir ve satabilirsiniz.
