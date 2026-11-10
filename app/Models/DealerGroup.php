<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bayi grubu: gruptaki bayilere uygulanan tek indirim yüzdesi ve varsayılan
 * cari çalışma şekli (peşin / vadeli, limit, vade; bayi bazında değiştirilebilir).
 *
 * @property BigDecimal $discount_rate
 * @property ?BigDecimal $credit_limit
 */
#[Fillable([
    'name',
    'discount_rate',
    'description',
    'payment_terms',
    'credit_limit',
    'payment_term_days',
    'over_limit_action',
    'overdue_action',
    'allowed_payment_methods',
])]
class DealerGroup extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'discount_rate' => Decimal::class.':2',
            'credit_limit' => Decimal::class.':2',
            'payment_term_days' => 'integer',
            'allowed_payment_methods' => 'array',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
