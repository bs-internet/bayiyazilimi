<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir siparişte uygulanan kampanya: kullanım limitleri ve bütçe buradan sayılır.
 *
 * @property string $id
 * @property string|null $campaign_id silinen kampanyada boş
 * @property string|null $campaign_name kullanım anındaki kampanya adı
 * @property string $order_id
 * @property string|null $account_id
 * @property string|null $user_id
 * @property string|null $email
 * @property BigDecimal $value kazanç (ana para biriminde)
 */
#[Fillable(['campaign_id', 'campaign_name', 'order_id', 'account_id', 'user_id', 'email', 'value'])]
class CampaignUsage extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'value' => Decimal::class.':2',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
