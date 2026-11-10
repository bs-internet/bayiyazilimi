<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cari hesap defteri: müşterinin tüm aldı / verdi hareketleri.
 * Kayıt silinmez; düzeltme ters kayıtla yapılır. Tutarlar ana para birimindedir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            // order, payment, return, refund, opening, adjustment, cheque_bounce
            $table->string('type', 24)->index();
            // debit (borç) / credit (alacak)
            $table->string('direction', 8);
            // Ana para biriminde tutar.
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3);
            // Belgenin kendi para birimi ve çevrim kuru (bilgi).
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->date('transaction_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->nullableUuidMorphs('source');
            // system ya da erp:{ad}; ERP kaydı origin + external_ref ile tekildir.
            $table->string('origin', 32)->default('system');
            $table->string('external_ref', 100)->nullable();
            $table->foreignUuid('reversal_of')->nullable()->unique()->constrained('account_transactions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['origin', 'external_ref']);
            $table->index(['account_id', 'transaction_date']);
        });

        // FIFO dağıtımı: hangi alacak hangi borcu ne kadar kapattı (her hareketten sonra yeniden hesaplanır).
        Schema::create('account_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('debit_id')->constrained('account_transactions')->cascadeOnDelete();
            $table->foreignUuid('credit_id')->constrained('account_transactions')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['account_id', 'debit_id']);
        });

        // Havale / EFT ödeme bildirimi: bayi dekontla bildirir, yönetici onaylar (ödeme + cari alacak) ya da reddeder.
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3);
            $table->date('transfer_date');
            $table->string('sender_name')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('reject_reason')->nullable();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // Çek / senet portföyü (isteğe bağlı, Cari Ayarları'ndan açılır). Alındığında tahsilat (payment) + cari alacak;
        // karşılıksız / iade olursa cariye aynı tutarda borç (cheque_bounce).
        Schema::create('cheques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            // cheque (çek) / promissory_note (senet)
            $table->string('type', 16);
            $table->string('serial_no', 64);
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            // Keşideci (çeki yazan) / borçlu.
            $table->string('drawer')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3);
            $table->date('due_date')->index();
            // portfolio, deposited, collected, bounced, returned, endorsed
            $table->string('status', 16)->default('portfolio')->index();
            $table->string('endorsed_to')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('account_allocations');
        Schema::dropIfExists('account_transactions');
    }
};
