<?php

declare(strict_types=1);

namespace App\Support\Payments;

/**
 * Sipariş oluşturulduktan sonra sürücünün isteği: müşteri doğrudan sipariş
 * sayfasına mı gider, yoksa bir ödeme sayfasına mı yönlendirilir.
 */
final readonly class PaymentStart
{
    private function __construct(public ?string $redirectUrl, public ?string $message = null) {}

    public static function none(?string $message = null): self
    {
        return new self(null, $message);
    }

    public static function redirect(string $url): self
    {
        return new self($url);
    }

    public function redirects(): bool
    {
        return $this->redirectUrl !== null;
    }
}
