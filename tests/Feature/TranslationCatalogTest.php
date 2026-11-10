<?php

declare(strict_types=1);

use App\Support\Modules\ModuleState;
use App\Support\Translations\TranslationCatalog;
use Composer\InstalledVersions;

it('extracts translatable strings from php, blade and tsx', function () {
    $php = <<<'PHP'
        __('Kaydet'); trans("Sil"); __('Merhaba :name', ['name' => $n]);
        @lang('Çıkış'); trans_choice('{1} ürün|[2,*] ürünler', 2);
        __("Merhaba $name"); $this->__('yok'); __('Ali\'nin sepeti');
        PHP;

    $ts = <<<'TS'
        t('Kaydet'); t("Ödeme"); t(`Sepet`, { a: 1 }); t(`Toplam ${x}`);
        format.t('yok'); const at = 1; t(':count ürün', { count });
        TS;

    expect(TranslationCatalog::extract($php, 'php'))
        ->toBe(['Kaydet', 'Sil', 'Merhaba :name', 'Çıkış', '{1} ürün|[2,*] ürünler', "Ali'nin sepeti"])
        ->and(TranslationCatalog::extract($ts, 'ts'))
        ->toBe(['Kaydet', 'Ödeme', 'Sepet', ':count ürün']);
});

it('loads each installed module catalog next to the core catalog', function () {
    $paths = array_map(fn (string $p) => str_replace('\\', '/', (string) realpath($p)), app('translator')->getLoader()->jsonPaths());

    // Kurulu modüller (vendor/bsinternet/*); modülsüz çekirdekte liste boş.
    $installed = collect(glob(base_path('vendor/bsinternet/*/composer.json')) ?: [])
        ->filter(fn (string $file) => is_dir(dirname($file).'/lang'))
        ->filter(fn (string $file) => InstalledVersions::isInstalled((string) json_decode((string) file_get_contents($file), true)['name']))
        // Panelden kapatılan modülün kataloğu (provider'ıyla birlikte) yüklenmez.
        ->filter(fn (string $file) => app(ModuleState::class)->isEnabled(basename(dirname($file))))
        ->map(fn (string $file) => str_replace('\\', '/', (string) realpath(dirname($file).'/lang')));

    if ($installed->isEmpty()) {
        $this->markTestSkipped('Kurulu yerel modül yok.');
    }

    $installed->each(fn (string $lang) => expect($paths)->toContain($lang));
});

it('keeps the translation catalogs in sync with the source', function () {
    // Yeni bir metin eklendiyse: php artisan lang:sync
    $this->artisan('lang:sync', ['--check' => true])->assertSuccessful();
});

it('leaves file-based keys and code out of the json catalog', function () {
    expect(TranslationCatalog::extract("__('auth.must_login'); __('vitrin::shop.title'); __('Kaydet')", 'php'))->toBe(['Kaydet'])
        ->and(__('auth.must_login'))->not->toBe('auth.must_login');
});
