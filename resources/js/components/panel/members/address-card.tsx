import type { MemberAddress } from '@/components/panel/members/types';
import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';

export function AddressCard({ address }: { address: MemberAddress }) {
    const defaultLabel =
        address.type === 'billing'
            ? address.is_default_billing && t('Varsayılan')
            : address.is_default_shipping && t('Varsayılan');

    return (
        <div className="flex flex-col gap-1 rounded-lg border p-4 text-sm">
            <div className="flex items-center justify-between gap-2">
                <span className="font-medium">{address.title}</span>
                {defaultLabel && (
                    <Badge variant="secondary">{defaultLabel}</Badge>
                )}
            </div>
            <div>{address.company || address.name}</div>
            {address.company && (
                <div className="text-muted-foreground">{address.name}</div>
            )}
            <div className="text-muted-foreground">
                {address.address}
                {address.postal_code ? ` ${address.postal_code}` : ''}
            </div>
            <div className="text-muted-foreground">
                {[address.district, address.city, address.country]
                    .filter(Boolean)
                    .join(' / ')}
            </div>
            {address.phone && (
                <div className="text-muted-foreground">{address.phone}</div>
            )}
            {(address.tax_number || address.identity_number) && (
                <div className="text-xs text-muted-foreground">
                    {address.tax_number
                        ? `VD: ${address.tax_office ?? '-'} · VN: ${address.tax_number}`
                        : t('TCKN: :identity_number', {
                              identity_number: address.identity_number,
                          })}
                </div>
            )}
        </div>
    );
}
