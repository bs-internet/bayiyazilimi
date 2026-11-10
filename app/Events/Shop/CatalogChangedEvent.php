<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CatalogChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?Model $subject,
        public readonly string $logName,
        public readonly string $action,
        public readonly string $message,
        public readonly User $causer,
    ) {}
}
