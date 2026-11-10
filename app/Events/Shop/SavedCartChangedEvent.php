<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\SavedCart;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Kayıtlı sepet oluşturuldu / güncellendi / sepete yüklendi / silindi.
 * `$action`: created | updated | loaded | deleted.
 */
class SavedCartChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SavedCart $savedCart,
        public readonly string $action,
        public readonly User $causer,
    ) {}
}
