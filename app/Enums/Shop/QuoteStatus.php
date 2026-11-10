<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Teklif durumları (müşteri talebi ya da yöneticinin hazırladığı teklif).
 *
 *   müşteri talebi → pending ─┐
 *   yönetici taslağı → draft ─┼→ (yönetici fiyatlar, gönderir) → sent
 *   revize isteği → revision ─┘
 *   sent → approved (müşteri onaylar) → ordered (sipariş oluşur)
 *   sent → revision (müşteri revize ister) | rejected (müşteri reddeder)
 *   açık her durumda yönetici iptal edebilir → cancelled
 *
 * Süresi dolmuş teklif ayrı bir durum değildir (valid_until geçmişse gösterimde).
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Revision = 'revision';
    case Sent = 'sent';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Yönetici tarafındaki ad. */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Taslak'),
            self::Pending => __('Fiyat bekliyor'),
            self::Revision => __('Revize istendi'),
            self::Sent => __('Yanıt bekliyor'),
            self::Approved => __('Onaylandı'),
            self::Ordered => __('Siparişe dönüştü'),
            self::Rejected => __('Reddedildi'),
            self::Cancelled => __('İptal edildi'),
        };
    }

    /** Müşteri tarafındaki ad. */
    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft, self::Pending => __('Fiyatlandırılıyor'),
            self::Revision => __('Revize ediliyor'),
            self::Sent => __('Onayınızı bekliyor'),
            self::Approved => __('Onaylandı — ödeme bekliyor'),
            self::Ordered => __('Siparişe dönüştü'),
            self::Rejected => __('Reddedildi'),
            self::Cancelled => __('İptal edildi'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Pending, self::Revision => 'warning',
            self::Sent => 'info',
            self::Approved, self::Ordered => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    /** Yönetici fiyat / kalem düzenleyebilir mi. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Revision, self::Sent], true);
    }

    /** Fiyatlar müşteriye gösterilir mi. */
    public function isPriced(): bool
    {
        return in_array($this, [self::Sent, self::Approved, self::Ordered], true);
    }

    /**
     * Kapandı: artık hiçbir geçiş yok. "Siparişe dönüştü" kapalı değildir —
     * ödenmemiş sipariş iptal edilirse teklif yeniden "Onaylandı" olur.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled], true);
    }

    /**
     * İzinli geçişler. Gönderilmiş teklif yeniden gönderilebilir (Sent → Sent).
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft, self::Pending, self::Revision => [self::Sent, self::Cancelled],
            self::Sent => [self::Sent, self::Approved, self::Revision, self::Rejected, self::Cancelled],
            self::Approved => [self::Ordered, self::Cancelled],
            self::Ordered => [self::Approved],
            self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** Yönetici işlemi bekleyen (panel "bekleyen işler"). */
    public static function awaitingAdmin(): array
    {
        return [self::Pending->value, self::Revision->value];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
