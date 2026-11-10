import { SectionCard } from '@/components/admin/page';
import { AddressCard } from '@/components/panel/members/address-card';
import type { MemberAddress } from '@/components/panel/members/types';
import { t } from '@/lib/i18n';

/** Adresler sekmesi: teslimat ve fatura adresleri. */
export function MemberAddressesTab({
    addresses,
}: {
    addresses: MemberAddress[];
}) {
    const shippingAddresses = addresses.filter((a) => a.type !== 'billing');
    const billingAddresses = addresses.filter((a) => a.type !== 'shipping');

    return (
        <>
            <SectionCard
                title={t('Teslimat adresleri')}
                description={t(
                    'Üyenin Adreslerim bölümünde kayıtlı adresleri.',
                )}
            >
                {shippingAddresses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('Kayıtlı teslimat adresi yok.')}
                    </p>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {shippingAddresses.map((a) => (
                            <AddressCard key={a.id} address={a} />
                        ))}
                    </div>
                )}
            </SectionCard>
            <SectionCard title={t('Fatura adresleri')}>
                {billingAddresses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('Kayıtlı fatura adresi yok.')}
                    </p>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {billingAddresses.map((a) => (
                            <AddressCard key={a.id} address={a} />
                        ))}
                    </div>
                )}
            </SectionCard>
        </>
    );
}
