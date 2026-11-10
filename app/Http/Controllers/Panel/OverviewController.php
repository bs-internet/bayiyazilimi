<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Tax;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bölüm giriş sayfaları (Tanımlamalar, Genel Ayarlar): menüdeki alt bölümler kart
 * olarak listelenir. Kartlar `navigation`'dan (yetkiye göre süzülmüş) kurulur;
 * her kartın durum özeti menü anahtarıyla eşlenen `summaries`'ten gelir.
 *
 * Modüller özet eklemek için `panel.definitions.summaries` /
 * `panel.settings.general.summaries` filtrelerine
 * `[menü anahtarı => ['text' => string, 'warning' => bool]]` yazar.
 */
class OverviewController extends Controller
{
    public function definitions(): Response
    {
        $defaultLanguage = Language::query()->where('code', Setting::get('default_language_code'))->value('native_name');

        $summaries = [
            'panel.tools.definitions.languages.index' => [
                'text' => self::counts(Language::query()->count(), Language::query()->where('status', 'active')->count(), __('dil'))
                    .($defaultLanguage ? __(' · varsayılan :default_language', ['default_language' => $defaultLanguage]) : ''),
            ],
            'panel.tools.definitions.countries.index' => [
                'text' => __(':countries ülke · :cities il', ['countries' => Country::query()->count(), 'cities' => City::query()->count()]),
            ],
            'panel.tools.definitions.currencies.index' => [
                'text' => self::counts(Currency::query()->count(), Currency::query()->where('status', 'active')->count(), __('para birimi')),
            ],
            'panel.tools.definitions.taxes.index' => [
                'text' => self::counts(Tax::query()->count(), Tax::query()->where('status', 'active')->count(), __('oran')),
            ],
        ];

        return Inertia::render('panel/tools/definitions/overview', [
            'summaries' => self::normalize(hooks()->apply('panel.definitions.summaries', $summaries)),
        ]);
    }

    public function general(): Response
    {
        $summaries = [
            'panel.settings.general.edit' => [
                'text' => (string) (Setting::get('app_name') ?: config('app.name')),
            ],
        ];

        return Inertia::render('panel/settings/general/overview', [
            'summaries' => self::normalize(hooks()->apply('panel.settings.general.summaries', $summaries)),
        ]);
    }

    /** "12 birim · 10 aktif" biçimi. */
    public static function counts(int $total, int $active, string $noun): string
    {
        return $total === $active ? __(':total :noun', ['total' => $total, 'noun' => $noun]) : __(':total :noun · :active aktif', ['total' => $total, 'noun' => $noun, 'active' => $active]);
    }

    /**
     * @param  array<string, mixed>  $summaries
     * @return array<string, array{text: string, warning: bool}>
     */
    private static function normalize(array $summaries): array
    {
        return collect($summaries)->map(fn ($s) => [
            'text' => (string) ($s['text'] ?? ''),
            'warning' => (bool) ($s['warning'] ?? false),
        ])->all();
    }
}
