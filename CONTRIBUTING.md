# Katkı rehberi

Bayi Yazılımı'na katkı vermek istediğiniz için teşekkürler. Bu dosya katkının nasıl
yapılacağını anlatır; kodun nasıl kurulduğu ve uyulması gereken kurallar
[AGENTS.md](AGENTS.md)'dedir. Kod yazmadan önce onu okuyun.

## Nereden başlamalı

- **Hata bildirimi:** issue açın; ne yaptığınızı, ne beklediğinizi, ne olduğunu ve sürümü
  (panel alt bilgisinde yazar) ekleyin. Ekran görüntüsü ve günlük satırı çok yardımcı olur.
- **Güvenlik açığı:** issue açmayın. GitHub'da **Security → Report a vulnerability** ile gizli bildirin.
- **Yeni özellik:** kod yazmadan önce issue açıp konuşalım. Çekirdek bilerek sade tutulur;
  belli bir sektöre ya da tek bir firmaya özgü özellikler çoğu zaman eklenti olarak daha doğru yerdedir.
- **Küçük düzeltmeler** (yazım, belge, açık hata) için doğrudan PR açabilirsiniz.

## Ortamı hazırlama

Gerekenler: PHP 8.4+, Composer, Node.js 22 LTS, MySQL 8+ ya da MariaDB 10.6+.

```bash
composer setup              # bağımlılıklar, .env, anahtar, tablolar, ön yüz derlemesi
php artisan migrate --seed  # geliştirme verileri — giriş: admin@admin.com / password
composer dev                # sunucu + kuyruk + Vite
```

Testler ayrı ve boş bir veritabanında çalışır; bir kez oluşturun:

```sql
CREATE DATABASE bayiyazilimi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Bağlantı bilgileri `.env`'den okunur. Farklıysa `phpunit.xml.dist`'i `phpunit.xml` olarak
kopyalayıp düzenleyin (git'e girmez).

## Değişiklik yaparken

1. Ana daldan yeni bir dal açın.
2. Değişikliği [AGENTS.md](AGENTS.md)'deki kurallara göre yapın.
3. Rotalara dokunduysanız: `php artisan wayfinder:generate --with-form --no-interaction`
4. Arayüz metni eklediyseniz ya da değiştirdiyseniz: `php artisan lang:sync`
5. Davranışı değiştiren her şey için Pest testi ekleyin ya da güncelleyin.
6. Tüm kontrolleri çalıştırın, yeşil olmadan PR açmayın:
   ```bash
   composer ci:check   # ESLint + Prettier + tsc + Pint + Pest
   ```

## Pull request

- Bir PR tek bir konuyu çözer; ilgisiz düzenlemeleri ayrı PR'a bırakın.
- Açıklamada **ne** değiştiğini ve **neden** gerektiğini yazın, ilgili issue'yu bağlayın.
  Arayüz değiştiyse ekran görüntüsü ekleyin.
- Veritabanı değişikliği yeni bir migration'la yapılır; yayımlanmış migration düzenlenmez.
- Commit başlıkları kısa ve Türkçe, ne yapıldığını söyler
  (ör. "Tahsilatta sipariş seçimi", "Kargolanmış sipariş iptal edilemez").

## Bitti sayılması için

- [ ] `composer ci:check` yeşil.
- [ ] Rotalar değiştiyse Wayfinder yeniden üretildi; metinler değiştiyse `lang:sync` çalıştı.
- [ ] Yeni davranış testle kapsandı.
- [ ] Kullanıcının gördüğü her metin `__()` / `t()` içinde.
- [ ] `components/ui/*` elle düzenlenmedi; adres, marka ya da eklenti adı koda yazılmadı.
- [ ] "Bayi Yazılımı altyapısıyla çalışır" atfına dokunulmadı.

## Lisans

Gönderdiğiniz katkı, projenin lisansıyla —
[GNU AGPLv3](LICENSE) ve [ek koşullar](EK-KOSULLAR.md) — yayımlanır. PR açarak katkının
size ait olduğunu ve bu lisansla yayımlanmasını kabul ettiğinizi beyan etmiş olursunuz.
