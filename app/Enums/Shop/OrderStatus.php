<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum OrderStatus: string
{
    case Pending = 'pending';
    /** Vadeli sipariş limit aşımı / vadesi geçmiş borç nedeniyle yönetici onayında. */
    case AwaitingApproval = 'awaiting_approval';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Ödeme Bekleniyor'),
            self::AwaitingApproval => __('Onay Bekliyor'),
            self::Processing => __('Hazırlanıyor'),
            self::Shipped => __('Kargoya Verildi'),
            self::Completed => __('Tamamlandı'),
            self::Cancelled => __('İptal Edildi'),
            self::Failed => __('Ödeme Başarısız'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::AwaitingApproval => 'warning',
            self::Processing => 'info',
            self::Shipped => 'primary',
            self::Completed => 'success',
            self::Cancelled, self::Failed => 'danger',
        };
    }

    /**
     * Stoğun düşülmüş (ayrılmış) kabul edildiği durumlar. Stok sipariş anında
     * düşüldüğü için ödeme / onay bekleyen sipariş de stok tutar; iptal ve
     * başarısız ödeme geri ekler.
     */
    public function holdsStock(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Failed], true);
    }

    /** Sipariş kesinleşti mi (cari borç yazılır). */
    public function isConfirmed(): bool
    {
        return in_array($this, [self::Processing, self::Shipped, self::Completed], true);
    }

    /** Mal sevk edildi mi: iptal edilemez, geri dönüş İade talebiyle yapılır. */
    public function isDispatched(): bool
    {
        return in_array($this, [self::Shipped, self::Completed], true);
    }

    /**
     * Yöneticinin bu durumdan elle geçirebileceği durumlar (mevcut durum hariç).
     * Ödeme Bekleniyor, Onay Bekliyor ve Ödeme Başarısız sistemin kendi
     * durumlarıdır, elle seçilmez. Kargoya verilmiş / tamamlanmış sipariş iptal
     * edilmez: geri dönüş İade talebiyle yapılır (stok ve cari orada işlenir).
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Pending, self::AwaitingApproval => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Completed, self::Cancelled],
            self::Shipped => [self::Processing, self::Completed],
            self::Completed => [self::Shipped],
            self::Cancelled => [self::Processing],
            self::Failed => [self::Cancelled],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /**
     * Durum seçicisinin seçenekleri: mevcut durum (yalnız not / kargo bilgisi
     * güncellemek için) + izinli hedefler.
     *
     * @return list<self>
     */
    public function manualTargets(): array
    {
        return [$this, ...$this->allowedTargets()];
    }

    /**
     * @param  list<self>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    public static function optionsFor(array $cases): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], $cases);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
