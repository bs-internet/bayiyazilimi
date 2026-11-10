<?php

declare(strict_types=1);

use App\Support\Modules\ModuleDiscovery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kurulu modüller kaydı: `bayi:install` yazar, `bayi:uninstall` siler.
 * Modüller ekranı kurulum tarihini ve kurulumun tamamlanıp tamamlanmadığını
 * (composer ile eklenip `bayi:install` unutulmuş modül) buradan gösterir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installed_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('version');
            $table->timestamp('installed_at');
            $table->timestamps();
        });

        // Bu tablodan önce kurulmuş modüller kurulu sayılır.
        $now = now();

        foreach (app(ModuleDiscovery::class)->all() as $manifest) {
            if (class_exists($manifest->provider)) {
                DB::table('installed_modules')->insertOrIgnore([
                    'id' => (string) Str::uuid7(),
                    'key' => $manifest->key,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'installed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
    }
};
