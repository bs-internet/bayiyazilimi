<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Bir modülün kurulum kaydı (`bayi:install` yazar, `bayi:uninstall` siler).
 * `version`, kurulumun son çalıştırıldığı sürümdür; module.json'daki sürümden
 * farklıysa yeni sürümün kurulumu (migration vb.) henüz çalıştırılmamıştır.
 *
 * @property string $key
 * @property string $name
 * @property string $version
 * @property CarbonImmutable $installed_at
 */
#[Fillable([
    'key',
    'name',
    'version',
    'installed_at',
])]
class InstalledModule extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'installed_at' => 'immutable_datetime',
        ];
    }
}
