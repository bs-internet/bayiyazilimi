import { t } from '@/lib/i18n';
/**
 * Üye alanı içerik bileşenlerinin ortak küçük parçaları.
 */

export type SnapshotAddress = Partial<
    Record<
        | 'name'
        | 'company'
        | 'tax_office'
        | 'tax_number'
        | 'identity_number'
        | 'address'
        | 'district'
        | 'city'
        | 'postal_code'
        | 'phone',
        string | null
    >
>;

/** Sipariş anındaki adres (SectionCard içinde kullanılır). */
export function SnapshotAddressBlock({
    address,
}: {
    address: SnapshotAddress;
}) {
    return (
        <div className="flex flex-col gap-0.5 text-sm">
            <p className="font-medium">{address.company || address.name}</p>
            {address.company && address.name ? (
                <p className="text-muted-foreground">{address.name}</p>
            ) : null}
            <p className="text-muted-foreground">{address.address}</p>
            <p className="text-muted-foreground">
                {[address.district, address.city].filter(Boolean).join(' / ')}
                {address.postal_code ? ` ${address.postal_code}` : ''}
            </p>
            {address.phone ? (
                <p className="text-muted-foreground">{address.phone}</p>
            ) : null}
            {address.tax_number ? (
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('VD: :office · VN: :number', {
                        office: address.tax_office ?? '-',
                        number: address.tax_number,
                    })}
                </p>
            ) : address.identity_number ? (
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('TCKN: :identity_number', {
                        identity_number: address.identity_number,
                    })}
                </p>
            ) : null}
        </div>
    );
}

/** SectionCard (flush) içindeki tabloların başlık / kenar hizası. */
export const flushTableClass =
    'overflow-x-auto [&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-5 [&_th:last-child]:pr-5';
