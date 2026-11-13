<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Webhook adresi: abone olunan olaylarda imzalı POST alır. İmza anahtarı
 * şifreli saklanır ve yalnız oluşturulurken bir kez gösterilir.
 *
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property ?string $description
 * @property bool $active
 * @property int $failure_count
 */
#[Fillable(['url', 'secret', 'events', 'description', 'active', 'created_by'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    use HasUuids;

    /** Art arda bu kadar başarısız gönderimden sonra adres kendiliğinden kapatılır. */
    public const DISABLE_AFTER_FAILURES = 50;

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
            'failure_count' => 'integer',
            'last_delivery_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        return in_array('*', $this->events, true) || in_array($event, $this->events, true);
    }
}
