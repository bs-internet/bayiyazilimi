<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->text('description')->nullable();
            // Cari çalışma şekli: peşin / vadeli, limit, vade, limit aşımı ve vadesi geçmiş borçta davranış.
            $table->string('payment_terms', 16)->default('prepaid');
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->string('over_limit_action', 16)->default('approval');
            $table->string('overdue_action', 16)->default('approval');
            // İzinli ödeme yöntemleri (registry anahtarları); null = hepsi.
            $table->json('allowed_payment_methods')->nullable();
            $table->timestamps();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignUuid('dealer_group_id')->nullable()->after('type')->constrained('dealer_groups')->nullOnDelete();
            // Bayinin bölge deposu: üye alanında bu deponun stoğu ayrıca gösterilir, siparişi önce buradan düşülür.
            $table->foreignUuid('warehouse_id')->nullable()->after('dealer_group_id')->constrained('warehouses')->nullOnDelete();
            // Bayiye özel cari çalışma şekli; boş alanlar bayi grubundan gelir.
            $table->string('payment_terms', 16)->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->string('over_limit_action', 16)->nullable();
            $table->string('overdue_action', 16)->nullable();
            $table->json('allowed_payment_methods')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'credit_limit', 'payment_term_days', 'over_limit_action', 'overdue_action', 'allowed_payment_methods']);
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('dealer_group_id');
        });

        Schema::dropIfExists('dealer_groups');
    }
};
