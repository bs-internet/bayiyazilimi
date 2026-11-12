# AGENTS.md

Bayi Yazılımı'nda kod yazan herkesin (insan ya da yapay zekâ) mühendislik rehberi:
mimari, değişmez kurallar ve günlük komutlar. Tek kaynak bu dosyadır; `CLAUDE.md`
bunu içe aktarır. Kurulum ve kullanım için [README.md](README.md), katkı akışı için
[CONTRIBUTING.md](CONTRIBUTING.md).

## Genel bakış

- Tek Laravel uygulaması, iki kullanıcı alanı: **yönetim paneli** (`/panel`, yöneticiler)
  ve **bayi paneli** (`/app`, üyeler). Ayrıca ödeme dönüşleri (`/odeme`), kurulum
  sihirbazı (`/kurulum`) ve REST API (`/api/v1`).
- Çekirdek **B2B**'dir. Mağaza ön yüzü (vitrin), sanal pos, muhasebe entegrasyonları,
  plasiyer ekranı gibi her şey ayrı depolardaki **eklentilerdir** (`bsinternet/*`
  composer paketleri). Çekirdek hiçbir eklentiyi tanımaz; eklentiler çekirdeğin
  kancalarına bağlanır.
- Arayüz Türkçedir. Kod tanımlayıcıları İngilizce, kod yorumları Türkçe yazılır.
- Lisans: AGPL-3.0-only + [EK-KOSULLAR.md](EK-KOSULLAR.md) · © Bülent Sakarya.

## Teknoloji

- **Sunucu:** PHP 8.4+, Laravel 13, Pest 4. Yalnız **MySQL / MariaDB** (testler dahil;
  ayrı `bayiyazilimi_test` veritabanı).
- **Ön yüz:** React 19, TypeScript (strict), Inertia.js v3, Tailwind CSS v4, shadcn/ui, Vite.
  React Compiler açık.
- **Kimlik:** Laravel Fortify (giriş, e-posta doğrulama, şifre sıfırlama, iki adımlı doğrulama);
  REST API için Laravel Sanctum kişisel erişim anahtarları.
- **Tipli rotalar:** Laravel Wayfinder rota / action yardımcılarını `resources/js/{routes,actions}`
  altına üretir.
- **Önemli paketler:** `spatie/laravel-permission`, `spatie/laravel-activitylog`,
  `yadahan/laravel-authentication-log`, `brick/money`, `openspout/openspout`.

## Komutlar

```bash
composer setup            # ilk kurulum: bağımlılıklar, .env, anahtar, tablolar, derleme
php artisan migrate --seed  # geliştirme verileri (admin@admin.com / password)
composer dev              # sunucu + kuyruk + Vite

composer ci:check         # ESLint + Prettier + tsc + Pint + Pest — iş bitmeden yeşil olmalı
composer lint             # Pint (düzeltir)
npm run lint              # ESLint --fix
npm run format            # Prettier
npm run types:check       # tsc --noEmit
php artisan test --filter=Ad

php artisan wayfinder:generate --with-form --no-interaction   # her rota değişikliğinden sonra
php artisan lang:sync                                          # her metin değişikliğinden sonra
```

## Değişmez kurallar

1. **Her PHP dosyası** `declare(strict_types=1);` ile başlar. Her model `HasUuids` kullanır.
   Tarihler `CarbonImmutable`.
2. **Yan etkiler yalnız `event() → listener`** ile yapılır (etkinlik kaydı, bildirim, e-posta);
   controller ya da servis bunları doğrudan çağırmaz. Listener'lar otomatik keşfedilir,
   elle kaydedilmez.
