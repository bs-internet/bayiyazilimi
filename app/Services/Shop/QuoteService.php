<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\QuoteStatus;
use App\Events\Shop\QuoteRequestedEvent;
use App\Events\Shop\QuoteStatusChangedEvent;
use App\Events\Shop\QuoteUpdatedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Teklif akışı.
 *
 * Müşteri talep eder (pending) ya da yönetici taslak hazırlar (draft). Yönetici
 * fiyatlandırıp gönderir (sent). Müşteri onaylar (approved → ödeme → ordered),
 * revize ister (revision → yönetici yeniden gönderir) ya da reddeder (rejected).
 * Yönetici açık teklifi iptal edebilir; müşteri fiyatlanmamış talebini geri çekebilir.
 *
 * Fiyatlar teklifin para biriminde ve KDV hariçtir; kargo KDV dahil tutardır.
 */
class QuoteService
{
    public function __construct(
        private readonly CurrencyManager $currencies,
    ) {}

    /**
     * Teklif listesinden müşteri talebi oluşturur.
     *
     * @param  array<string, mixed>  $data
     */
    public function request(Basket $list, array $data, ?User $user, Currency $currency, ?string $ip = null): Quote
    {
        $lines = $list->lines($currency);

        if ($lines->isEmpty()) {
            throw new ShopException(__('Teklif listeniz boş.'));
        }

        $quote = DB::transaction(function () use ($lines, $data, $user, $currency, $ip) {
            $quote = Quote::create([
                'number' => OrderService::nextNumber('T', 'quotes'),
                'status' => QuoteStatus::Pending,
                'source' => Quote::SOURCE_REQUEST,
                'user_id' => $user?->id,
                'account_id' => $user?->account_id,
                'customer_name' => $data['customer_name'],
                'company' => $data['company'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'currency_code' => $currency->code,
                'exchange_rate' => $currency->exchange_rate,
                'ip_address' => $ip,
            ]);

            foreach ($lines->values() as $i => $line) {
                $quote->items()->create([
                    'product_id' => $line['product']->id,
                    'sku' => $line['product']->sku,
                    'name' => $line['product']->name,
                    'unit' => $line['product']->unit,
                    'quantity' => $line['quantity'],
                    'list_price' => $line['unit_net'],
                    'unit_price' => null,
                    'tax_rate' => $line['tax_rate'],
                    'sort_order' => $i,
                ]);
            }

            $this->recalculate($quote);
            $this->history($quote, QuoteStatus::Pending, $data['customer_note'] ?? null, $user, byCustomer: true);

            return $quote;
        });

        $list->clear();

        QuoteRequestedEvent::dispatch($quote);

        return $quote;
    }

    /**
     * Yöneticinin teklifi oluşturması / düzenlemesi. Yeni teklif taslak olarak
     * açılır; müşteriye ancak send() ile gider.
     *
     * @param  array{user_id?: ?string, currency_code?: ?string, admin_note?: ?string, valid_from?: ?string, valid_until?: ?string, shipping_total?: mixed, items: array<int, array{product_id: string, quantity: mixed, unit_price: mixed, unit?: ?string, tax_rate?: mixed}>}  $data
     */
    public function save(?Quote $quote, array $data, User $causer, bool $resend = false): Quote
    {
        $isNew = $quote === null;

        $quote = DB::transaction(function () use ($quote, $data, $causer, $resend) {
            if ($quote !== null) {
                // Müşterinin onay / ret yanıtıyla yarışmasın: teklif kilitli yeniden okunur.
                $this->lock($quote);

                if (! $quote->isEditable()) {
                    throw new ShopException(__('Bu teklif artık düzenlenemez (onaylanmış, siparişe dönüşmüş ya da kapanmış).'));
                }

                // Müşterinin gördüğü fiyatlar sessizce değişmesin: gönderilmiş teklif
                // ancak yeniden gönderilerek değiştirilir.
                if ($quote->status === QuoteStatus::Sent && ! $resend) {
                    throw new ShopException(__('Müşteriye gönderilmiş teklif değiştirilince yeniden gönderilmelidir; "Kaydet ve yeniden gönder"i kullanın.'));
                }
            }

            if ($quote === null) {
                $currency = $this->currencies->find($data['currency_code'] ?? null) ?? $this->currencies->base();

                $quote = new Quote([
                    'number' => OrderService::nextNumber('T', 'quotes'),
                    'status' => QuoteStatus::Draft,
                    'source' => Quote::SOURCE_ADMIN,
                    'created_by' => $causer->id,
                    'currency_code' => $currency->code,
                    'exchange_rate' => $currency->exchange_rate,
                ]);
            }

            // Yöneticinin hazırladığı teklifte müşteri seçilir / değiştirilebilir.
            if ($quote->isAdminCreated() && ! empty($data['user_id'])) {
                $customer = User::query()->with('account')->findOrFail($data['user_id']);
                $quote->fill([
                    'user_id' => $customer->id,
                    'account_id' => $customer->account_id,
                    'customer_name' => $customer->name,
                    'company' => $customer->account?->title,
                    'email' => $customer->email,
                ]);
            }

            $quote->fill([
                'admin_note' => $data['admin_note'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'shipping_total' => Money::round(self::number($data['shipping_total'] ?? 0)),
            ])->save();

            $this->replaceItems($quote, $data['items']);
            $this->recalculate($quote);

            return $quote;
        });

        if ($isNew) {
            $this->history($quote, QuoteStatus::Draft, null, $causer);
        }

        QuoteUpdatedEvent::dispatch($quote, $causer);

        return $quote;
    }

    /**
     * Fiyatlanmış teklifi müşteriye gönderir (yeniden gönderim de olur).
     */
    public function send(Quote $quote, User $causer, ?string $note = null): void
    {
        $this->transition($quote, QuoteStatus::Sent, $causer, $note, guard: function (Quote $quote) use ($causer): array {
            if (! $quote->isEditable()) {
                throw new ShopException(__('Bu teklif gönderilemez.'));
            }

            $quote->load('items');

            if ($quote->items->isEmpty()) {
                throw new ShopException(__('Teklifte en az bir ürün olmalı.'));
            }

            if ($quote->items->contains(fn ($i) => $i->unit_price === null)) {
                throw new ShopException(__('Göndermeden önce tüm kalemlere teklif fiyatı girin.'));
            }

            if ($quote->user_id === null && blank($quote->email)) {
                throw new ShopException(__('Teklifin gönderileceği müşteri seçilmedi.'));
            }

            return [
                'valid_until' => $quote->valid_until === null || $quote->isExpired()
                    ? now()->addDays(max(1, ShopSettings::int('quote_valid_days')))->toDateString()
                    : $quote->valid_until,
                'sent_at' => now(),
                'responded_at' => now(),
                'responded_by' => $causer->id,
            ];
        });
    }

    public function approve(Quote $quote, ?User $user, ?string $note = null): void
    {
        $this->respond($quote, QuoteStatus::Approved, $user, $note);
    }

    public function requestRevision(Quote $quote, ?User $user, string $note): void
    {
        $this->respond($quote, QuoteStatus::Revision, $user, $note);
    }

    public function reject(Quote $quote, ?User $user, ?string $note = null): void
    {
        $this->respond($quote, QuoteStatus::Rejected, $user, $note);
    }

    /** Müşteri fiyatlanmamış talebini geri çeker. */
    public function withdraw(Quote $quote, ?User $user): void
    {
        $this->transition($quote, QuoteStatus::Cancelled, $user, __('Müşteri talebini geri çekti.'), byCustomer: true, guard: function (Quote $quote): array {
            if (! $quote->canWithdraw()) {
                throw new ShopException(__('Bu teklif artık geri çekilemez.'));
            }

            return [];
        });
    }

    /** Yönetici açık teklifi iptal eder (kilit altında: aynı anda siparişe dönüşen teklif iptal edilmez). */
    public function cancel(Quote $quote, User $causer, ?string $note = null): void
    {
        $this->transition($quote, QuoteStatus::Cancelled, $causer, $note, guard: function (Quote $quote): array {
            if ($quote->status->isFinal() || $quote->order_id !== null || $quote->status === QuoteStatus::Ordered) {
                throw new ShopException(__('Sonuçlanmış ya da siparişe dönüşmüş teklif iptal edilemez.'));
            }

            return [];
        });
    }

    /** Müşteri yanıtı (onay / revize / ret): yanıtlanabilirlik kilit altında yeniden denetlenir. */
    private function respond(Quote $quote, QuoteStatus $status, ?User $user, ?string $note): void
    {
        $this->transition($quote, $status, $user, $note, ['customer_responded_at' => now()], byCustomer: true, guard: function (Quote $quote): array {
            $this->ensureCanRespond($quote);

            return [];
        });
    }

    /** Yalnız müşteriye hiç gitmemiş taslak silinebilir. */
    public function delete(Quote $quote, User $causer): void
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new ShopException(__('Yalnız taslak teklif silinebilir; diğerlerini iptal edebilirsiniz.'));
        }

        $quote->delete();
    }

