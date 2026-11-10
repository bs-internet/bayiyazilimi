<?php

declare(strict_types=1);

namespace App\Support\Payments;

/**
 * Ödeme yöntemleri (sürücüler). Çekirdek sürücüler ShopServiceProvider'da,
 * modüllerinkiler `shop.payment.methods.register` hook'unda eklenir:
 *
 *     hooks()->action('shop.payment.methods.register', function (PaymentRegistry $payments) {
 *         $payments->add(new PaytrGateway);
 *     });
 *
 * Hook ilk erişimde bir kez tetiklenir.
 */
final class PaymentRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    private bool $booted = false;

    public function add(PaymentGateway $gateway): self
    {
        $key = $gateway->key();

        if (strlen($key) > 24) {
            throw new \InvalidArgumentException("Ödeme yöntemi anahtarı en fazla 24 karakter olabilir: {$key}");
        }

        $this->gateways[$key] = $gateway;

        return $this;
    }

    public function get(?string $key): ?PaymentGateway
    {
        $this->boot();

        return $key !== null ? ($this->gateways[$key] ?? null) : null;
    }

    public function has(?string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Ödemesi sağlayıcının kendi sayfasında tamamlanan (ve sonucu geri bildirimle
     * gelen) yöntem mi? Kart sürücüleri böyledir; havale ve cari değildir.
     */
    public static function paysOnProviderPage(?string $key): bool
    {
        return app(self::class)->get($key) instanceof HandlesCallbacks;
    }

    /**
     * @return array<string, PaymentGateway>
     */
    public function all(): array
    {
        $this->boot();

        return $this->gateways;
    }

    /**
     * Bağlamda sunulabilen yöntemler: sürücünün kendi koşulu + müşterinin izinli
     * yöntemleri + yönteme göre en düşük sipariş tutarı (PaymentRules).
     *
     * @return array<string, PaymentGateway>
     */
    public function available(PaymentContext $context): array
    {
        return array_filter(
            $this->all(),
            fn (PaymentGateway $g) => PaymentRules::allows($g, $context) && $g->available($context),
        );
    }

    /**
     * @return array<string, AcceptsBalancePayments&PaymentGateway>
     */
    public function balanceGateways(PaymentContext $context): array
    {
        return array_filter(
            $this->all(),
            fn (PaymentGateway $g) => $g instanceof AcceptsBalancePayments && $g->available($context),
        );
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        hooks()->do('shop.payment.methods.register', payments: $this);
    }
}