3. **`account_id` asla istekten alınmaz;** bağlı hesaptan ya da `Account` ilişkisinden gelir.
4. **Durum alanları** (`status`, ödeme durumu …) yalnız ilgili servisin geçiş metoduyla değişir
   (bkz. [Durum makineleri](#durum-makineleri)).
5. **Para hesabında float yoktur:** `App\Support\Money`, `Decimal` cast, JSON'da string tutar,
   ön yüzde `@/lib/decimal` (bkz. [Para](#para)).
6. **Kullanıcının gördüğü her metin** çeviri fonksiyonundan geçer: PHP'de `__('…')`, TSX'te
   `t('…')`. Anahtar Türkçe metnin kendisidir. Sonra `php artisan lang:sync`.
7. **Adres koda yazılmaz:** ön yüz Wayfinder yardımcılarını, sunucu `route()` kullanır.
   Rotalar değişince `wayfinder:generate`.
8. **Panel rotalarının adı vardır;** rota adı aynı zamanda yetki adıdır.
9. **Çekirdek hiçbir eklentiye başvurmaz.** Eklentiye özgü her şey bir kancanın (sunucu) ya da
   slot'un (ön yüz) arkasına konur.
10. **`resources/js/components/ui/*` elle düzenlenmez** (shadcn üretir). Tek bilinçli istisna:
    [button.tsx](resources/js/components/ui/button.tsx) içindeki genel `cursor-pointer`.
11. **Hazır bekleyen parçalar silinmez** (bkz. [Hazır bekleyen parçalar](#hazır-bekleyen-parçalar)).
12. **Geri alınamaz her işlem** önce [ConfirmDelete](resources/js/components/confirm-delete.tsx)
    ile onay ister.
13. **Satır içi `<script>`** yalnız `nonce="{{ Vite::cspNonce() }}"` ile çalışır (CSP).
14. **Excel çıktısındaki satırlar** `App\Support\SpreadsheetRow::of()` ile yazılır (`=` ile başlayan
    metin formül olarak çalışmasın).
15. **Sayfalı her liste** `App\Support\Pagination::PER_PAGE` kullanır. Bilinçli istisnalar:
    il / ilçe tanım listeleri ve vitrin katalog ızgarası.

## Mimari

### İki alan: panel ve bayi paneli

Kod `Panel/*` (yönetici) ve `App/*` (üye) olarak aynalanır: `app/Http/Controllers`,
`Requests`, `Resources`, `Events`, `Listeners`, `Services` ve `resources/js/pages/{panel,app}`,
düzenler `layouts/{panel,app}`.

- `routes/panel.php` — `/panel`; ara katmanlar `auth, verified, user_type:admin, active_user,
  write_access, route_permission`.
- `routes/app.php` — `/app`; ara katmanlar `auth, verified, user_type:member, active_user,
  write_access, bind_account`.
- `/dashboard` kullanıcıyı türüne göre yönlendirir; tedarikçi ve pasif kullanıcıların oturumu kapatılır.

### Inertia paylaşılan prop'ları

`HandleInertiaRequests::share()` tam olarak şunları gönderir; yenisi eklenmez:

- **`auth`** — tek kimlik sözleşmesi, `auth.type: 'app' | 'panel'` ile ayrılır. React'te
  `usePage().props.auth` okunmaz: bayi tarafında `useAppAuth()`, panelde `usePanelAuth()`.
  `AppUser` ile `PanelUser` karıştırılmaz. Tipler: [types/auth.ts](resources/js/types/auth.ts).
- **`branding`** — uygulama kimliği, `useBranding()` ile okunur.
- **`flash`** — toast verisi.
- **`navigation`** — kullanıcının alanına göre `MenuRegistry`'den kurulan, yetkiye göre süzülmüş,
  `order`'a göre sıralı menü.
- **`modules`** — kurulu ve **etkin** eklenti paketleri; `<Slot>` devre dışı eklentinin
  parçalarını bununla gizler.
- **`systemUpdate`** — kod veritabanından yeniyse Super Admin için true (`UpdateService::pending()`);
  panel "Güncellemeyi tamamla" uyarısını gösterir.
- **`name`**, **`sidebarOpen`**.

### Hesap sahipliği ve kapsam

Üye verisinin sahibi `Account`'tur. Üyeler ve üyeye ait kayıtlar `account_id` taşır; yöneticilerin
`account_id`'si null'dır ve tüm hesapları görür.

Üyeye ait modeller `App\Concerns\BelongsToAccount` kullanır: `account()` ilişkisi, koşullu genel
kapsam ve `creating` kancası. `bind_account` ara katmanı (yalnız `/app` grubunda) giriş yapan
üyenin hesabını bağlar; bağlıyken her sorgu `account_id`'ye göre süzülür ve yeni kayda
`account_id` kendiliğinden yazılır. Panel, kuyruk ve seeder'da bağlı hesap yoktur, kapsam da yoktur.
Hesabı olmayan üye `BelongsToAccountScope::NO_ACCOUNT` olarak bağlanır: sorgular boş döner
(kapsamsız kalıp her şeyi göstermez).

### Olaylar ve bildirimler

- Yan etki zinciri: servis `event(new XEvent(...))` → `app/Listeners` altındaki listener
  (otomatik keşif). Eklentiler kendi listener'larını provider'da `Event::listen` ile bağlar.
- E-posta / bildirim standardı: ince bir `Send{X}` listener'ı `$notifiable->notify(new
  {X}Notification(...))` çağırır. `{X}Notification implements ShouldQueue`, `via` ile
  `mail` / `database` seçer, `toMail()` bir Mailable döndürür, `toArray()` uygulama içi kaydı yazar.
  Rutin e-posta Job'a sarılmaz; Job yalnız bağımsız yaşam döngüsü gerekirse
  (tek örnek: `Auth/DetectNewDeviceLogin`). E-posta görünümleri `resources/views/mail/`.

### Yetkilendirme

Spatie laravel-permission, **arayüzden yönetilir** (yetki yapılandırma dosyası yok).

- **Super Admin** `Gate::before` ile her kontrolü geçer; tek tek yetki almaz, rol seçimlerinde
  sunulmaz (yalnız kurulum / konsol atar).
- **`route_permission`** (yalnız panel): **rota adı = yetki adı**. Adlı her panel rotası
  `$user->can($routeName)` ile korunur; yetkisi henüz tanımlanmamış yeni rotaya yalnız
  Super Admin girer. Adı olmayan panel rotası da yalnız Super Admin'e açılır, diğerleri
  uyarıyla geri döner. Muaf olanlar: `panel.dashboard`, bölüm giriş sayfaları ve kişisel
  `panel.profile.*` rotaları. `PermissionDependencies`: bölümü görme yetkisi olmadan o bölümde
  işlem yapılamaz.
- **Yetkiler ekranı** (`panel/settings/permissions`): yetkileri listeler / düzenler;
  **Rotalardan Keşfet** panel rota adlarını yetki olarak içe alır. `permissions` tablosunda
  arayüz için `group` ve `label` sütunları var.
- **Kurulumda** `RolePermissionSeeder` iki sistem rolünü (`Super Admin`, `Admin`) kurar;
  `PanelPermissionSeeder` keşfedilebilen tüm panel yetkilerini (eklentilerinki dahil) oluşturup
  **hepsini Admin'e** verir. `ShopRoleSeeder`'ın iş rolleri yalnız kendi alanını alır. Sistem
  rolleri silinemez / yeniden adlandırılamaz (`RoleService::SYSTEM_ROLES`).
- **Kilitlenme korumaları:** kimse kendi rolünü değiştiremez ya da kendini pasif yapamaz;
  Super Admin'in rolü panelden değişmez; Super Admin'i yalnız Super Admin pasif yapar ve
  son aktif Super Admin pasif yapılamaz; Super Admin'in e-postasını yalnız Super Admin değiştirir.
  Pasif yapılan kullanıcının oturumları kapanır, girişi `Fortify::authenticateUsing` içinde reddedilir.
  Taslak (salt okunur) kullanıcı yine şifresini değiştirebilir, bildirimlerini okuyabilir,
  oturumlarını kapatabilir (`EnsureWriteAccess::PERSONAL_ROUTES`).
- Spatie'nin `role` / `permission` ara katman takma adları kayıtlıdır ama kullanılmaz.
  Policy yoktur; rota düzeyinde kontrol yetmediğinde eklenir.

### Durum makineleri

İş sonucu olan yaşam döngüleri açıkça modellenir: **geçiş haritası enum'dadır**, geçişi **tek
bir servis metodu** uygular; satırı kilitler ve mevcut durumu yeniden kontrol eder (rotaya
bağlanan modele güvenilmez). `status` başka yerde yazılmaz.

- **Sipariş** — `OrderStatus::allowedTargets()` / `canTransitionTo()`; `OrderService::changeStatus()`
  `lockOrder` altında uygular. Kargolanmış / tamamlanmış sipariş iptal edilmez (mal iadeyle döner).
  Dış kanal (pazaryeri) siparişleri `OrderService::placeExternal()` ile açılır: kanalın KDV dahil
  fiyatı korunur, sipariş verilen hesaba yazılır, durumu kanalın ödeme sürücüsünün `start()`'ı belirler.
  Stok sipariş anında düşer; iptal ve başarısız dışındaki her durum stoğu tutar. İptal edilen
  teklif siparişi yeniden açılırsa teklifi yeniden bağlanır ya da reddedilir. Onay bekleyen
  vadeli sipariş onaylanırken kredi yeniden değerlendirilir (`CreditService::decide(locking: true)`).
- **Ödeme** — `markPaid()` / `markPaymentFailed()` / `updatePaymentStatus()` siparişi kilitler.
  Ödenmiş, iptal edilmiş ya da süresi dolmuş siparişe gelen sağlayıcı ödemesi siparişe uygulanmaz:
  ödeme cariye alacak yazılır, sipariş açılmaz, `UnappliedPaymentReceivedEvent` yöneticilere bildirir.
  Ödemesi başarısız olan teklif siparişinin teklifi yeniden ödenebilir hale gelir. Kart sürücüsü
  `ReconcilesPayments` uygularsa süre aşımı komutu ödemeyi başarısız saymadan önce sağlayıcıya sorar.
- **İade (kart)** — `payment_refunds`: pending → succeeded / failed. Satır sağlayıcı çağrısından
  **önce** yazılır; sonuç alınamazsa pending kalır ve tutarı ayırmaya devam eder (çift iade olmaz).
  `PaymentRefundService::STALE_AFTER_MINUTES` sonra yönetici siparişin Ödemeler kartından
  `confirm()` ya da `abandon()` ile sonuçlandırır.
- **Teklif** — `QuoteStatus::allowedTargets()`; her değişiklik `QuoteService::transition()`
  üzerinden. Gönderilmiş teklif yalnız yeniden gönderilerek değişir.
- **İade talebi** — `ReturnStatus::allowedTargets()`: pending → approved | rejected → received →
  refunded. Stoğa alınmış iade reddedilemez; iade tutarı iade edilen satırların değeriyle sınırlı;
  sipariş yalnız tamamı iade edilince "iade edildi" olur.
- **Çek / senet** — `ChequeStatus::next()`, satır kilidi altında. Portföyde çek varken çek
  özelliği kapatılamaz.
- **Cari** — `LedgerService` her yazmada **hesap satırını** kilitler. `reverse()` koşullu
  güncellemeyle işaretler; `account_transactions.reversal_of` tekildir. Alacaklar önce ödemeye
  bağlanan siparişlere (`payment_orders`), kalanı en eski vadeli borçlara (FIFO) dağıtılır.
- **Ödeme sayfası** — `CheckoutGuard`: her ödeme sayfasına tek kullanımlık `checkout_token`
  (çift gönderim tek sipariş verir) ve `expected_total` (fiyat değiştiyse sipariş bir kez yeni
  toplamla reddedilir).
- **Stok** — her hareket önce **ürün satırını** kilitler (sabit sıra, depolar arası kilitlenme yok).
  Uygunluk kilit içinde, süresi geçmiş lotlar hariç hesaplanır. Stok ya da lot varken takip türü
  (yok / lot / seri) değişmez. **Stok rezervasyonu:** `shop:release-stock-reservations` (saatlik)
  `stock_reservation_days`'i geçen siparişleri kapatır — ödeme bekleyen (kendi süresi olmayan
  yöntem) → başarısız, onay bekleyen → iptal; incelemede havale bildirimi varsa dokunmaz.
- **Durum düğmeleri** hedef durumu gönderir (`HasStatus::hasStatus()`): çift tıklama ya da iki
  yönetici kaydı geri çevirmez.

### Para

- Tutar hesabı [App\Support\Money](app/Support/Money.php) ile yapılır (`brick/money`):
  `of` / `zero` / `sum`, `round` / `add` / `sub` / `times` / `percent` / `withRate` / `line` /
  `allocate`. Ara adımlar kesin; yalnız sonuç **HalfUp** yuvarlanır — birim fiyat 4, tutar 2 hane.
  Karşılaştırma `isPositive()` / `isGreaterThan()` ile, tolerans değerleriyle değil.
- Para / oran / desi sütunları `App\Casts\Decimal` (`:2`, birim fiyat `:4`, kur `:6`) ile
  `Brick\Math\BigDecimal` okunur; `(float)` yapılmaz. Servisler `BigNumber|int|string` alır,
  `BigDecimal` döner. İstek girdisi `Money::round((string) $value)`.
- Tutarlar PHP'den **string** çıkar (`"1234.50"`): Inertia prop'ları, API, ERP çıktıları.
  Tek istisna Excel hücreleri (`Money::toFloat`).
- Ön yüzde para alanları `string` tiplidir; gösterim `formatMoney` / `formatNumber`
  (`@/lib/format`), hesap [`@/lib/decimal`](resources/js/lib/decimal.ts) (BigInt, HalfUp;
  `Money`'nin aynısı). `toNumber` yalnız grafik eksenleri için.
- Testlerde tutar `toBeMoney('510.00')` (ölçek dahil) ya da `toEqualDecimal('7.5')` ile karşılaştırılır.

### Çeviri

- Anahtar Türkçe metnin kendisidir (Laravel JSON çevirileri); yer tutucular her iki tarafta
  Laravel'in `:ad` biçimindedir. Cümle birleştirerek kurulmaz.
- Çekirdek metinleri `lang/tr.json`; her eklentinin kendi `lang/tr.json`'u vardır
  (`loadJsonTranslationsFrom`) ve `@/lib/i18n` bunları `vendor/bsinternet/*/lang`'dan okur.
- Kataloglar üretilir, elle düzenlenmez: `php artisan lang:sync`. Güncel değilse
  `TranslationCatalogTest` düşer. Yeni dil = her `tr.json`'un yanına aynı anahtarlarla
  `{locale}.json`; çevrilmeyen anahtar Türkçeye düşer.
- Laravel'in kendi `lang/{tr,en}/*.php` dosyaları (auth, validation, pagination, passwords) olduğu gibi kalır.

### Marka ve atıf

[App\Support\Branding](app/Support/Branding.php) uygulama kimliğinin tek kaynağıdır: `name()`,
`slogan()` (ayarlar → yoksa `config('app.name')`), `logo()` / `logoDark()` / `favicon()`
(yüklenen görsel → yoksa `public/bayiyazilimi*.png`). Genel Ayarlar ekranından yönetilir.
Favicon yalnız kenar çubuğunda kullanılır, diğer her yerde normal logo.

**Atıf silinmez:** [PoweredBy](resources/js/components/powered-by.tsx) giriş ekranında ve kurulum
sihirbazında, [AppFooter](resources/js/components/panel/app-footer.tsx) panelde
"Bayi Yazılımı v{sürüm}" ve "Bayi Yazılımı · © Bülent Sakarya" gösterir. EK-KOSULLAR.md
madde 1 gereğidir; müşterinin markası bunun yerine geçmez.

### Ayarlar, medya, etkinlik kaydı

- **Ayarlar:** `Setting` modeli (`Setting::allCached()`), `SettingsService` ve Genel Ayarlar ekranı.
  Satış / stok / cari ayarları `ShopSettings` üzerinden; eklentiler varsayılanlarını
  `shop.settings.defaults` filtresiyle ekler.
- **Medya:** çok biçimli `media` tablosu, `MediaService::attach/detach/reorder`, modelde `HasMedia`.
  Üyeye ait dosyalar `public|private/{hesap kodu}`, genel dosyalar `public|private/media` altında.
  Özel dosyalar `Media::temporaryUrl()` ile imzalı adresten verilir.
- **Etkinlik kaydı:** `spatie/laravel-activitylog`; listener'lar `LogsActivity` yardımcısını kullanır,
  konu etiketleri [ActivitySubjectLabels](app/Support/ActivitySubjectLabels.php). Girişler
  `yadahan/laravel-authentication-log` ile tutulur.
- **Ortak model özellikleri** (`app/Concerns/`): `HasStatus`, `HasSortOrder`, `BelongsToAccount`,
  `HasMedia`, `LogsActivity`.
- **Tanımlamalar** (`panel/tools/definitions`): ülke / il / ilçe, para birimi, dil, vergi.
  Kayıtlar pasif oluşur, durum tablodan ayrı uç noktayla değişir. Varsayılan seçimler
  `DefinitionGuard` ile korunur (varsayılan pasif yapılamaz, alt kaydı olan silinemez); ihlal
  `DefinitionGuardException` ile uyarı toast'ı olarak döner.

### REST API (`/api/v1`)

ERP / muhasebe gibi gelen entegrasyonlar içindir ([routes/api.php](routes/api.php), rota adları `api.v1.*`).

- **Kimlik:** yalnız Sanctum kişisel erişim anahtarları (oturumla API yok). Anahtar bir **yöneticiye**
  aittir ve API o kullanıcı adına çalışır. `api_user` sahibi aktif / taslak yönetici olmayan anahtarı
  reddeder (401), taslak sahibin anahtarını salt okunur yapar (403). Biçim `{uuid}|bayi_…`, bir kez gösterilir.
- **Yetenekler:** [ApiAbilities](app/Support/Api/ApiAbilities.php) — `products:read`, `products:write`,
  `stock:write`, `orders:read`, `orders:write`, `accounts:read`, `ledger:write`. Her rota
  `abilities:{yetenek}` ister. Kullanıcı yalnız karşılık gelen panel yetkisine sahip olduğu yeteneği
  anahtara verebilir (`ApiAbilities::PERMISSIONS`). Eklentiler yetenek eklemek için `api.abilities` filtresini kullanır.
- **Ekran:** Ayarlar → API Anahtarları (`panel.settings.api-tokens.*`, `ApiTokenService`).
- **Sınır:** `api` hız sınırlayıcısı, anahtar başına `BAYIYAZILIMI_API_RATE_LIMIT` (varsayılan 120/dk).
- **Yanıtlar:** her zaman JSON, hata mesajları Türkçe ([ApiExceptionRenderer](app/Exceptions/ApiExceptionRenderer.php);
  `ShopException` → 422). Tutarlar string, zamanlar ISO-8601, listeler Laravel sayfalayıcısı
  (`per_page` ≤ 100), `updated_at, id` sıralı ve artımlı eşitleme için `updated_since` filtreli.
- **Uç noktalar çekirdek servislerini kullanır**, durum / stok / cari sütununa doğrudan yazmaz:
  ürün güncelleme → `ProductService::update`; stok → `StockSyncService` → `StockService::setQuantity`
  (mutlak miktar, fark "API" notlu düzeltme hareketi; lot / seri takipli ürün reddedilir); sipariş
  durumu → `OrderService::changeStatus`; fatura → `OrderService::recordInvoice`; cari hareket →
  `ExternalLedgerService` → `LedgerService::record` (`origin = api`, `external_ref` = ERP referansı,
  aynı referans aynı kaydı döndürür).

### Eklenti sistemi

Eklenti, çekirdeğe dokunmadan menü, rota, yetki, ekran ve e-posta ekleyen bir **composer paketidir**.
Mekanizma **Hook + Registry**'dir: kanca "ne zaman / nerede", registry "ne eklenecek, tipli".

**Kancalar** ([HookManager](app/Support/Hooks/HookManager.php), `hooks()`): `action` / `do` yan etki,
`filter` / `apply` değer dönüşümü içindir. Filtre geri çağrısının ilk parametresinin adı **`$value`**
olmalıdır (container adla çağırır). Kancalar provider'ın `register()` metodunda bağlanır; başka bir
eklentinin katkısına dayanan kayıt `booted()` içinde yapılır.

Başlıca kancalar:

| Kanca | Ne için |
|---|---|
| `{panel,app,web,api}.routes.register` | Rotalar; ilgili ara katman grubunun ve ad ön ekinin **içinde** çalışır |
| `{area}.menu.register` | [MenuRegistry](app/Support/Registry/MenuRegistry.php) — menü (yetkiye göre süzülür) |
| `{area}.permissions.register` | [PermissionRegistry](app/Support/Registry/PermissionRegistry.php) — `source` = eklenti anahtarı |
| `shop.payment.methods.register` | Ödeme yöntemi (`PaymentRegistry`) |
| `shop.settings.defaults` | Ayar varsayılanları |
| `api.abilities` | API yetenekleri |
| `security.csp` | CSP'ye dış kaynak ekleme |
| `site.public_access`, `shop.storefront_enabled`, `shop.customer_types` | Siteyi ziyaretçiye açma, vitrin alanları, müşteri (B2C) hesapları (vitrin kullanır) |
| `auth.home`, `user.area_access` | Kullanıcıya kendi alanını verme (ör. plasiyer `/plasiyer`) |
| `seo.robots` | `/robots.txt` (çekirdek varsayılanı `Disallow: /`) |
| `database.seed` | Kurulumda temel veri |

Ürün formu, kampanya koşulları, sipariş detayı gibi ekranların da kendi filtreleri vardır
(`shop.products.*`, `shop.campaigns.*` …); tam liste için kodda `hooks()->do` / `hooks()->apply` aranır.

**`module.json`** (paket kökünde) bildirimdir; mantık tipli PHP'de kalır:

```jsonc
{
  "key": "ptt",
  "name": "PTT Kargo",
  "provider": "Bsinternet\\Ptt\\PttServiceProvider",
  "areas": ["panel", "app"],
  "requires": { "php": "^8.4", "bayiyazilimi": "^1.0" },   // diğer anahtarlar = gereken eklentiler
  "publish": [{ "from": "resources/views/mail", "to": "resources/views/vendor/ptt" }],
  "migrate": true,
  "permissions": "remove",   // kaldırırken: "remove" | "keep"
  "purge_data": false,
  "enabled": true
}
```

**Yaşam döngüsü:** `composer require` → arka uç hemen çalışır (otomatik keşif) →
`php artisan bayi:install {key}` (gereksinim ve çakışma kontrolü, `publish` kopyası, migrate, yetkiler —
role atanmadan; `installed_modules` kaydı) → `npm run build`. Kaldırma: `bayi:uninstall` **`composer
remove`'dan önce** (manifest gerekir); yalnız değiştirilmemiş yayımlanan dosyaları siler, tablolara
`--purge-data` olmadan dokunmaz, başka eklentinin ihtiyaç duyduğu eklentiyi kaldırmaz. `from` / `to`
yolları paket / proje kökü dışına çıkamaz. `module.json`'un anlatamadığı iş için eklenti
`installed()` / `uninstalling()` metotlu bir `Installer` sınıfı taşıyabilir.

**Ön yüz:** eklentinin kendi tasarım sistemi yoktur; çekirdeğin bileşenlerini `@/` ile içe aktarır.
Sayfalar kopyalanmaz, `vendor/bsinternet/*/resources/js/pages` altından çözülür (`config/bayiyazilimi.php`
`module_vendors`, `App\Support\InertiaPage`); projede aynı yoldaki sayfa eklentininkini ezer.
`resources/js/extensions.ts` çekirdeğin adlı slot'larına bileşen yerleştirir (`registerSlot` /
`<Slot>` / `useHasSlot`); kayıt yalnız eklenti etkinken görünür. Wayfinder devre dışı eklentilerin
de yardımcılarını üretir (sayfaları derlemede kalır).

**Eklentiler ekranı** (Ayarlar → Eklentiler, `panel.settings.extensions.*`; arayüzde "eklenti",
kodda "module"): kurulu eklentileri listeler, açar / kapatır. Kapalı eklenti
`storage/app/modules.json`'a yazılır ve [ModulePackageManifest](app/Support/Modules/ModulePackageManifest.php)
paketi otomatik keşiften tümden çıkarır (veri korunur). Tüm eklentiler listesi
`ModuleCatalog::URL`'deki uzak JSON'dan gelir (ertelenmiş prop; erişilemezse son önbellek);
testler `Http::fake()` kullanır.

**Temalar:** `module.json`'da `"type": "theme"` olan eklenti yalnız mağaza sayfalarını (`shop/*`)
değiştirebilir. Etkin tema `settings.storefront_theme`; tema `themes/{key}/shop/…` sayfası taşıyorsa
`Inertia::render('shop/…')` ona yönlenir, taşımıyorsa varsayılan kullanılır. Renkler `store-*`
CSS değişkenleriyle değişir. Ekran: Ayarlar → Eklentiler → Temalar.

**Çekirdekte kalan vitrin izleri:** stok "tükenince göster", kategori SEO, ETBİS, kampanya rozeti gibi
alanlar yalnız `Storefront::enabled()` iken görünür ve kapalıyken değerlerine dokunulmaz. Müşteri
(B2C) hesapları yalnız `CustomerType::customersEnabled()` iken vardır. İçerik sayfaları vitrinindir;
çekirdek yalnız **sözleşmeleri** tutar (`Contract::forForm()`; ödeme ve kayıt formları için).

### Güvenlik

- **Başlıklar** (`SetSecurityHeaders`): `nosniff`, `SAMEORIGIN`, referrer, `Permissions-Policy`
  (konum yalnız kendi sayfalarımıza), canlıda HSTS.
- **CSP** ([ContentSecurityPolicy](app/Support/Security/ContentSecurityPolicy.php)): betikler istek
  başına nonce + `'strict-dynamic'` ile çalışır. Kip `BAYIYAZILIMI_CSP`: `report` (varsayılan,
  `Content-Security-Policy-Report-Only`; ihlaller `POST /csp-report` ile günlüğe "CSP ihlali"),
  `enforce`, `off`. Yerelde Vite dev sunucusu çalışırken başlık gönderilmez. Dış adrese çerçeve /
  form / bağlantı açan eklenti adresini `security.csp` filtresiyle ekler:
  `fn (array $value) => array_merge_recursive($value, ['frame-src' => ['https://…']])`.
- **Proxy:** `BAYIYAZILIMI_TRUSTED_PROXIES` (IP / CIDR listesi ya da `*`).
- **Şifre sıfırlama** kayıtlı olmayan e-postada da aynı yanıtı verir (hesap var mı sızmaz);
  giriş, kayıt, şifre sıfırlama uçları hız sınırlıdır.
- **Dışarıdan dosya indirme** (ör. ürün görseli aktarımı) yalnız herkese açık adreslere,
  boyut sınırıyla yapılır.
- **Ödeme sağlayıcısı bildirimleri** (`odeme/bildirim/*`, `odeme/donus/*`) CSRF dışıdır; imzayı
  ve tutarı sürücü doğrular.

### Kurulum, güncelleme, örnek veriler

- **Seeder'lar:** `CoreDataSeeder` her kurulumun aldığı temel veridir (dil, para birimi, Türkiye il /
  ilçe, vergi, ayarlar, roller, panel yetkileri, eklentilerin `database.seed` kancası); **kullanıcı
  oluşturmaz**. `SampleDataSeeder` isteğe bağlı örnek veridir (örnek bayi, sipariş; şifre verilmezse
  rastgele). `DatabaseSeeder` **yalnız geliştirme / test** içindir (`admin@admin.com`,
  `panel@admin.com`, şifre `password`); canlı ortamda çalışmayı reddeder.
- **Kurulum:** web sihirbazı `/kurulum` ([InstallController](app/Http/Controllers/Install/InstallController.php))
  ve `php artisan bayi:setup`, ikisi de [Installer](app/Services/Install/Installer.php) üzerinden:
  `.env` yazar, migrate + seed, Super Admin oluşturur, `APP_ENV=production` ve
  `BAYIYAZILIMI_INSTALLED=true` yapar, bekleyen eklentileri bağımlılık sırasıyla `bayi:install` eder.
- **Kurulu mu:** [InstallState](app/Support/Install/InstallState.php) — `.env` bayrağı; yoksa Super Admin'i
  olan veritabanı kurulu sayılır ve bayrak yazılır. Kurulmamışken `EnsureInstalled` her isteği
  `/kurulum`'a yollar, oturum / önbellek / kuyruk dosya ve sync'e geçer, `Setting::allCached()` boş döner.
  Kurulu sistemde sihirbaz rotaları hiç kaydedilmez (konsol hariç). Sihirbaz veritabanına dokunmayan
  `installer` ara katman grubunda çalışır, kurulum önbellek kilidi altındadır.
- **Güncelleme:** `php artisan bayi:update` (`--no-backup`, `--no-maintenance`, `--build`) ya da panel
  uyarısı → [UpdateService](app/Services/System/UpdateService.php): mysqldump yedeği, migrate, bekleyen
  eklenti kurulumları, önbellek temizliği, `queue:restart`, `installed_version` kaydı.
  Sürüm: `App\Support\BayiYazilimi::VERSION`.
- **Zamanlanmış görevler** ([routes/console.php](routes/console.php)): `shop:sync-rates` (hafta içi
  09:05 ve 15:45), `shop:expire-pending-payments` (5 dk), `shop:release-stock-reservations` (saatlik),
  `shop:refresh-expired-stock` (00:10).

## Ön yüz kuralları

- TS strict, `@/* → resources/js/*`. Uç noktalar Inertia `useForm` / `<Form>` + Wayfinder ile çağrılır.
- **Tipler:** paylaşılan, tekrarlanan ya da sunucuyu yansıtan tipler `resources/js/types/`'a
  (`@/types`); sayfa `Props`'ları ve tek kullanımlık görünüm modelleri sayfada kalır.
  `{ value, label }` için `Option<TValue>`. Alan tipleri kendi dosyasında (`definitions.ts`,
  `ledger.ts`, `auth.ts` …).
- **Ortak bileşenler:** [DataPagination](resources/js/components/data-pagination.tsx) (sunucuda
  `PaginatedResource::make(...)`), [ConfirmDelete](resources/js/components/confirm-delete.tsx),
  [ImageUpload](resources/js/components/image-upload.tsx), [BrandHeader](resources/js/components/brand-header.tsx).
- **Kancalar:** `useAppAuth` / `usePanelAuth`, `useBranding`, `usePermissions`, `useActiveNavHref`,
  `useFlashToast`, `useCurrentUrl`.
- **Toast:** controller `->with('toast', ['type' => 'success|info|warning|error', 'message' => __('…')])`;
  [use-flash-toast](resources/js/hooks/use-flash-toast.ts) sonner ile gösterir. Alan kuralı ihlalleri
  genel 422 yerine render edilebilir istisnayla uyarı toast'ı döner.
- **Menü** PHP'den `navigation` prop'uyla gelir; ikon string anahtardır,
  [navigation-icons.ts](resources/js/lib/navigation-icons.ts) Lucide bileşenine çevirir. Kenar çubuğu
  elle kodlanmaz. Simge kipinde alt menüler açılır menü olarak görünür.
- **Düğmeler:** bir grupta düğmelerden biri simgeliyse hepsi simgelidir (kaydet → Save, filtre → Search,
  vazgeç → X, sil → Trash2 …).
- Karanlık kip `next-themes` ile; Tailwind v4 (yapılandırma dosyası yok, giriş `resources/css/app.css`).

## Testler

- Pest 4, MySQL üzerinde ayrı `bayiyazilimi_test` veritabanı. Önbellek / oturum `array`, kuyruk `sync`.
- Veritabanına dokunan özellik testleri `RefreshDatabase` kullanır; tam geliştirme verisi gereken
  testler `$seed = true` ile `DatabaseSeeder`'a dayanır.
- Her davranış değişikliği testle gelir; özellikle durum geçişleri, yetki sınırları ve para hesapları.
- Dış servisler taklit edilir (`Http::fake()`, eklenti kataloğu dahil).
- `Model::preventLazyLoading()` canlı dışında açıktır: N+1 testte hata verir.
- Eklenti testleri eklentinin deposundadır; `php artisan test` onları kendiliğinden koşmaz.

## Hazır bekleyen parçalar

Çekirdek başlangıç kiti olarak da kullanılabilsin diye bazı tamamlanmış parçalar bilerek
**kullanılmadan** durur. Bunlar ölü kod değildir; temizlikte silinmez:

- shadcn/ui setinin tamamı (`components/ui/*`).
- Yetki kancaları: [use-permissions.ts](resources/js/hooks/use-permissions.ts) — `useCan`,
  `useHasRole`, `useIsSuperAdmin`.
- Enum yardımcıları: `*_LABEL` / `*_OPTIONS` / `*_VALUES` ([types/status.ts](resources/js/types/status.ts),
  [user-status.ts](resources/js/types/user-status.ts), [user-type.ts](resources/js/types/user-type.ts)).
- Alternatif düzenler: `auth/auth-simple-layout`, `auth/auth-card-layout`, `auth/auth-split-layout`,
  `app/app-header-layout`, `panel/app-header-layout` (etkin olanlar `auth-brand-layout` ve
  `app-sidebar-layout`; `layouts/{auth,app,panel}-layout.tsx` içinden değiştirilir).
- [media-gallery](resources/js/components/media-gallery.tsx).
- Panel arayüz parçaları: [RadioFilter](resources/js/components/admin/radio-filter.tsx),
  [admin/page.tsx](resources/js/components/admin/page.tsx) içindeki `ListLayout` / `FilterPanel` /
  `FilterGroup`, [shop/form-fields.tsx](resources/js/components/shop/form-fields.tsx) içindeki
  `StateBadge` / `FilterSelect` / `selectClassName`, `components/{app,panel}/nav-user.tsx`.

Kenar çubuğunda sürükle-bırak sıralama bilerek yapılmadı; `order` alanlı registry hazır veri kaynağıdır.
