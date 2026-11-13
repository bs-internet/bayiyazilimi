<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Shop\OrderService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;

/**
 * REST API'den sipariş: dış sistem (ERP, bayinin kendi sitesi, MCP) bir cari
 * hesap adına sipariş açar. Satırlar stok koduyla gelir; birim fiyat (KDV dahil)
 * gönderilmezse bayinin fiyatı uygulanır (ürün indirimi, yoksa bayi / grup
 * indirimi; ana para birimine çevrilir, KDV eklenir). Sipariş OrderService::placeExternal
 * ile açılır: stok düşer, durumu ödeme yöntemi belirler (vadeli → kredi kuralları).
 */
final class ApiOrderService
{
    public const PAYMENT_METHODS = ['on_account', 'bank_transfer'];

    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * @param  array{lines: list<array{sku: string, quantity: int, unit_price?: ?string}>, payment_method: string, shipping?: ?array<string, mixed>, billing?: ?array<string, mixed>, email?: ?string, phone?: ?string, customer_note?: ?string, note?: ?string}  $input
     */
    public function place(Account $account, array $input, User $causer): Order
    {
        $products = Product::query()->with('currency')->whereIn('sku', array_column($input['lines'], 'sku'))->get()->keyBy('sku');
        $account->loadMissing(['dealerGroup', 'address.city', 'address.district']);
        $lines = [];
        $errors = [];

        foreach ($input['lines'] as $i => $line) {
            $product = $products->get($line['sku']);

            if ($product === null) {
                $errors[] = __(':sku: ürün bulunamadı.', ['sku' => $line['sku']]);

                continue;
            }

            if (! $product->isActive()) {
                $errors[] = __(':sku: ürün satışta değil.', ['sku' => $line['sku']]);

                continue;
            }

            $unit = isset($line['unit_price']) && $line['unit_price'] !== null
                ? Money::round((string) $line['unit_price'], 4)
                : self::dealerGross($product, $account);

            if (! $unit->isPositive()) {
                $errors[] = __(':sku: fiyatı olmayan (teklifle satılan) ürün için birim fiyat gönderin.', ['sku' => $line['sku']]);

                continue;
            }

            $lines[] = ['product' => $product, 'quantity' => (int) $line['quantity'], 'unit_gross' => $unit];
        }

        if ($errors !== []) {
            throw new ShopException(implode(' ', $errors));
        }

        $billing = self::billing($account, $input['billing'] ?? null);
        $shipping = isset($input['shipping']) ? [...Arr::only($billing, ['name']), ...$input['shipping']] : $billing;

        $order = $this->orders->placeExternal($lines, [
            'customer_name' => $billing['name'],
            'email' => (string) ($input['email'] ?? $account->invoice_email ?? $causer->email),
            'phone' => (string) ($input['phone'] ?? $shipping['phone'] ?? ''),
            'billing' => $billing,
            'shipping' => $shipping,
            'customer_note' => $input['customer_note'] ?? null,
            'note' => __('API ile oluşturuldu (:user).', ['user' => $causer->name]).(filled($input['note'] ?? null) ? ' '.$input['note'] : ''),
        ], $account, $input['payment_method']);

        return $order;
    }

    /** Bayinin KDV dahil birim fiyatı (ana para biriminde). */
    public static function dealerGross(Product $product, Account $account): BigDecimal
    {
        $price = $product->price;

        if ($price->isPositive() && $product->discountRate() === 0) {
            $rate = $account->dealerDiscountRate();

            if ($rate->isPositive()) {
                $price = Money::percent($price, Money::sub(100, $rate), 4);
            }
        }

        return Money::withRate(Money::toBase($price, $product->currency), $product->tax_rate);
    }

    /**
     * Fatura adresi: gönderilmezse cari hesabın ünvanı, vergi bilgisi ve adresi.
     *
     * @param  array<string, mixed>|null  $given
     * @return array<string, mixed>
     */
    private static function billing(Account $account, ?array $given): array
    {
        $address = $account->address;

        $default = array_filter([
            'type' => $account->tax_number ? 'corporate' : 'individual',
            'name' => (string) ($account->title ?? $account->code),
            'company' => $account->tax_number ? $account->title : null,
            'tax_office' => $account->tax_office,
            'tax_number' => $account->tax_number,
            'identity_number' => $account->identity_number,
            'address' => $address?->address ?? '-',
            'city' => $address?->city?->name,
            'district' => $address?->district?->name,
            'postal_code' => $address?->postal_code,
        ], fn ($v) => $v !== null && $v !== '');

        return $given === null ? $default : [...$default, ...array_filter($given, fn ($v) => $v !== null)];
    }
}
