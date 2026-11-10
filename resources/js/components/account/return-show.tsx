import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import { flushTableClass } from '@/components/account/parts';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { ReturnStatusBadge } from '@/components/account/status-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    InfoList,
    MetaDot,
    SectionCard,
} from '@/components/admin/page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';

export type ReturnShowProps = {
    returnRequest: {
        id: string;
        number: string;
        order_id: string;
        order_number: string | null;
        status: string;
        status_label: string;
        reason_label: string;
        created_at: string | null;
        customer_note: string | null;
        admin_note: string | null;
        refund_amount: string | null;
        store_address: string | null;
        items: {
            id: string;
            name: string | null;
            sku: string | null;
            quantity: number;
        }[];
    };
};

export function ReturnShowContent({
    area,
    returnRequest: r,
}: ReturnShowProps & { area: AccountArea }) {
    const routes = accountRoutes(area);

    return (
        <>
            <AccountTitle
                area={area}
                title={t('İade :number', { number: r.number })}
                parent={{ title: t('İadelerim'), href: routes.returns.index() }}
                badges={
                    <ReturnStatusBadge
                        status={r.status}
                        label={r.status_label}
                    />
                }
                meta={
                    <>
                        <span>{formatDateTime(r.created_at)}</span>
                        {r.order_number ? (
                            <>
                                <MetaDot />
                                <span>
                                    {t('Sipariş :order_number', {
                                        order_number: r.order_number,
                                    })}
                                </span>
                            </>
                        ) : null}
                        <MetaDot />
                        <span>
                            {t(':count kalem', { count: r.items.length })}
                        </span>
                    </>
                }
            />

            {r.status === 'approved' && r.store_address && (
                <Alert>
                    <CircleCheck />
                    <AlertTitle>{t('Talebiniz onaylandı')}</AlertTitle>
                    <AlertDescription>
                        <p>
                            {t(
                                'Ürün(ler)i orijinal ambalajı ve faturasıyla birlikte şu adrese gönderebilirsiniz:',
                            )}{' '}
                            <strong className="text-foreground">
                                {r.store_address}
                            </strong>
                        </p>
                    </AlertDescription>
                </Alert>
            )}

            <EntityLayout>
                <EntityMain>
                    <SectionCard title={t('İade edilen ürünler')} flush>
                        <div className={flushTableClass}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Ürün')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('Adet')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {r.items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {item.name}
                                                </div>
                                                {item.sku ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('Kod: :sku', {
                                                            sku: item.sku,
                                                        })}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.quantity}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </SectionCard>

                    {r.admin_note ? (
                        <SectionCard title={t('Mağaza notu')}>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {r.admin_note}
                            </p>
                        </SectionCard>
                    ) : null}
                </EntityMain>

                <EntitySidebar>
                    <SectionCard title={t('Talep')}>
                        <InfoList
                            items={[
                                { label: t('Neden'), value: r.reason_label },
                                {
                                    label: t('Sipariş'),
                                    value: (
                                        <Link
                                            href={routes.orders.show(
                                                r.order_id,
                                            )}
                                            className="text-primary hover:underline"
                                        >
                                            {r.order_number ?? '—'}
                                        </Link>
                                    ),
                                },
                                ...(r.refund_amount
                                    ? [
                                          {
                                              label: t('İade tutarı'),
                                              value: (
                                                  <span className="tabular-nums">
                                                      {r.refund_amount}
                                                  </span>
                                              ),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    </SectionCard>

                    {r.customer_note ? (
                        <SectionCard title={t('Açıklamanız')}>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {r.customer_note}
                            </p>
                        </SectionCard>
                    ) : null}
                </EntitySidebar>
            </EntityLayout>
        </>
    );
}
