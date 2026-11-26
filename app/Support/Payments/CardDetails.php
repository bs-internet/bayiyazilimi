<?php

declare(strict_types=1);

namespace App\Support\Payments;

/**
 * Müşterinin kart ekranında girdiği kart bilgisi. Yalnız istek süresince bellekte
 * durur: veritabanına, günlüğe, oturuma yazılmaz (doğrulama hatasında da forma geri
 * doldurulmaz, bkz. bootstrap/app.php `dontFlash`). Sürücü sağlayıcıya iletir.
 */
final readonly class CardDetails
{
    public function __construct(
        public string $holder,
        #[\SensitiveParameter] public string $number,
        public int $expiryMonth,
        public int $expiryYear,
        #[\SensitiveParameter] public string $cvv,
        public int $installment = 1,
    ) {}

    /** Kartın ilk 6 hanesi (taksit / banka sorgusu için). */
    public function bin(): string
    {
        return substr($this->number, 0, 6);
    }

    /** Ekranda / kayıtta gösterilebilir hali: 4543 60** **** 1234. */
    public function masked(): string
    {
        $number = substr($this->number, 0, 6).str_repeat('*', max(0, strlen($this->number) - 10)).substr($this->number, -4);

        return trim(chunk_split($number, 4, ' '));
    }

    /** Dökümde (dd, günlük) kart numarası ve CVV görünmez. */
    public function __debugInfo(): array
    {
        return ['holder' => $this->holder, 'number' => $this->masked(), 'expiry' => sprintf('%02d/%d', $this->expiryMonth, $this->expiryYear), 'installment' => $this->installment];
    }

    /** Luhn denetimi (kart numarasında yazım hatası). */
    public static function luhn(string $number): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;
                $digit -= $digit > 9 ? 9 : 0;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $number !== '' && $sum % 10 === 0;
    }
}
