import { router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';

import { ConfirmDelete } from '@/components/confirm-delete';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { status } from '@/routes/panel/shop/orders';

/** Onay bekleyen vadeli sipariş: onayla / reddet. */
export function OrderApprovalAlert({ orderId }: { orderId: string }) {
    return (
        <Alert>
            <AlertTitle>{t('Vadeli sipariş onay bekliyor')}</AlertTitle>
            <AlertDescription>
                <p>
                    {t(
                        'Kredi limiti aşıldığı ya da vadesi geçmiş borç olduğu için sipariş onaya düştü (ayrıntı geçmişte). Onaylanınca hazırlanmaya alınır ve borç cariye vadeyle yazılır; reddedilirse iptal edilir ve stok geri eklenir.',
                    )}
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        onClick={() =>
                            router.patch(
                                status.url(orderId),
                                {
                                    status: 'processing',
                                    note: t('Vadeli sipariş onaylandı.'),
                                    notify: true,
                                },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Check data-icon="inline-start" />
                        {t('Onayla')}
                    </Button>
                    <ConfirmDelete
                        action={status(orderId, {
                            query: {
                                status: 'cancelled',
                                note: t('Vadeli sipariş onaylanmadı.'),
                                notify: 1,
                            },
                        })}
                        title={t('Sipariş reddedilsin mi?')}
                        description={t(
                            'Sipariş iptal edilir, stok geri eklenir ve müşteriye bildirilir.',
                        )}
                        confirmLabel={t('Reddet')}
                        confirmIcon={X}
                    >
                        <Button size="sm" variant="outline">
                            <X data-icon="inline-start" />
                            {t('Reddet')}
                        </Button>
                    </ConfirmDelete>
                </div>
            </AlertDescription>
        </Alert>
    );
}
