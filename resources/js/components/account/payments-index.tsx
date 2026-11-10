import { Link } from '@inertiajs/react';
import { CreditCard, Landmark } from 'lucide-react';
import type { ReactNode } from 'react';

import { AccountTitle } from '@/components/account/page-header';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import { paymentStatusVariant } from '@/components/account/status-badge';
import { TableCard } from '@/components/admin/page';
import { BalancePayButton } from '@/components/ledger/balance-pay-button';
import type { BalancePayData } from '@/components/ledger/balance-pay-button';
import { LedgerStatement } from '@/components/ledger/ledger-statement';
import {
    LedgerDues,
    LedgerSummaryCards,
} from '@/components/ledger/ledger-summary';
import {
    PaymentNotifyButton,
    PaymentNotifyHistory,
} from '@/components/ledger/payment-notification-form';
import type { PaymentNotifyData } from '@/components/ledger/payment-notification-form';
import { BankAccountList } from '@/components/shop/bank-account-list';
import type { BankAccountInfo } from '@/components/shop/bank-account-list';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import type { LedgerData } from '@/types';

export type PaymentRow = {
    id: string;
    date: string | null;
    order_id: string | null;
    order_number: string | null;
    method: string;
    amount: string;
    status: string;
    status_label: string;
    reference: string | null;
    failed_reason: string | null;
    awaiting_transfer: boolean;
};

export type PaymentsIndexProps = {
    /** Cari (bakiye + ekstre); hesabı olmayan üyede null. */
    ledger?: LedgerData | null;
    /** Havale bildirimi (yalnız bayi panelinde). */
    notify?: PaymentNotifyData | null;
    /** Kartla cari ödeme (kart modülü kuruluysa). */
    balancePay?: BalancePayData | null;
    payments: PaymentRow[];
    bankAccounts: BankAccountInfo[] | null;
};

export function PaymentsIndexContent({
    area,
    ledger,
    notify,
    balancePay,
    payments,
    bankAccounts,
    title = t('Ödemelerim'),
    description = t('Siparişlerinize ait ödemeler ve havale/EFT bildirimleri.'),
    actions,
}: PaymentsIndexProps & {
    area: AccountArea;
    title?: string;
    description?: string;
    /** Başlık altındaki işlemler (ör. ödeme bildirimi, kartla ödeme). */
    actions?: ReactNode;
}) {
    const routes = accountRoutes(area);
    const awaitingTransfer = payments.some((p) => p.awaiting_transfer);
    const orderLink = (order: { id: string; number: string }) => (
        <Link href={routes.orders.show(order.id)} className="hover:underline">
            {order.number}
        </Link>
    );

    return (
        <>
            <AccountTitle area={area} title={title} description={description} />

            {(actions || notify || balancePay) && (
                <div className="flex flex-wrap items-center gap-2">
                    {notify && <PaymentNotifyButton notify={notify} />}
                    {balancePay && <BalancePayButton data={balancePay} />}
                    {actions}
                </div>
            )}

            {ledger && (
                <>
                    <LedgerSummaryCards ledger={ledger} />
                    <LedgerDues ledger={ledger} orderLink={orderLink} />
                </>
            )}

            {notify && <PaymentNotifyHistory notify={notify} />}

            {awaitingTransfer && bankAccounts?.length ? (
                <Alert>
                    <Landmark />
                    <AlertTitle>{t('Havale / EFT bilgileri')}</AlertTitle>
                    <AlertDescription>
                        <p>
                            {t(
                                'Ödemesi beklenen havale siparişleriniz var. Açıklama kısmına sipariş numaranızı yazmayı unutmayın.',
                            )}
                        </p>
                        <BankAccountList
                            accounts={bankAccounts}
                            className="mt-1 w-full text-foreground"
                        />
                    </AlertDescription>
                </Alert>
            ) : null}

            <TableCard>
                {payments.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <CreditCard />
                            </EmptyMedia>
                            <EmptyTitle>
                                {t('Henüz ödeme kaydınız yok')}
                            </EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Siparişlerinize ait ödemeler burada listelenir.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Tarih')}</TableHead>
                                <TableHead>{t('Sipariş')}</TableHead>
                                <TableHead>{t('Yöntem')}</TableHead>
                                <TableHead>{t('Durum')}</TableHead>
                                <TableHead className="text-right">
                                    {t('Tutar')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(p.date)}
                                    </TableCell>
                                    <TableCell>
                                        {p.order_id ? (
                                            <Link
                                                href={routes.orders.show(
                                                    p.order_id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {p.order_number}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">
                                                {t('Cari tahsilat')}
                                            </span>
                                        )}
                                        {p.reference &&
                                            p.reference !== p.order_number && (
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {p.reference}
                                                </div>
                                            )}
                                    </TableCell>
                                    <TableCell>{p.method}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={paymentStatusVariant(
                                                p.status,
                                            )}
                                        >
                                            {p.awaiting_transfer
                                                ? t('Havale bekleniyor')
                                                : p.status_label}
                                        </Badge>
                                        {p.failed_reason && (
                                            <div className="mt-1 text-xs text-destructive">
                                                {p.failed_reason}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {p.amount}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </TableCard>

            {ledger && (
                <>
                    <h2 className="mt-2 text-base font-semibold">
                        {t('Hesap ekstresi')}
                    </h2>
                    <LedgerStatement ledger={ledger} orderLink={orderLink} />
                </>
            )}
        </>
    );
}
