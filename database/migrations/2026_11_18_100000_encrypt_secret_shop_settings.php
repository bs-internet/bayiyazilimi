<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Shop\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gizli mağaza ayarları (API anahtarı, şifre, imza tuzu …) artık şifreli saklanır
 * (bkz. ShopSettings::encryptValue). Daha önce düz yazılmış değerler burada
 * şifrelenir; eklentinin kendi şifrelediği değer de çekirdeğin katmanıyla sarılır
 * (get() yalnız çekirdeğin katmanını açar, eklenti kendi katmanını açmaya devam eder).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('group', ShopSettings::GROUP)->orderBy('key')->get(['id', 'key', 'value'])
            ->filter(fn (object $row): bool => ShopSettings::isSecret((string) $row->key) && (string) $row->value !== '')
            ->each(function (object $row): void {
                $encrypted = ShopSettings::encryptValue((string) $row->value);

                if ($encrypted !== $row->value) {
                    DB::table('settings')->where('id', $row->id)->update(['value' => $encrypted]);
                }
            });

        Setting::flushCache();
    }

    public function down(): void
    {
        // Şifreli değerler geri düz metne çevrilmez; ShopSettings::get() iki biçimi de okur.
    }
};
