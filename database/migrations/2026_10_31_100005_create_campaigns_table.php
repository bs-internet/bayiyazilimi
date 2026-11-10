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
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('badge', 40)->nullable();
            $table->json('audience_rules')->nullable();
            $table->string('product_scope', 20)->default('all');
            $table->json('product_rules')->nullable();
            $table->string('action', 40);
            $table->json('action_settings')->nullable();
            $table->string('shipping_mode', 10)->default('cart');
            $table->decimal('shipping_amount', 12, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('status', 20)->default(Status::Passive->value)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