    /** Teklif üzerinden sipariş oluştu (OrderService::placeFromQuote içinde, teklif kilitliyken). */
    public function markOrdered(Quote $quote, Order $order): void
    {
        if (! $quote->status->canTransitionTo(QuoteStatus::Ordered)) {
            throw new ShopException(__('Bu teklif siparişe dönüştürülemez (onaylı değil, süresi dolmuş veya zaten sipariş edilmiş).'));
        }

        $quote->forceFill(['order_id' => $order->id, 'status' => QuoteStatus::Ordered])->save();
        $this->history($quote, QuoteStatus::Ordered, __('Sipariş :number oluşturuldu.', ['number' => $order->number]), null);
    }

    /**
     * Ödenmemiş sipariş iptal edildi (sipariş transaction'ı içinde): teklif
     * yeniden ödenebilir (onaylı) olur.
     */
    public static function orderCancelled(Order $order, ?string $note = null): void
    {
        $quotes = Quote::query()->withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('status', QuoteStatus::Ordered->value)
            ->lockForUpdate()
            ->get();

        foreach ($quotes as $quote) {
            $quote->forceFill(['order_id' => null, 'status' => QuoteStatus::Approved])->save();
            app(self::class)->history($quote, QuoteStatus::Approved, $note ?? __('Sipariş :number iptal edildi; teklif yeniden ödenebilir.', ['number' => $order->number]), null);
        }
    }

