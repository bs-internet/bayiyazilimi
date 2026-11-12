<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\LimitAction;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentNotificationStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RefundStatus;
use App\Events\Shop\OrderInvoicedEvent;
use App\Events\Shop\OrderPaidEvent;
use App\Events\Shop\OrderPlacedEvent;
use App\Events\Shop\OrderStatusChangedEvent;
use App\Events\Shop\StockReservationExpiredEvent;
use App\Events\Shop\UnappliedPaymentReceivedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentMethods;
use App\Support\Payments\PaymentRegistry;
use App\Support\Shop\Campaigns\CampaignUsages;
use App\Support\Shop\CheckoutGuard;
use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly OrderStockService $stock,
        private readonly CurrencyManager $currencies,
        private readonly PaymentRegistry $payments,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Sepetten sipariş oluşturur.
     *
     * @param  array<string, mixed>  $data  doğrulanmış ödeme formu
     */
    public function placeFromCart(Basket $cart, array $data, ?User $user, Currency $currency, ?string $ip = null): Order
    {
        return $this->guarded($data, fn (): Order => $this->placeCart($cart, $data, $user, $currency, $ip));
    }

    /**
     * Ödeme anahtarı tek kullanımlıktır (çift gönderim iki sipariş açmaz);
     * sipariş oluşmazsa anahtar bırakılır.
     *
     * @param  array<string, mixed>  $data
     * @param  callable(): Order  $place
     */
    private function guarded(array $data, callable $place): Order
    {
        $token = isset($data['checkout_token']) ? (string) $data['checkout_token'] : null;
        CheckoutGuard::claim($token);

        try {
            return $place();
        } catch (\Throwable $e) {
            CheckoutGuard::release($token);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placeCart(Basket $cart, array $data, ?User $user, Currency $currency, ?string $ip): Order
    {
        $lines = $cart->lines($currency);

        if ($lines->isEmpty()) {
            throw new ShopException(__('Sepetiniz boş.'));
        }

        foreach ($lines as $line) {
            $product = $line['product'];

            if (! $product->sale_mode->allowsCart() || ! $product->price->isPositive()) {
                throw new ShopException(__(':name ürünü yalnızca teklif ile satılmaktadır. Lütfen sepetten çıkarıp teklif isteyin.', ['name' => $product->name]));
            }

            // Adet sınırları sipariş anında da denetlenir (sepete ekleyen her yol denetlemese de).
            if (empty($line['gift']) && $line['quantity'] < $product->min_qty) {
                throw new ShopException(__(':name için en az :min_qty adet sipariş verilebilir.', ['name' => $product->name, 'min_qty' => $product->min_qty]));
            }

            if (empty($line['gift']) && $product->max_qty && $line['quantity'] > $product->max_qty) {
                throw new ShopException(__(':name için en fazla :max_qty adet sipariş verilebilir.', ['name' => $product->name, 'max_qty' => $product->max_qty]));
            }
        }

        $carrier = null;
        if (! empty($data['carrier_id'])) {
            $carrier = Carrier::query()->active()->with('rates')->find($data['carrier_id']);

            if ($carrier === null) {
                throw new ShopException(__('Seçilen kargo firması kullanılamıyor.'));
            }
        }

        // Kampanyalar ödeme bilgisini (e-posta, ödeme yöntemi, il) görerek yeniden hesaplanır.
        $cart->useCheckout(
            $data['email'] ?? $user?->email,
            $data['payment_method'] ?? null,
            $data['shipping']['city'] ?? $data['billing']['city'] ?? null,
        );
        $totals = $cart->totals($currency, $carrier);
        $campaignResult = $cart->campaign();
        $campaignContext = $cart->campaignContext();
        $campaign = $campaignResult?->campaign;
        $gateway = $this->gatewayFor($data['payment_method'] ?? null, $user, $totals['total'], $currency);

        $order = DB::transaction(function () use ($lines, $totals, $data, $user, $currency, $ip, $campaign, $campaignResult, $campaignContext, $gateway) {
            $this->lockAndCheckStock($lines);

            $order = $this->newOrder($data, $user, $currency, $ip);
            $order->fill([
                'campaign_id' => $campaign?->id,
                'campaign_name' => $campaignResult?->name(),
                'carrier_id' => $totals['carrier']?->id,
                'cargo_company' => $totals['carrier']?->name,
                'total_desi' => $totals['desi'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'tax_total' => $totals['tax'],
                'shipping_total' => $totals['shipping'],
                'grand_total' => $totals['total'],
            ])->save();

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'sku' => $line['product']->sku,
                    'name' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_net'],
                    'discount_amount' => $line['discount'],
                    'campaign' => $line['campaign'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['line_tax'],
                    'line_total' => $line['line_gross'],
                ]);
            }

            // Kullanım limiti: kampanya kilitlenip yeniden denetlenir, kullanım yazılır.
            if ($campaignResult !== null && $campaignContext !== null) {
                app(CampaignUsages::class)->claim($campaignResult, $campaignContext, $order);
            }

            $this->applyPaymentAdjustment($order, $gateway, $user, $currency);

            // Müşterinin ekranda gördüğü tutar değiştiyse (fiyat, kur, kampanya, il) sipariş açılmaz.
            CheckoutGuard::confirmTotal($data['expected_total'] ?? null, $order->grand_total, $currency->code);

            $this->afterPlaced($order, $gateway);

            return $order;
        });

        $cart->clear();

        OrderPlacedEvent::dispatch($order);

        return $order;
    }

    /**
     * Onaylanmış tekliften sipariş oluşturur (teklif fiyatları ve para birimiyle).
     *
     * @param  array<string, mixed>  $data
     */
    public function placeFromQuote(Quote $quote, array $data, ?User $user, ?string $ip = null): Order
    {
        return $this->guarded($data, fn (): Order => $this->placeQuote($quote, $data, $user, $ip));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placeQuote(Quote $quote, array $data, ?User $user, ?string $ip): Order
    {
        if (! $quote->canBeOrdered()) {
            throw new ShopException(__('Bu teklif siparişe dönüştürülemez (onaylı değil, süresi dolmuş veya zaten sipariş edilmiş).'));
        }

        $currency = $this->currencies->find($quote->currency_code) ?? $this->currencies->base();
        $gateway = $this->gatewayFor($data['payment_method'] ?? null, $user, $quote->grand_total, $currency);

        $order = DB::transaction(function () use ($quote, $data, $user, $currency, $ip, $gateway) {
            // Çift tıklama / iki sekme: teklif kilitli yeniden okunur, ikinci istek siparişe dönmüş teklifi görür.
            $locked = Quote::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($quote->id);
            $quote->setRawAttributes($locked->getAttributes(), true);

            if (! $quote->canBeOrdered()) {
                throw new ShopException(__('Bu teklif siparişe dönüştürülemez (onaylı değil, süresi dolmuş veya zaten sipariş edilmiş).'));
            }

            $quote->loadMissing('items');

            $order = $this->newOrder($data, $user, $currency, $ip);
            $order->fill([
                'quote_id' => $quote->id,
                'exchange_rate' => $quote->exchange_rate,
                'subtotal' => $quote->subtotal,
                'tax_total' => $quote->tax_total,
                'shipping_total' => $quote->shipping_total,
                'grand_total' => $quote->grand_total,
            ])->save();

            foreach ($quote->items as $item) {
                $line = Money::line($item->unit_price, $item->quantity, $item->tax_rate);

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $line['tax'],
                    'line_total' => $line['gross'],
                ]);
            }

            app(QuoteService::class)->markOrdered($quote, $order);
            $this->applyPaymentAdjustment($order, $gateway, $user, $currency);
            CheckoutGuard::confirmTotal($data['expected_total'] ?? null, $order->grand_total, $currency->code);

            $this->afterPlaced($order, $gateway, __('Teklif :number üzerinden oluşturuldu.', ['number' => $quote->number]));

            return $order;
        });

        OrderPlacedEvent::dispatch($order);

        return $order;
    }

    /**
     * Dış satış kanalından gelen sipariş (pazaryeri vb.). Satış fiyatını kanal
     * belirlemiştir: satırlar KDV dahil birim fiyatla gelir, sepet / kampanya /
     * ödeme farkı hesabı yapılmaz; KDV ürünün oranıyla fiyatın içinden ayrılır.
     * Sipariş verilen hesaba yazılır, stok her sipariş gibi düşülür (kanal zaten
     * sattığı için eksik stok siparişi durdurmaz). Ödeme yöntemi kanalın kendi
     * sürücüsüdür (ödeme sayfasında sunulmaz); siparişin durumunu `start()` belirler.
     *
     * @param  list<array{product: Product, quantity: int, unit_gross: BigNumber|int|string}>  $lines
     * @param  array{customer_name: string, email: string, phone: string, billing: array<string, mixed>, shipping: array<string, mixed>, customer_note?: ?string, cargo_company?: ?string, tracking_number?: ?string, note?: ?string}  $data
     */
    public function placeExternal(array $lines, array $data, Account $account, string $paymentMethod): Order
    {
        if ($lines === []) {
            throw new ShopException(__('Siparişte ürün yok.'));
        }

        $gateway = $this->payments->get($paymentMethod)
            ?? throw new ShopException(__('Ödeme yöntemi bulunamadı: :method.', ['method' => $paymentMethod]));
        $currency = $this->currencies->base();

        $order = DB::transaction(function () use ($lines, $data, $account, $paymentMethod, $gateway, $currency) {
            $order = $this->newOrder([
                ...$data,
                'payment_method' => $paymentMethod,
                'same_address' => false,
            ], null, $currency, null);

            $order->fill([
                'customer_name' => $data['customer_name'],
                'account_id' => $account->id,
                'warehouse_id' => self::warehouseFor($account->id),
                'customer_note' => $data['customer_note'] ?? null,
                'cargo_company' => $data['cargo_company'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
            ])->save();

            $net = [];
            $tax = [];
            $gross = [];

            foreach ($lines as $line) {
                $product = $line['product'];
                $rate = Money::decimal($product->tax_rate);
                $lineGross = Money::times($line['unit_gross'], $line['quantity']);
                // KDV fiyatın içinden: brüt / (1 + oran); kuruş farkı KDV'de kalır, brüt aynen korunur.
                $lineNet = Money::div($lineGross, Money::add(1, Money::div($rate, 100, 6)));
                $lineTax = Money::sub($lineGross, $lineNet);

                $order->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::div($lineNet, $line['quantity'], 4),
                    'tax_rate' => $rate,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineGross,
                ]);

                $net[] = $lineNet;
                $tax[] = $lineTax;
                $gross[] = $lineGross;
            }

            $order->fill([
                'subtotal' => Money::add(...$net),
                'tax_total' => Money::add(...$tax),
                'grand_total' => Money::add(...$gross),
            ])->save();

            $this->afterPlaced($order, $gateway, $data['note'] ?? null);

            return $order;
        });

        OrderPlacedEvent::dispatch($order);

        return $order;
    }

    /**
     * Ödeme alındı (ödeme sağlayıcısı bildirimi, yöneticinin havale onayı ya da
     * onaylanan havale bildirimi). Ödeme kaydı verilmezse siparişin yöntemiyle,
     * sipariş tutarında bir kayıt yazılır; tüm ödemeler `payments` tablosunda toplanır.
     */
    public function markPaid(Order $order, ?User $causer = null, ?string $note = null, ?Payment $payment = null): void
    {
        /** @var array{kind: 'paid'|'unapplied', payment: Payment, reason?: string}|null $result */
        $result = DB::transaction(function () use ($order, $causer, $note, $payment): ?array {
            // Eşzamanlı iki "ödendi" (sağlayıcı bildirimi + dönüş, çift onay) bir kez işlensin.
            $this->lockOrder($order);

            // Siparişe uygulanamayan ödeme: sipariş başka ödemeyle zaten ödenmiş ya da
            // iptal edilmiş; sağlayıcıdan gelen (yönetici işlemi olmayan) gecikmiş ödeme
            // başarısız (zaman aşımına uğramış) siparişi de yeniden açmaz. Para kaybolmaz:
            // ödeme "ödendi" olur, cariye alacak yazılır, yöneticiye bildirilir.
            $unapplied = match (true) {
                $order->payment_status === PaymentStatus::Paid => __('sipariş zaten ödenmiş'),
                $order->status === OrderStatus::Cancelled => __('sipariş iptal edilmiş'),
                $order->status === OrderStatus::Failed && $causer === null && $payment !== null => __('sipariş ödeme zaman aşımıyla kapanmış'),
                default => null,
            };

            if ($unapplied !== null) {
                if ($payment === null || $payment->status === PaymentStatus::Paid) {
                    return null;
                }

                $this->recordUnappliedPayment($order, $payment, $unapplied);

                return ['kind' => 'unapplied', 'payment' => $payment, 'reason' => $unapplied];
            }

            // Başarısız siparişin teklifi serbest kalmıştı: yönetici ödendi yapınca yeniden bağlanır
            // (teklif bu arada başka siparişe dönüştüyse işlem durur).
            if ($order->status === OrderStatus::Failed) {
                QuoteService::orderReopened($order);
            }

            $payment ??= $order->payments()->where('status', PaymentStatus::Pending)->latest()->first();

            if ($payment === null) {
                $payment = $order->payments()->create([
                    'account_id' => $order->account_id,
                    'method' => $order->payment_method,
                    'provider' => 'manual',
                    'merchant_oid' => Payment::newReference(),
                    'amount' => $order->grand_total,
                    'currency_code' => $order->currency_code,
                    'status' => PaymentStatus::Pending,
                    'user_id' => $causer?->id,
                ]);
            }

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => $payment->paid_at ?? now(),
                'account_id' => $payment->account_id ?? $order->account_id,
                'order_id' => $order->id,
            ])->save();

            $order->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'status' => in_array($order->status, [OrderStatus::Pending, OrderStatus::Failed], true)
                    ? OrderStatus::Processing
                    : $order->status,
            ])->save();

            $this->stock->deductForOrder($order);

            // Cari: kesinleşen siparişin borcu, ardından ödemenin alacağı.
            $this->ledger->syncOrder($order, $causer);
            $this->ledger->recordPayment($payment);

            $order->histories()->create([
                'status' => $order->status,
                'note' => $note ?? __('Ödeme alındı.'),
                'notify_customer' => true,
                'user_id' => $causer?->id,
            ]);

            return ['kind' => 'paid', 'payment' => $payment];
        });

        if ($result === null) {
            return;
        }

        if ($result['kind'] === 'paid') {
            OrderPaidEvent::dispatch($order->fresh(), $causer);

            return;
        }

        UnappliedPaymentReceivedEvent::dispatch($order->fresh(), $result['payment']->fresh(), $result['reason'] ?? '');
    }

    /**
     * Siparişe uygulanamayan ödeme (kilitli sipariş transaction'ı içinde): ödeme
     * "ödendi" olur ve cariye alacak yazılır (hesabı varsa); sipariş durumu ve
     * stok değişmez. Yönetici siparişi yeniden açar ya da ödemeyi iade eder.
     */
    private function recordUnappliedPayment(Order $order, Payment $payment, string $reason): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => $payment->paid_at ?? now(),
            'account_id' => $payment->account_id ?? $order->account_id,
            'order_id' => $order->id,
        ])->save();

        $this->ledger->recordPayment($payment);

        $order->histories()->create([
            'status' => $order->status,
            'note' => Str::limit(__(':amount :currency tutarında ödeme alındı ancak siparişe uygulanmadı (:reason). Tutar :target; siparişi yeniden açın ya da ödemeyi iade edin.', [
                'amount' => Money::number($payment->amount),
                'currency' => $payment->currency_code,
                'reason' => $reason,
                'target' => $order->account_id !== null ? __('cariye alacak olarak işlendi') : __('müşteriye iade edilmeli'),
            ]), 250),
        ]);
    }

    /**
     * Ödeme başarısız (sağlayıcı bildirimi ya da zaman aşımı). Yalnız ödeme
     * bekleyen sipariş "Ödeme Başarısız" olur ve stoğu geri eklenir; sipariş bu
     * arada ödenmiş ya da yönetici tarafından ilerletilmişse yalnız bekleyen
     * ödeme kayıtları başarısız sayılır.
     */
    public function markPaymentFailed(Order $order, string $reason): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        DB::transaction(function () use ($order, $reason) {
            // Aynı anda gelen "ödendi" bildirimi ya da yönetici işlemi kilit altında görülür.
            $this->lockOrder($order);

            if ($order->payment_status === PaymentStatus::Paid) {
                return;
            }

            $order->payments()->where('status', PaymentStatus::Pending)->update([
                'status' => PaymentStatus::Failed,
                'failed_reason' => Str::limit($reason, 250),
            ]);

            if ($order->status !== OrderStatus::Pending) {
                return;
            }

            // Ödemesi başarısız sipariş stok tutmaz; yönetici ödendi işaretlerse markPaid tekrar düşer.
            $this->stock->restoreForOrder($order);

            $order->forceFill([
                'payment_status' => PaymentStatus::Failed,
                'status' => OrderStatus::Failed,
            ])->save();

            $this->releaseQuoteOfFailedOrder($order);

            $order->histories()->create([
                'status' => OrderStatus::Failed,
                'note' => Str::limit(__('Ödeme başarısız: :reason', ['reason' => $reason]), 250),
            ]);
        }, 3);
    }

    /** Ödemesi alınamayan siparişin teklifi müşteri tarafından yeniden ödenebilir. */
    private function releaseQuoteOfFailedOrder(Order $order): void
    {
        QuoteService::orderCancelled($order, __('Sipariş :number ödemesi alınamadı; teklif yeniden ödenebilir.', ['number' => $order->number]));
    }

    /**
     * Stok rezervasyonunun süresi doldu (`stock_reservation_days`): stok sipariş
     * anında düşüldüğü için ödemesi gelmeyen (havale) ya da onayı verilmeyen
     * (vadeli, limit aşımı) sipariş stoğu süresiz tutmasın.
     *
     * - Ödeme bekleyen → "Ödeme Başarısız" (kart zaman aşımıyla aynı sonuç).
     * - Onay bekleyen  → "İptal Edildi".
     *
     * İncelenmeyi bekleyen havale bildirimi varsa dokunulmaz. Kilit altında güncel
     * durum yeniden denetlenir; sipariş bu arada ilerlediyse false döner.
     */
    public function releaseReservation(Order $order, int $days): bool
    {
        $previous = $order->status;

        $released = DB::transaction(function () use ($order, $days, &$previous): bool {
            $this->lockOrder($order);

            $previous = $order->status;
            $awaitingPayment = $order->status === OrderStatus::Pending && $order->payment_status === PaymentStatus::Pending;

            if (! $awaitingPayment && $order->status !== OrderStatus::AwaitingApproval) {
                return false;
            }

            $notified = PaymentNotification::query()->where('order_id', $order->id)
                ->where('status', PaymentNotificationStatus::Pending)->exists();

            if ($notified) {
                return false;
            }

            $note = $awaitingPayment
                ? __('Ödeme :days gün içinde gelmediği için sipariş kapatıldı; ayrılan stok serbest bırakıldı.', ['days' => $days])
                : __('Sipariş :days gün içinde onaylanmadığı için iptal edildi; ayrılan stok serbest bırakıldı.', ['days' => $days]);

            $this->stock->restoreForOrder($order);

            if ($awaitingPayment) {
                $order->payments()->where('status', PaymentStatus::Pending)->update([
                    'status' => PaymentStatus::Failed,
                    'failed_reason' => Str::limit($note, 250),
                ]);

                $order->forceFill(['status' => OrderStatus::Failed, 'payment_status' => PaymentStatus::Failed])->save();
                $this->releaseQuoteOfFailedOrder($order);
            } else {
                // Onaylanmamış vadeli sipariş cariye yazılmamıştır; ters kayıt gerekmez.
                $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()])->save();
                QuoteService::orderCancelled($order);
            }

            $order->histories()->create([
                'status' => $order->status,
                'note' => Str::limit($note, 250),
                'notify_customer' => true,
            ]);

            return true;
        });

        if ($released) {
            StockReservationExpiredEvent::dispatch($order->fresh(), $previous, $days);
        }

        return $released;
    }

    /**
     * Yönetici durum değişikliği. Geçiş kuralı (OrderStatus::canTransitionTo)
     * kilit altında, güncel durum üzerinden denetlenir; aynı durum yeniden
     * seçilirse yalnız not / kargo bilgisi güncellenir.
     *
     * @param  array{note?: ?string, notify?: bool, cargo_company?: ?string, carrier_id?: ?string, tracking_number?: ?string}  $data
     */
    public function changeStatus(Order $order, OrderStatus $status, array $data, User $causer): void
    {
        $previous = $order->status;

        DB::transaction(function () use ($order, $status, $data, $causer, &$previous) {
            // Stok düşüm / iade bayrağı güncel okunsun (ödeme bildirimiyle çakışmasın).
            $this->lockOrder($order);

            $previous = $order->status;
            $changed = $previous !== $status;

            if ($changed) {
                $this->guardTransition($order, $previous, $status);
            }

            $fill = ['status' => $status];

            if (array_key_exists('cargo_company', $data)) {
                $fill['cargo_company'] = $data['cargo_company'];
            }

            if (array_key_exists('carrier_id', $data)) {
                $fill['carrier_id'] = $data['carrier_id'];
            }

            if (array_key_exists('tracking_number', $data)) {
                $fill['tracking_number'] = $data['tracking_number'];
            }

            if ($status === OrderStatus::Shipped && $order->shipped_at === null) {
                $fill['shipped_at'] = now();
            }

            if ($changed && $status === OrderStatus::Cancelled) {
                $fill['cancelled_at'] = now();
                $this->stock->restoreForOrder($order, $causer);
            }

            if ($changed && $previous === OrderStatus::Cancelled) {
                // Yeniden açılan sipariş: iptal izi temizlenir, teklifi yeniden bağlanır.
                $fill['cancelled_at'] = null;
                QuoteService::orderReopened($order);
            }

            if ($changed && $previous === OrderStatus::AwaitingApproval && $status === OrderStatus::Processing) {
                $fill['due_date'] = $this->approveOnAccount($order);
            }

            // İptal edilmiş siparişi yeniden açarken stok yetmiyorsa işlem durur (ön siparişe açık ürünler hariç).
            if ($status->holdsStock() && ! $order->stock_deducted) {
                $this->stock->deductForOrder($order, allowShortage: false);
            }

            $order->forceFill($fill)->save();

            // Cari: Hazırlanıyor / Kargoda / Tamamlandı → borç; İptal → borcun ters kaydı.
            $this->ledger->syncOrder($order, $causer);

            // Ödenmemiş sipariş iptal edildi: teklifi yeniden ödenebilir olur (aynı transaction).
            if ($changed && $status === OrderStatus::Cancelled && $order->payment_status !== PaymentStatus::Paid) {
                QuoteService::orderCancelled($order);
            }

            $order->histories()->create([
                'status' => $status,
                'note' => $data['note'] ?? null,
                'notify_customer' => (bool) ($data['notify'] ?? false),
                'user_id' => $causer->id,
            ]);

            // Ödemesi alınmış sipariş iptal edildi: borç ters kayıtla kapandı, ödeme duruyor.
            // Hesaplı müşteride tutar cari alacak olarak kalır; misafirde iade edilmelidir.
            if ($changed && $status === OrderStatus::Cancelled && in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Partial], true)) {
                $order->histories()->create([
                    'status' => $status,
                    'note' => $order->account_id !== null
                        ? __('Ödemesi alınmış sipariş iptal edildi; ödeme müşterinin carisinde alacak olarak kaldı.')
                        : __('Ödemesi alınmış sipariş iptal edildi; ödeme müşteriye iade edilmeli (Ödeme durumu → İade Edildi).'),
                    'user_id' => $causer->id,
                ]);
            }
        });

        OrderStatusChangedEvent::dispatch($order->fresh(), $previous, $causer, (bool) ($data['notify'] ?? false), $data['note'] ?? null);
    }

    /**
     * Elle geçişin kuralları (kilitli sipariş üzerinde).
     */
    private function guardTransition(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        // Kargoya verilmiş sipariş önce "Hazırlanıyor"a çekilerek de iptal edilemez (stok ve cari iki kez işlenirdi).
        $dispatchedBefore = $to === OrderStatus::Cancelled && ($from->isDispatched() || $order->shipped_at !== null);

        if ($dispatchedBefore || ! $from->canTransitionTo($to)) {
            throw new ShopException($dispatchedBefore
                ? __('Kargoya verilmiş / tamamlanmış sipariş iptal edilemez; ürünlerin geri dönüşü için iade talebi oluşturun.')
                : __(':from durumundaki sipariş :to yapılamaz.', ['from' => $from->label(), 'to' => $to->label()]));
        }
    }

    /**
     * Onay bekleyen vadeli siparişin onayı: limit / vade kararı hesap kilitliyken
     * yeniden alınır ("engelle" ise onaylanamaz); vade tarihini döndürür.
     */
    private function approveOnAccount(Order $order): CarbonImmutable
    {
        $account = Account::query()->with('dealerGroup')->lockForUpdate()->find($order->account_id);

        if ($account === null || ! CreditTerms::for($account)->isCredit()) {
            throw new ShopException(__('Müşterinin hesabı artık vadeli çalışmıyor; sipariş vadeli onaylanamaz.'));
        }

        $decision = app(CreditService::class)->decide($account, $this->ledger->orderAmount($order, $order->grand_total), $order, locking: true);

        if ($decision['action'] === LimitAction::Block) {
            throw new ShopException(__('Vadeli sipariş onaylanamıyor: :reason.', ['reason' => $decision['reason']]));
        }

        return CreditTerms::for($account)->dueDate();
    }

    /**
     * Fatura bilgisi (yalnız tamamlanan sipariş): no girilir / değiştirilir; null
     * kaldırır. Panelin Fatura kartı ve muhasebe eklentileri (kestikleri e-faturanın
     * numarasını yazar) bunu kullanır. Geçmişe not düşer, OrderInvoicedEvent yayılır.
     */
    public function recordInvoice(Order $order, ?string $number, ?User $causer = null): void
    {
        $number = $number === null || trim($number) === '' ? null : trim($number);

        if ($order->status !== OrderStatus::Completed) {
            throw new ShopException(__('Fatura bilgisi yalnızca tamamlanan siparişlere girilebilir.'));
        }

        $order->forceFill([
            'invoice_number' => $number,
            'invoiced_at' => $number === null ? null : ($order->invoiced_at ?? now()),
        ])->save();

        OrderHistory::query()->create([
            'order_id' => $order->id,
            'note' => $number === null ? __('Fatura bilgisi kaldırıldı.') : __('Fatura no :number ile faturalandırıldı.', ['number' => $number]),
            'user_id' => $causer?->id,
        ]);

        event(new OrderInvoicedEvent($order, $number, $causer));
    }

    /**
     * Yöneticinin ödeme durumu değişikliği. Geçişler: Ödendi (markPaid);
     * Ödendi / Kısmen → İade Edildi ya da Bekliyor (ödemeler ters kayıtla kapanır);
     * Bekliyor → Başarısız yalnız ödeme bekleyen siparişte (markPaymentFailed).
     */
    public function updatePaymentStatus(Order $order, PaymentStatus $status, User $causer): void
    {
        if ($status === PaymentStatus::Paid) {
            $this->markPaid($order, $causer, __('Ödeme yönetici tarafından onaylandı.'));

            return;
        }

        if ($status === PaymentStatus::Failed) {
            DB::transaction(function () use ($order) {
                $this->lockOrder($order);

                if ($order->payment_status !== PaymentStatus::Pending || $order->status !== OrderStatus::Pending) {
                    throw new ShopException(__('Yalnız ödemesi beklenen sipariş "Başarısız" yapılabilir.'));
                }
            });

            $this->markPaymentFailed($order, __('Yönetici ödemeyi başarısız işaretledi.'));

            return;
        }

        DB::transaction(function () use ($order, $status, $causer) {
            // Ödeme bildirimi / sağlayıcı ile yarışmasın: güncel ödeme durumu kilit altında okunur.
            $this->lockOrder($order);
            $current = $order->payment_status;

            if ($current === $status) {
                return;
            }

            $settled = in_array($current, [PaymentStatus::Paid, PaymentStatus::Partial], true);

            if ($status === PaymentStatus::Refunded && ! $settled) {
                throw new ShopException(__('Ödemesi alınmamış sipariş "İade Edildi" yapılamaz.'));
            }

            // Sipariş "Başarısız" kalırken ödeme "Bekliyor" olursa iki alan çelişirdi.
            if ($status === PaymentStatus::Pending && $order->status === OrderStatus::Failed) {
                throw new ShopException(__('Başarısız siparişin ödemesi "Bekliyor" yapılamaz; "Ödendi" işaretleyin ya da siparişi iptal edin.'));
            }

            if ($status === PaymentStatus::Pending && ! in_array($current, [PaymentStatus::Paid, PaymentStatus::Partial, PaymentStatus::Failed], true)) {
                throw new ShopException(__('Ödeme durumu bu duruma geri alınamaz.'));
            }

            // Ödendi / Kısmen'den geri alınan ya da iade edilen siparişin ödemeleri ters kayıtla kapanır.
            if ($settled) {
                // Kısmi kart iadesi cariye ayrıca işlendi; tüm tahsilat ters çevrilirse fazla borç yazılırdı.
                $hasCardRefund = $order->payments()->where('status', PaymentStatus::Paid)
                    ->whereHas('refunds', fn ($q) => $q->where('status', '!=', RefundStatus::Failed->value))
                    ->exists();

                if ($hasCardRefund) {
                    throw new ShopException(__('Bu ödemede kart iadesi var; kalan tutarı Ödemeler kartından iade edin.'));
                }

                $order->payments()->where('status', PaymentStatus::Paid)->get()
                    ->each(function (Payment $p) use ($status, $causer): void {
                        $p->forceFill([
                            'status' => $status === PaymentStatus::Refunded ? PaymentStatus::Refunded : PaymentStatus::Cancelled,
                        ])->save();
                        $this->ledger->reversePayment($p, $causer);
                    });
            }

            $order->refresh();
            $order->forceFill([
                'payment_status' => $status,
                'paid_at' => $status === PaymentStatus::Pending ? null : $order->paid_at,
            ])->save();

            $order->histories()->create([
                'status' => $order->status,
                'note' => __('Ödeme durumu: :status', ['status' => $status->label()]),
                'user_id' => $causer->id,
            ]);
        });
    }

    /**
     * Ödeme yöntemine özel fark (ör. havale indirimi): KDV dahil genel toplam
     * üzerinden oran; siparişe ayrı satır olarak yazılır ve genel toplama eklenir.
     */
    private function applyPaymentAdjustment(Order $order, PaymentGateway $gateway, ?User $user, Currency $currency): void
    {
        $context = PaymentContext::for($user, $order->grand_total, $currency);
        $rate = $gateway->adjustmentRate($context);

        if ($rate->isZero()) {
            return;
        }

        $adjustment = Money::percent($order->grand_total, $rate);

        $order->forceFill([
            'payment_adjustment' => $adjustment,
            'payment_adjustment_label' => $gateway->adjustmentLabel($context),
            'grand_total' => Money::add($order->grand_total, $adjustment),
        ])->save();
    }

    /**
     * Yöntemi bağlamda doğrular (sürücü + izinli yöntemler + en düşük tutar).
     */
    public function gatewayFor(?string $key, ?User $user, BigNumber|int|string $total, ?Currency $currency): PaymentGateway
    {
        $gateway = $this->payments->get($key);

        if ($gateway === null || ! array_key_exists($gateway->key(), $this->payments->available(PaymentContext::for($user, $total, $currency)))) {
            throw new ShopException(__('Seçilen ödeme yöntemi şu anda kullanılamıyor.'));
        }

        return $gateway;
    }

    /**
     * Günlük belge numarası (ör. S2611050001). Sayaç `document_sequences`
     * satırında kilitli artırılır; eşzamanlı iki istek aynı numarayı alamaz.
     * Sayacın kilidi çağıranın transaction'ı bitene kadar tutulur.
     */
    public static function nextNumber(string $prefix, string $table): string
    {
        $stamp = now()->format('ymd');
        $key = $table.':'.$prefix.$stamp;

        return DB::transaction(function () use ($prefix, $stamp, $table, $key): string {
            // Günün ilk numarası: sayaç, tabloda o gün için zaten verilmiş numaralardan başlar.
            DB::table('document_sequences')->insertOrIgnore([
                'key' => $key,
                'value' => DB::table($table)->where('number', 'like', $prefix.$stamp.'%')->count(),
            ]);

            $count = (int) DB::table('document_sequences')->where('key', $key)->lockForUpdate()->value('value');

            do {
                $count++;
                $number = $prefix.$stamp.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            } while (DB::table($table)->where('number', $number)->exists());

            DB::table('document_sequences')->where('key', $key)->update(['value' => $count]);

            return $number;
        });
    }

    /**
     * Transaction içinde: siparişi kilitleyip güncel haliyle yeniden okur
     * (verilen modelin öznitelikleri de güncellenir).
     */
    private function lockOrder(Order $order): void
    {
        $locked = Order::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
        $order->setRawAttributes($locked->getAttributes(), true);
    }

    /**
     * Siparişin öncelikli deposu: bayinin bölge deposu, yoksa varsayılan depo.
     * Stok önce buradan, yetmezse diğer satılabilir depolardan düşülür.
     */
    public static function warehouseFor(?string $accountId): ?string
    {
        $region = $accountId !== null
            ? Account::query()->with('warehouse')->find($accountId)?->regionWarehouse()
            : null;

        return $region?->id ?? Warehouse::default()?->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function newOrder(array $data, ?User $user, Currency $currency, ?string $ip): Order
    {
        $billing = $data['billing'];
        $shipping = ($data['same_address'] ?? true) ? $billing : ($data['shipping'] ?? $billing);

        $order = new Order([
            'number' => self::nextNumber('S', 'orders'),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => (string) $data['payment_method'],
            'user_id' => $user?->id,
            'account_id' => $user?->account_id,
            'warehouse_id' => self::warehouseFor($user?->account_id),
            'customer_name' => $billing['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'billing_address' => $billing,
            'shipping_address' => $shipping,
            'currency_code' => $currency->code,
            'exchange_rate' => $currency->exchange_rate,
            'base_exchange_rate' => Money::baseRate(),
            'customer_note' => $data['customer_note'] ?? null,
            'ip_address' => $ip,
        ]);

        $order->save();

        return $order;
    }

    /**
     * Sipariş kaydının ardından (aynı transaction): geçmiş, stok ayırma ve ödeme
     * sürücüsünün başlangıcı (yönlendirme isteyebilir; vadeli siparişin durumunu belirler).
     */
    private function afterPlaced(Order $order, PaymentGateway $gateway, ?string $extraNote = null): void
    {
        $order->histories()->create([
            'status' => OrderStatus::Pending,
            'note' => __('Sipariş oluşturuldu (:method).', ['method' => PaymentMethods::label($order->payment_method)]),
        ]);

        if ($extraNote !== null) {
            $order->histories()->create(['status' => OrderStatus::Pending, 'note' => $extraNote]);
        }

        // Stok sipariş anında ayrılır (düşülür); iptalde ya da ödeme başarısız olursa geri eklenir.
        $this->stock->deductForOrder($order);

        $order->paymentStart = $gateway->start($order);
    }

    /**
     * Sepetteki ürün satırlarını kilitleyip stoğu güncel değerle doğrular. Aynı
     * transaction içinde stok düşüldüğü için eşzamanlı siparişler son adedi
     * birlikte satamaz (ikinci sipariş kilidi bekler, sonra güncel stoğu görür).
     *
     * @param  Collection<int, array{product: Product, quantity: int}>  $lines
     */
    private function lockAndCheckStock(Collection $lines): void
    {
        $wanted = [];

        foreach ($lines as $line) {
            $id = $line['product']->id;
            $wanted[$id] = ($wanted[$id] ?? 0) + (int) $line['quantity'];
        }

        $products = Product::query()
            ->whereKey(array_keys($wanted))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($products as $product) {
            if (! $product->hasStockFor($wanted[$product->id])) {
                throw new ShopException(__(':name için yeterli stok yok (mevcut: :stock).', ['name' => $product->name, 'stock' => $product->stock]));
            }
        }
    }
}
