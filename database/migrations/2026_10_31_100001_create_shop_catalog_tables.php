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
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('status')->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 32)->unique();
            $table->string('status', 16)->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_default')->default(false);
            // Satılabilir depolar sitede gösterilen stoğa dahil edilir.
            $table->boolean('is_sellable')->default(true);
            $table->string('status')->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Depo içi raf adresleri; her deponun bir "atanmamış" konumu olur (Warehouse::unassignedLocation()).
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('aisle', 16)->nullable();
            $table->string('rack', 16)->nullable();
            $table->string('bin', 16)->nullable();
            $table->boolean('is_unassigned')->default(false);
            $table->string('status', 16)->default(Status::Active->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        // Tanımlamalar › Tedarikçiler: mal kabulde (stok girişi) seçilir.
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('tax_office')->nullable();
            $table->string('tax_number', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->default(Status::Active->value)->index();
            $table->timestamps();
        });

        // Mal kabul: tedarikçinin irsaliye/faturasıyla stok girişi. Satırları
        // `stock_movements` (reference = bu kayıt) tutar; maliyet tutulmaz.
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 24);
            $table->string('document_no', 64);
            $table->date('document_date');
            $table->foreignUuid('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'document_no']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('model_number', 128)->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignUuid('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('notice', 500)->nullable();
            $table->json('highlights')->nullable();
            // Fiyatlar KDV hariç ve ürünün kendi para biriminde tutulur.
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('compare_price', 15, 4)->nullable();
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->string('sale_mode', 16)->default('cart')->index();
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_backorder')->default(false);
            // İzlenebilirlik: none | lot | serial (StockTracking) ve son kullanma tarihi takibi.
            $table->string('tracking', 16)->default('none');
            $table->boolean('expiry_tracking')->default(false);
            // Satılabilir depoların toplamı (product_stocks'tan senkronlanır).
            $table->integer('stock')->default(0)->index();
            // Kritik stok eşiği; boşsa Stok Ayarları'ndaki genel eşik. Eşiğe inince bir kez bildirilir.
            $table->unsignedInteger('min_stock')->nullable();
            $table->timestamp('low_stock_notified_at')->nullable();
            $table->unsignedInteger('min_qty')->default(1);
            $table->unsignedInteger('max_qty')->nullable();
            $table->unsignedSmallInteger('delivery_days')->default(0);
            $table->decimal('desi', 10, 2)->default(0);
            $table->string('unit', 32)->default('Adet');
            $table->boolean('is_new')->default(false);
            $table->boolean('free_shipping')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->text('search_keywords')->nullable();
            $table->string('status')->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
        });

        // Lot ya da seri numarası (ürünün `tracking` değerine göre); seride miktar en fazla 1.
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->date('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['product_id', 'code']);
        });

        // Lot takipsiz üründe stock_lot_id null. Aynı ürün+depo değişiklikleri
        // StockService'te product_stocks satır kilidiyle sıralandığı için null lot
        // satırı da tekil kalır (unique null'ları ayırt etmez).
        Schema::create('location_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_location_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_lot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_location_id', 'stock_lot_id'], 'location_stocks_product_location_lot_unique');
            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            // Hareket geçmişi depo silinerek yok edilemez (CatalogService::deleteWarehouse pasife almayı önerir).
            $table->foreignUuid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24)->index();
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->nullableUuidMorphs('reference');
            $table->string('note')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Sayım oturumu: depo (isteğe bağlı tek lokasyon) sayılır, satırlar
        // barkodla toplanır; onayda farklar toplu "Sayım Düzeltme" hareketi yazar.
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignUuid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('open')->index();
            $table->boolean('zero_uncounted')->default(false);
            $table->string('note', 500)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // `expected` satır son güncellendiğindeki sistem miktarıdır; onayda
        // `counted - expected` kadar düzeltilir (sayım sonrası satışlar korunur).
        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_location_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_lot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('expected')->default(0);
            $table->unsignedInteger('counted')->default(0);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['stock_count_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('location_stocks');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
