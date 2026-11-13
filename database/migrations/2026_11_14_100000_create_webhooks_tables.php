<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Webhook'lar: olay olunca (sipariş açıldı, durum değişti, ödeme alındı …)
 * kayıtlı adreslere imzalı POST gönderilir. Her gönderim denemeleriyle
 * birlikte webhook_deliveries'te tutulur.
 *
 * Kurulu sistemde API anahtarı oluşturabilen roller Webhook ekranlarının ve
 * ayrı sayfaya taşınan API anahtarı oluşturma ekranının yetkilerini de alır;
 * yeni kurulumda yetkileri PanelPermissionSeeder kurar.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'panel.settings.api-tokens.create' => 'API anahtarı oluşturma ekranı',
        'panel.settings.webhooks.index' => 'Webhook\'ları görüntüleme',
        'panel.settings.webhooks.store' => 'Webhook ekleme',
        'panel.settings.webhooks.update' => 'Webhook açma / kapatma',
        'panel.settings.webhooks.destroy' => 'Webhook silme',
        'panel.settings.webhooks.test' => 'Webhook deneme gönderimi',
    ];

    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url', 2000);
            // HMAC-SHA256 imza anahtarı (şifreli); yalnız oluşturulurken bir kez gösterilir.
            $table->text('secret');
            // Abone olunan olaylar: ["order.created", …]; ["*"] = tümü.
            $table->json('events');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Art arda başarısız gönderim sayısı (başarılı gönderimde sıfırlanır).
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 64)->index();
            $table->json('payload');
            // pending → succeeded | failed (son deneme de başarısızsa)
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at']);
        });

        $store = Permission::query()->where('name', 'panel.settings.api-tokens.store')->where('guard_name', 'web')->first();

        if ($store === null) {
            return;
        }

        $created = collect(self::PERMISSIONS)->map(fn (string $label, string $name) => Permission::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['group' => $store->group, 'label' => __($label)],
        ));

        Role::query()->whereHas('permissions', fn ($q) => $q->whereKey($store->getKey()))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($created->values()->all()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
