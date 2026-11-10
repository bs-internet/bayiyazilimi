<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;
use Brick\Math\BigDecimal;

/**
 * Bir ödeme yöntemi (sürücü). Çekirdekte havale ve cari hesaba (vadeli);
 * kart sağlayıcıları modüllerden `shop.payment.methods.register` hook'u ile
 * eklenir.
 */
interface PaymentGateway
{
    /** Registry anahtarı; `orders.payment_method` / `payments.method` bunu tutar (en fazla 24 karakter). */
    public function key(): string;

    public function label(): string;

    /** Ödeme ekranında yöntemin altında gösterilen kısa açıklama (bağlama göre, ör. kullanılabilir limit). */
    public function description(PaymentContext $context): ?string;

    /** Ödeme ekranındaki ikon anahtarı (ör. `landmark`, `creditCard`, `wallet`). */
    public function icon(): string;

    /** Bu müşteri, tutar ve para birimi için sunulur mu (yöntem kısıtlama ve en düşük tutar registry'de). */
    public function available(PaymentContext $context): bool;

    /**
     * Yönteme özel fark oranı (%), siparişin KDV dahil toplamı (kargo dahil) üzerinden:
     * negatif = indirim (ör. havale indirimi −3), pozitif = ek ücret. Yoksa 0.
     */
    public function adjustmentRate(PaymentContext $context): BigDecimal;

    /** Fark satırının etiketi (ör. "Havale indirimi (%3)"); fark yoksa null. */
    public function adjustmentLabel(PaymentContext $context): ?string;

    /**
     * Sipariş oluşturulduğu transaction içinde çağrılır. Ödeme kaydı açabilir,
     * siparişin durumunu belirleyebilir (vadeli) ya da yönlendirme isteyebilir.
     * HTTP çağrısı yapmamalı: sağlayıcıya gidilecekse kendi ödeme sayfasına yönlendirsin.
     */
    public function start(Order $order): PaymentStart;

    /** Bekleyen ödemenin zaman aşımı (dakika); dolunca sipariş "Ödeme Başarısız" olur. Null: süresiz (havale). */
    public function pendingTimeout(): ?int;
}
