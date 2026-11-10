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
        // Sözleşmeler (Tanımlamalar): ödeme / üyelik formunda onaylatılanlar;
        // aktif olanların hepsi bayi panelinde (Sözleşmeler) okunur.
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->boolean('show_on_checkout')->default(false);
            $table->boolean('show_on_registration')->default(false);
            $table->string('status', 16)->default(Status::Passive->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Havale / EFT için banka hesapları.
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_holder');
            $table->string('iban', 34);
            $table->string('account_number', 32)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('status', 16)->default(Status::Active->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('contracts');
    }
};
