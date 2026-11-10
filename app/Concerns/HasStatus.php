<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ortak `Status` enum'u (Active/Passive) kullanan modeller için scope trait'i.
 *
 * Sadece `App\Enums\Status` kullanan modellerde uygulanır.
 *
 * @property Status $status
 */
trait HasStatus
{
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', Status::Active->value);
    }

    #[Scope]
    protected function passive(Builder $query): void
    {
        $query->where('status', Status::Passive->value);
    }

    public function isActive(): bool
    {
        return $this->getAttribute('status') === Status::Active;
    }

    /**
     * Durum anahtarının gönderdiği hedef zaten geçerli mi. Anahtar hedef durumu
     * gönderir; çift tıklama ya da iki yöneticinin aynı anda değiştirmesi durumu
     * geri çevirmez. Hedef gönderilmezse (eski istemci) false: değiştirilir.
     */
    public function hasStatus(mixed $target): bool
    {
        $target = is_string($target) ? Status::tryFrom($target) : null;

        return $target !== null && $this->getAttribute('status') === $target;
    }
}
