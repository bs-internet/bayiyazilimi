<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - contracts.show_on_site: sözleşme sitede (vitrin) herkese açık sayfa olarak
 *   yayınlanır; yasal metinler tek yerden (Tanımlamalar → Sözleşmeler) yönetilir.
 * - users.marketing_consent_at: üyelik formunda verilen isteğe bağlı ticari
 *   elektronik ileti onayının zamanı (6563 s. Kanun; kanıt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'show_on_site')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->boolean('show_on_site')->default(false)->after('show_on_registration');
            });
        }

        if (! Schema::hasColumn('users', 'marketing_consent_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('marketing_consent_at')->nullable()->after('email_verified_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn('show_on_site'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('marketing_consent_at'));
    }
};
