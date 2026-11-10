<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Birlikte uygulanabilen kampanyalar. İlişki simetriktir: A ile B
 * birleşebiliyorsa iki satır yazılır (A→B, B→A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_combinations', function (Blueprint $table): void {
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('other_id')->constrained('campaigns')->cascadeOnDelete();
            $table->primary(['campaign_id', 'other_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_combinations');
    }
};
