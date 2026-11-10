<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->string('source', 16)->default('request')->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('company')->nullable();
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->string('city')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignUuid('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('customer_responded_at')->nullable();
            // Siparişe dönüştüğünde; orders.quote_id ile döngüsel olduğundan FK yok.
            $table->uuid('order_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->string('unit', 32)->nullable();
            $table->unsignedInteger('quantity');
            // Talep anındaki liste fiyatı ve yöneticinin teklif ettiği birim fiyat (KDV hariç, teklif para biriminde).
            $table->decimal('list_price', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quote_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->text('note')->nullable();
            $table->boolean('by_customer')->default(false);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_status', 16)->default('pending')->index();
            // Ödeme yöntemi: PaymentRegistry anahtarı (bank_transfer, on_account, modül sürücüleri).
            $table->string('payment_method', 24);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignUuid('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('campaign_name')->nullable();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->string('customer_name');
            $table->string('email')->index();
            $table->string('phone', 32);
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            // Sipariş anında ana para biriminin TL kuru: cari bu kurla ana birime çevrilir.
            $table->decimal('base_exchange_rate', 18, 6)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('total_desi', 10, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            // Ödeme yöntemine özel fark (havale indirimi −, ek ücret +); grand_total'a dahildir.
            $table->decimal('payment_adjustment', 15, 2)->default(0);
            $table->string('payment_adjustment_label')->nullable();
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('cargo_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('invoice_number', 64)->nullable()->index();
            $table->timestamp('invoiced_at')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('paid_at')->nullable();
            // Cari borcunun vade tarihi (borç "Hazırlanıyor"da yazılırken belirlenir).
            $table->date('due_date')->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity');
            // Sipariş para biriminde, KDV hariç birim fiyat.
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('campaign')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->nullable();
            $table->string('note')->nullable();
            $table->boolean('notify_customer')->default(false);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Tüm ödemeler: kart, onaylanan havale, havale bildirimi, elle tahsilat.
        // order_id boşsa siparişten bağımsız cari tahsilattır.
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 24)->index();
            $table->string('provider', 24);
            $table->string('merchant_oid', 64)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3);
            $table->unsignedTinyInteger('installment')->default(1);
            // Müşterinin ödediği taksit / komisyon farkı (sipariş tutarına dahil değil).
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->string('status', 16)->default('pending')->index();
            $table->string('failed_reason')->nullable();
            $table->string('note')->nullable();
            $table->json('payload')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Kart ödemesinin sağlayıcı üzerinden iadesi (pending → succeeded / failed).
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3);
            $table->string('status', 16)->default('pending')->index();
            $table->string('failed_reason')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Kampanya kullanımları: kampanya silinse de geçmiş (ad) korunur.
        Schema::create('campaign_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('campaign_name')->nullable();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('account_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();
            // Kampanyanın bu siparişteki kazancı (indirim + hediye + kargo), ana para biriminde.
            $table->decimal('value', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_usages');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('quote_histories');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
