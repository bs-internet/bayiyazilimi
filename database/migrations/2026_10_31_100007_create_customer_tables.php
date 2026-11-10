<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Üyenin kayıtlı adresleri (Hesabım → Adreslerim). Account bazlıdır; tür: shipping | billing.
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('title', 64);
            $table->string('type', 16)->default('shipping');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->string('identity_number', 11)->nullable();
            $table->string('phone', 32)->nullable();
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignUuid('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->text('address');
            $table->string('postal_code', 16)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });

        // Bayinin kaydettiği sepetler.
        Schema::create('saved_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->text('note')->nullable();
            $table->json('items');
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });

        // İade talepleri.
        Schema::create('return_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('reason', 24);
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->boolean('restocked')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('saved_carts');
        Schema::dropIfExists('customer_addresses');
    }
};
