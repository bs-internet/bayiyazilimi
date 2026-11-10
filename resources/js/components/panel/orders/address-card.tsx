import { InfoList, SectionCard } from '@/components/admin/page';

import { t } from '@/lib/i18n';
import type { Address } from './types';

export function AddressCard({
    title,
    address,
    billing = false,
}: {
    title: string;
    address: Address;
    billing?: boolean;
}) {
    const a = address ?? {};
    const place = [a.district, a.city].filter(Boolean).join(' / ');
    const hasAny = Object.values(a).some((v) => v);

    return (
        <SectionCard title={title}>
            {!hasAny ? (
                <p className="text-sm text-muted-foreground">
                    {t('Adres yok.')}
                </p>
            ) : (
                <div className="flex flex-col gap-3 text-sm">
                    <div className="flex flex-col gap-0.5">
                        {a.name && (
                            <span className="font-medium">{a.name}</span>
                        )}
                        {a.company && <span>{a.company}</span>}
                        {a.address && (
                            <span className="text-muted-foreground">
                                {a.address}
                            </span>
                        )}
                        {(place || a.postal_code) && (
                            <span className="text-muted-foreground">
                                {[place, a.postal_code]
                                    .filter(Boolean)
                                    .join(' ')}
                            </span>
                        )}
                        {a.phone && (
                            <span className="text-muted-foreground">
                                {a.phone}
                            </span>
                        )}
                    </div>
                    {billing &&
                        (a.tax_office || a.tax_number || a.identity_number) && (
                            <InfoList
                                className="border-t pt-3"
                                items={[
                                    {
                                        label: t('Fatura türü'),
                                        value:
                                            a.type === 'corporate'
                                                ? t('Kurumsal')
                                                : t('Bireysel'),
                                    },
                                    ...(a.tax_office || a.tax_number
                                        ? [
                                              {
                                                  label: t(
                                                      'Vergi dairesi / no',
                                                  ),
                                                  value: [
                                                      a.tax_office,
                                                      a.tax_number,
                                                  ]
                                                      .filter(Boolean)
                                                      .join(' / '),
                                              },
                                          ]
                                        : []),
                                    ...(a.identity_number
                                        ? [
                                              {
                                                  label: t('T.C. kimlik no'),
                                                  value: a.identity_number,
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                        )}
                </div>
            )}
        </SectionCard>
    );
}
