<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * İsteğe bağlı ticari elektronik ileti onayı (6563 s. Kanun): misafir teklif
 * talebinde ve ödeme formunda onay verildiyse zamanı tutulur; IP zaten
 * `ip_address`'te.
 *
 * Ödeme formundaki satış sözleşmesinin yasal adı "Mesafeli Satış Sözleşmesi"dir;
 * başlığı değiştirilmemiş varsayılan kayıt yeniden adlandırılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'orders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('marketing_consent_at')->nullable()->after('ip_address');
            });
        }

        DB::table('contracts')
            ->where('slug', 'satis-sozlesmesi')
            ->where('title', 'Satış Sözleşmesi')
            ->update(['title' => 'Mesafeli Satış Sözleşmesi']);
    }

    public function down(): void
    {
        foreach (['quotes', 'orders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('marketing_consent_at');
            });
        }
    }
};
