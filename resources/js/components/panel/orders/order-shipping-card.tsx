import { ExternalLink } from 'lucide-react';

import { InfoList, SectionCard } from '@/components/admin/page';
import { isPositive } from '@/lib/decimal';
import { formatDateTime, formatNumber } from '@/lib/format';

import { t } from '@/lib/i18n';
import type { OrderDetail } from './types';

/** Kargo kartı: firma, desi, takip numarası ve kargoya veriliş tarihi. */
export function OrderShippingCard({ order }: { order: OrderDetail }) {
    const carrierName = order.carrier ?? order.cargo_company;

    return (
        <SectionCard title={t('Kargo')}>
            <InfoList
                items={[
                    {
                        label: t('Kargo firması'),
                        value: carrierName ?? '—',
                    },
                    {
                        label: t('Toplam desi'),
                        value: isPositive(order.total_desi)
                            ? formatNumber(order.total_desi)
                            : '—',
                    },
                    {
                        label: t('Takip numarası'),
                        value: order.tracking_number ? (
                            order.tracking_link ? (
                                <a
                                    href={order.tracking_link}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-primary hover:underline"
                                >
                                    {order.tracking_number}
                                    <ExternalLink className="size-3.5" />
                                </a>
                            ) : (
                                order.tracking_number
                            )
                        ) : (
                            '—'
                        ),
                    },
                    ...(order.shipped_at
                        ? [
                              {
                                  label: t('Kargoya verildi'),
                                  value: formatDateTime(order.shipped_at),
                              },
                          ]
                        : []),
                ]}
            />
        </SectionCard>
    );
}
