<?php

declare(strict_types=1);

use App\Enums\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique()->index();
            $table->string('name');
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('thousands_separator', 1)->default(',');
            $table->string('decimal_separator', 1)->default('.');
            // 1 birim = exchange_rate TL. Varsayılan para birimi (TL) için 1.
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->string('rate_source', 16)->default('manual');
            $table->timestamp('rate_updated_at')->nullable();
            $table->string('status')->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
