<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bayinin kayıtlı sepeti. Hesap (Account) bazlıdır; kalemler oturum sepetiyle
 * aynı biçimde `items` JSON'unda [{product_id, quantity}] olarak tutulur.
 * Fiyat saklanmaz — tutar her gösterimde güncel fiyattan hesaplanır.
 */
#[Fillable(['user_id', 'name', 'note', 'items'])]
class SavedCart extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, int> product_id => quantity
     */
    public function quantities(): array
    {
        $map = [];

        foreach ($this->items ?? [] as $item) {
            $id = (string) ($item['product_id'] ?? '');

            if ($id !== '') {
                $map[$id] = ($map[$id] ?? 0) + max(1, (int) ($item['quantity'] ?? 1));
            }
        }

        return $map;
    }

    public function itemCount(): int
    {
        return count($this->items ?? []);
    }
}
