<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class ActivitySubjectLabels
{
    public static function for(?string $subjectType): ?string
    {
        if ($subjectType === null || $subjectType === '') {
            return null;
        }

        $className = class_basename($subjectType);

        return match ($className) {
            'Account' => __('Hesap'),
            'City' => __('İl'),
            'Campaign' => __('Kampanya'),
            'Cheque' => __('Çek / Senet'),
            'Country' => __('Ülke'),
            'Currency' => __('Para Birimi'),
            'DealerGroup' => __('Bayi Grubu'),
            'District' => __('İlçe'),
            'Language' => __('Dil'),
            'Media' => __('Medya'),
            'Payment' => __('Ödeme'),
            'PaymentNotification' => __('Havale Bildirimi'),
            'PersonalAccessToken' => __('API Anahtarı'),
            'Permission' => __('İzin'),
            'Role' => __('Rol'),
            'SavedCart' => __('Kayıtlı Sepet'),
            'Setting' => __('Ayar'),
            'StockLot' => __('Lot / Seri No'),
            'StockReceipt' => __('Mal Kabul'),
            'StockCount' => __('Sayım'),
            'Supplier' => __('Tedarikçi'),
            'Tax' => __('Vergi Oranı'),
            'User' => __('Kullanıcı'),
            default => Str::of($className)->headline()->toString(),
        };
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function option(string $subjectType): array
    {
        $value = class_basename($subjectType);

        return [
            'value' => $value,
            'label' => self::for($subjectType) ?? $value,
        ];
    }
}
