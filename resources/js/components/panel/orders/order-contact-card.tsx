import { Mail, Phone } from 'lucide-react';

import { SectionCard } from '@/components/admin/page';

import { t } from '@/lib/i18n';
import type { OrderDetail } from './types';

/** İletişim kartı: e-posta ve telefon bağlantıları. */
export function OrderContactCard({ order }: { order: OrderDetail }) {
    return (
        <SectionCard title={t('İletişim')}>
            <div className="flex flex-col gap-2 text-sm">
                <a
                    href={`mailto:${order.email}`}
                    className="flex items-center gap-2 break-all hover:underline"
                >
                    <Mail className="size-4 shrink-0 text-muted-foreground" />
                    {order.email}
                </a>
                {order.phone && (
                    <a
                        href={`tel:${order.phone}`}
                        className="flex items-center gap-2 hover:underline"
                    >
                        <Phone className="size-4 shrink-0 text-muted-foreground" />
                        {order.phone}
                    </a>
                )}
            </div>
        </SectionCard>
    );
}
