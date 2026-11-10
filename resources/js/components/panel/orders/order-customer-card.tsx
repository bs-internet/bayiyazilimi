import { Link } from '@inertiajs/react';

import { InfoList, SectionCard } from '@/components/admin/page';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import { show as memberShow } from '@/routes/panel/members';
import { index } from '@/routes/panel/shop/orders';
import { edit as quoteShow } from '@/routes/panel/shop/quotes';

import type { OrderDetail } from './types';

/** Müşteri kartı: ad, hesap türü, sipariş sayısı, teklif ve müşteri notu. */
export function OrderCustomerCard({ order }: { order: OrderDetail }) {
    const isDealer = order.account?.type === 'dealer';
    const initials = order.customer_name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toLocaleUpperCase('tr-TR'))
        .join('');

    return (
        <SectionCard title={t('Müşteri')}>
            <div className="flex flex-col gap-4 text-sm">
                <div className="flex items-center gap-3">
                    <Avatar className="size-10">
                        <AvatarFallback>{initials || '?'}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        {order.user_id ? (
                            <Link
                                href={memberShow(order.user_id)}
                                className="font-medium hover:underline"
                            >
                                {order.customer_name}
                            </Link>
                        ) : (
                            <span className="font-medium">
                                {order.customer_name}
                            </span>
                        )}
                        <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                            {order.account?.type_label ? (
                                <Badge
                                    variant={isDealer ? 'default' : 'secondary'}
                                >
                                    {order.account.type_label}
                                </Badge>
                            ) : (
                                <span>{t('Misafir sipariş')}</span>
                            )}
                            {order.account &&
                                order.account.orders_count != null && (
                                    <Link
                                        href={index({
                                            query: {
                                                account: order.account.id,
                                            },
                                        })}
                                        className="hover:text-foreground hover:underline"
                                    >
                                        {order.account.orders_count === 1
                                            ? t('İlk siparişi')
                                            : t(':orders_count sipariş', {
                                                  orders_count:
                                                      order.account
                                                          .orders_count,
                                              })}
                                    </Link>
                                )}
                        </div>
                    </div>
                </div>
                {order.account?.title &&
                    order.account.title !== order.customer_name && (
                        <InfoList
                            items={[
                                {
                                    label: t('Hesap'),
                                    value: order.account.title,
                                },
                            ]}
                        />
                    )}
                {order.quote && (
                    <Link
                        href={quoteShow(order.quote.id)}
                        className="text-sm text-primary hover:underline"
                    >
                        {t('Teklif #:number', { number: order.quote.number })}
                    </Link>
                )}
                {order.customer_note && (
                    <div className="rounded-md bg-muted p-3">
                        <div className="mb-1 text-xs font-medium text-muted-foreground">
                            {t('Müşteri notu')}
                        </div>
                        {order.customer_note}
                    </div>
                )}
            </div>
        </SectionCard>
    );
}
