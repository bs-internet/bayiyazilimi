<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Enums\Shop\QuoteStatus;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Teklif: müşterinin talebi (source=request) ya da yöneticinin hazırladığı
 * teklif (source=admin). Yönetici fiyatlandırıp gönderir; müşteri onaylar,
 * revize ister ya da reddeder; onaylanan teklif ödenince siparişe dönüşür.
 *
 * @property BigDecimal $exchange_rate
 * @property BigDecimal $subtotal
 * @property BigDecimal $tax_total
 * @property BigDecimal $shipping_total
 * @property BigDecimal $grand_total
 * @property QuoteStatus $status
 * @property ?CarbonImmutable $valid_from
 * @property ?CarbonImmutable $valid_until
 */
#[Fillable([
    'number',
    'status',
    'source',
    'user_id',
    'account_id',
    'created_by',
    'customer_name',
    'company',
    'email',
    'phone',
    'city',
    'customer_note',
    'admin_note',
    'currency_code',
    'exchange_rate',
    'subtotal',
    'tax_total',
    'shipping_total',
    'grand_total',
    'valid_from',
    'valid_until',
    'sent_at',
    'responded_at',
    'responded_by',
    'customer_responded_at',
    'order_id',
    'ip_address',
])]
class Quote extends Model
{
    use BelongsToAccount, HasUuids;

    public const SOURCE_REQUEST = 'request';

    public const SOURCE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'exchange_rate' => Decimal::class.':6',
            'subtotal' => Decimal::class.':2',
            'tax_total' => Decimal::class.':2',
            'shipping_total' => Decimal::class.':2',
            'grand_total' => Decimal::class.':2',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'customer_responded_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(QuoteHistory::class)->latest();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Müşteriye görünen teklifler (yönetici taslakları gizli).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleToCustomer(Builder $query): void
    {
        $query->where('status', '!=', QuoteStatus::Draft->value);
    }

    /**
     * Geçerlilik süresi dolmamış teklifler (bitiş günü dahil; isExpired ile aynı kural).
     *
     * @param  Builder<self>  $query
     */
    public function scopeNotExpired(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()));
    }

    public function isAdminCreated(): bool
    {
        return $this->source === self::SOURCE_ADMIN;
    }

    /** Geçerlilik başlangıcı henüz gelmedi. */
    public function notStarted(): bool
    {
        return $this->valid_from !== null && $this->valid_from->isFuture();
    }

    /** Geçerlilik süresi doldu (bitiş günü dahil geçerlidir). */
    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /** Yönetici kalem / fiyat düzenleyebilir mi. */
    public function isEditable(): bool
    {
        return $this->status->isEditable() && $this->order_id === null;
    }

    /** Müşteri onaylayabilir / revize isteyebilir / reddedebilir mi. */
    public function canRespond(): bool
    {
        return $this->status === QuoteStatus::Sent && $this->order_id === null
            && ! $this->notStarted() && ! $this->isExpired();
    }

    /** Müşteri talebini geri çekebilir mi (henüz fiyatlanmamışken). */
    public function canWithdraw(): bool
    {
        return in_array($this->status, [QuoteStatus::Pending, QuoteStatus::Revision], true);
    }

    /** Onaylanmış, süresi geçmemiş ve henüz siparişe dönüşmemiş teklif ödenebilir. */
    public function canBeOrdered(): bool
    {
        return $this->status === QuoteStatus::Approved && $this->order_id === null
            && ! $this->notStarted() && ! $this->isExpired();
    }

    /** Müşteri tarafındaki durum adı (süresi dolmuşsa belirtilir). */
    public function customerStatusLabel(): string
    {
        if (in_array($this->status, [QuoteStatus::Sent, QuoteStatus::Approved], true) && $this->isExpired()) {
            return __('Süresi doldu');
        }

        return $this->status->customerLabel();
    }

    /**
     * Müşterinin teklifi giriş yapmadan görebileceği imzalı bağlantı. Yalnız
     * vitrin modülü kuruluysa vardır (`shop.quote.public_url`); yoksa null.
     */
    public function publicUrl(): ?string
    {
        return hooks()->apply('shop.quote.public_url', null, quote: $this);
    }

    /**
     * Müşterinin e-postadaki bağlantısı: bayi → /app (vitrine hiç uğramaz). Bayi
     * olmayan müşteri (vitrin) için bağlantıyı vitrin modülü verir
     * (`shop.quote.customer_url`: Hesabım ya da imzalı sayfa).
     */
    public function customerUrl(): string
    {
        $app = route('app.shop.quotes.show', $this->id);

        if ($this->account_id !== null && $this->account?->isDealer()) {
            return $app;
        }

        return hooks()->apply('shop.quote.customer_url', null, quote: $this) ?? $app;
    }
}