    /**
     * İptal edilmiş sipariş yeniden açılıyor (sipariş transaction'ı içinde): teklif
     * yeniden bu siparişe bağlanır. Teklif bu arada başka bir siparişe dönüştüyse
     * ya da artık onaylı değilse sipariş yeniden açılamaz (aynı tekliften iki sipariş olmasın).
     */
    public static function orderReopened(Order $order): void
    {
        if ($order->quote_id === null) {
            return;
        }

        $quote = Quote::query()->withoutGlobalScopes()->lockForUpdate()->find($order->quote_id);

        if ($quote === null || ($quote->status === QuoteStatus::Ordered && $quote->order_id === $order->id)) {
            return;
        }

        if ($quote->order_id !== null || $quote->status !== QuoteStatus::Approved) {
            throw new ShopException(__('Bu siparişin teklifi (:number) başka bir siparişe dönüştürülmüş ya da kapanmış; sipariş yeniden açılamaz.', ['number' => $quote->number]));
        }

        $quote->forceFill(['order_id' => $order->id, 'status' => QuoteStatus::Ordered])->save();
        app(self::class)->history($quote, QuoteStatus::Ordered, __('Sipariş :number yeniden açıldı.', ['number' => $order->number]), null);
    }

    /**
     * Tutarları kalemlerden yeniden hesaplar. Fiyatı girilmemiş kalemde liste fiyatı kullanılır.
     */
    public function recalculate(Quote $quote): void
    {
        $quote->load('items');
        $nets = [];
        $taxes = [];

        foreach ($quote->items as $item) {
            $line = Money::line($item->unit_price ?? $item->list_price, $item->quantity, $item->tax_rate);
            $item->update(['line_total' => $line['gross']]);
            $nets[] = $line['net'];
            $taxes[] = $line['tax'];
        }

        $subtotal = Money::add(...$nets);
        $tax = Money::add(...$taxes);

        $quote->forceFill([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'grand_total' => Money::add($subtotal, $tax, $quote->shipping_total),
        ])->save();
    }

