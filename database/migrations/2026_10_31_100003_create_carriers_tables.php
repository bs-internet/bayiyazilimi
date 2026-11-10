<?php

declare(strict_types=1);

use App\Enums\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kargo firmaları ve desi bazlı fiyat tabloları.
        Schema::create('carriers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('logo')->nullable();
            // Takip bağlantısı; {code} yerine takip numarası yazılır.
            $table->string('tracking_url')->nullable();
            // Tablonun en üst desisini aşan her desi için ek ücret (TL).
            $table->decimal('extra_desi_price', 12, 2)->default(0);
            // Fiyatlar KDV dahil mi? Değilse sepette %20 KDV eklenir.
            $table->boolean('prices_include_tax')->default(true);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('carrier_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carrier_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_desi', 10, 2)->default(0);
            $table->decimal('max_desi', 10, 2);
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->index(['carrier_id', 'max_desi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_rates');
        Schema::dropIfExists('carriers');
    }
};
