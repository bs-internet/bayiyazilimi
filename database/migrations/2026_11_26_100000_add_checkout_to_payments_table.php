<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kart ekranıyla ödenen sipariş ödeme alınana kadar açılmaz: ödeme formunun bilgileri
 * ve sepetin anlık görüntüsü bekleyen ödemede durur (CardCheckout), sipariş ödeme
 * başarılı olunca bunlardan oluşturulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->json('checkout')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('checkout');
        });
    }
};
