<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vade hatırlatmaları: hangi borca (cari hareket) hangi aşamada (yaklaşan /
 * geçmiş) ne zaman e-posta gittiği. Aynı borç için aynı aşamada tekrar
 * gönderim buna göre engellenir (geçmiş borçta ayarlanan aralıkla yinelenir).
 *
 * Kurulu sistemde cari ekstreyi Excel'e aktarabilen roller "e-postayla gönder"
 * yetkisini de alır; yeni kurulumda yetkiyi PanelPermissionSeeder kurar.
 */
return new class extends Migration
{
    private const PERMISSION = 'panel.shop.ledger.send';

    public function up(): void
    {
        // MySQL'de tablo oluşturma geri alınmaz: yetki adımı yarıda kalan bir önceki
        // denemenin tablosu varsa yeniden oluşturulmaz.
        if (! Schema::hasTable('due_reminders')) {
            Schema::create('due_reminders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('account_transaction_id')->constrained()->cascadeOnDelete();
                $table->string('stage', 16);
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->unique(['account_transaction_id', 'stage']);
            });
        }

        $export = Permission::query()->where('name', 'panel.shop.ledger.export')->where('guard_name', 'web')->first();

        if ($export === null) {
            return;
        }

        $send = Permission::query()->firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['group' => $export->group, 'label' => __('Cari ekstreyi e-postayla gönder')],
        );

        Role::query()->whereHas('permissions', fn ($q) => $q->whereKey($export->getKey()))
            ->with('permissions')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($send));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('due_reminders');
        Permission::query()->where('name', self::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