    public function history(Quote $quote, QuoteStatus $status, ?string $note, ?User $user, bool $byCustomer = false): void
    {
        $quote->histories()->create([
            'status' => $status,
            'note' => filled($note) ? $note : null,
            'by_customer' => $byCustomer,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Tek geçiş noktası: teklif kilitlenip güncel haliyle yeniden okunur, koruma
     * (guard) kilit altında çalışır ve geçiş haritası (QuoteStatus::canTransitionTo)
     * denetlenir. Guard ek öznitelik döndürebilir.
     *
     * @param  array<string, mixed>  $attributes
     * @param  (callable(Quote): array<string, mixed>)|null  $guard
     */
    private function transition(Quote $quote, QuoteStatus $status, ?User $user, ?string $note, array $attributes = [], bool $byCustomer = false, ?callable $guard = null): void
    {
        $previous = DB::transaction(function () use ($quote, $status, $user, $note, $attributes, $byCustomer, $guard): QuoteStatus {
            $this->lock($quote);
            $previous = $quote->status;

            $extra = $guard !== null ? $guard($quote) : [];

            if (! $previous->canTransitionTo($status)) {
                throw new ShopException(__(':from durumundaki teklif :to yapılamaz.', ['from' => $previous->label(), 'to' => $status->label()]));
            }

            $quote->forceFill(['status' => $status, ...$attributes, ...$extra])->save();
            $this->history($quote, $status, $note, $user, $byCustomer);

            return $previous;
        });

        QuoteStatusChangedEvent::dispatch($quote, $previous, $user, $byCustomer, $note);
    }

    /** Transaction içinde: teklifi kilitleyip güncel haliyle yeniden okur. */
    private function lock(Quote $quote): void
    {
        $locked = Quote::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($quote->id);
        $quote->setRawAttributes($locked->getAttributes(), true);
        $quote->unsetRelation('items');
    }

    private function ensureCanRespond(Quote $quote): void
    {
        if (! $quote->canRespond()) {
            throw new ShopException($quote->isExpired()
                ? __('Teklifin geçerlilik süresi doldu.')
                : __('Bu teklif şu an yanıtlanamaz.'));
        }
    }

    /**
     * Kalemleri formdakilerle değiştirir. Liste fiyatı (talep anındaki site
     * fiyatı) aynı ürün için korunur; yeni ürünlerde güncel fiyat alınır.
     *
     * @param  array<int, array{product_id: string, quantity: mixed, unit_price: mixed, unit?: ?string, tax_rate?: mixed}>  $rows
     */
    private function replaceItems(Quote $quote, array $rows): void
    {
        $quote->loadMissing('items');
        $previous = $quote->items->keyBy('product_id');
        $products = Product::query()->withTrashed()->with('currency')
            ->whereIn('id', array_column($rows, 'product_id'))->get()->keyBy('id');
        $currency = $this->currencies->find($quote->currency_code) ?? $this->currencies->base();

        $quote->items()->delete();

        foreach (array_values($rows) as $i => $row) {
            $product = $products->get($row['product_id']);

            if ($product === null) {
                continue;
            }

            $old = $previous->get($product->id);
            $price = $row['unit_price'] ?? null;
            $rate = isset($row['tax_rate']) && $row['tax_rate'] !== '' && $row['tax_rate'] !== null
                ? Money::round(self::number($row['tax_rate']))
                : $product->tax_rate;

            $quote->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => ($row['unit'] ?? null) ?: $product->unit,
                'quantity' => max(1, (int) $row['quantity']),
                'list_price' => $old?->list_price
                    ?? Money::round($this->currencies->convert($product->price, $product->currency, $currency), 4),
                'unit_price' => $price === null || $price === '' ? null : Money::round(self::number($price), 4),
                'tax_rate' => $rate,
                'note' => $old?->note,
                'sort_order' => $i,
            ]);
        }

        $quote->unsetRelation('items');
    }

    /** Formdan gelen sayı ("80,50" ya da "80.50") → kesin ondalık. */
    private static function number(mixed $value): BigDecimal
    {
        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) ? Money::decimal($value) : BigDecimal::zero();
    }
}
