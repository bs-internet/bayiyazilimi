<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cari tahsilatta seçilen siparişler: tahsilat önce bunları (sırayla) kapatır,
 * artanı en eski vadeli borca gider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['payment_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
