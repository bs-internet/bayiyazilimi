import { Form, Head, router } from '@inertiajs/react';
import { Landmark, Pencil, Plus, Save } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import {
    SelectField,
    StatusSwitch,
    TextField,
} from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import {
    destroy,
    index,
    discount,
    payment,
    status as statusRoute,
    store,
    update,
} from '@/routes/panel/shop/bank-accounts';
import type { ShopOption, Status } from '@/types';

type AccountRow = {
    id: string;
    bank_name: string;
    branch: string | null;
    account_holder: string;
    iban: string;
    account_number: string | null;
    currency_code: string | null;
    status: Status;
    sort_order: number;
};

type Props = {
    accounts: AccountRow[];
    currencies: ShopOption[];
    transferEnabled: boolean;
    discountRate: number;
};

/** Havale / EFT ödeme seçeneğini açıp kapatan anahtar (anında kaydedilir). */
function TransferSwitch({
    enabled,
    hasActive,
}: {
    enabled: boolean;
    hasActive: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Card className="flex-row items-start justify-between gap-4 px-5 py-4">
            <label
                htmlFor="bank-transfer-enabled"
                className="flex cursor-pointer flex-col gap-0.5"
            >
                <span className="text-sm font-medium">
                    {t('Havale / EFT ile ödeme')}
                </span>
                <span className="text-sm text-muted-foreground">
                    {enabled && !hasActive
                        ? t(
                              'Açık, ancak aktif hesap olmadığı için ödeme sayfasında gösterilmiyor.',
                          )
                        : enabled
                          ? t('Ödeme sayfasında seçenek olarak sunuluyor.')
                          : t('Kapalı; ödeme sayfasında gösterilmiyor.')}
                </span>
            </label>
            <Switch
                id="bank-transfer-enabled"
                checked={enabled}
                disabled={processing}
                onCheckedChange={(value) =>
                    router.put(
                        payment.url(),
                        { enabled: value ? '1' : '0' },
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                        },
                    )
                }
            />
        </Card>
    );
}

function AccountSheet({
    open,
    onOpenChange,
    account,
    currencies,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    account: AccountRow | null;
    currencies: ShopOption[];
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {account
                            ? t(':bank_name hesabını düzenle', {
                                  bank_name: account.bank_name,
                              })
                            : t('Banka hesabı ekle')}
                    </SheetTitle>
                    <SheetDescription>
                        {t(
                            'Aktif hesaplar havale / EFT ödemesinde, sipariş ekranında ve sipariş e-postasında gösterilir.',
                        )}
                    </SheetDescription>
                </SheetHeader>
                <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-4">
                    <Form
                        key={account?.id ?? 'new'}
                        {...(account ? update.form(account.id) : store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="flex flex-col gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FieldGroup>
                                    <TextField
                                        name="bank_name"
                                        label={t('Banka')}
                                        placeholder={t('Örn. Ziraat Bankası')}
                                        defaultValue={account?.bank_name}
                                        error={errors.bank_name}
                                        required
                                    />
                                    <TextField
                                        name="branch"
                                        label={t('Şube')}
                                        placeholder={t('Örn. Nilüfer / 1234')}
                                        defaultValue={account?.branch}
                                        error={errors.branch}
                                    />
                                    <TextField
                                        name="account_holder"
                                        label={t('Hesap sahibi')}
                                        defaultValue={account?.account_holder}
                                        error={errors.account_holder}
                                        required
                                    />
                                    <TextField
                                        name="iban"
                                        label="IBAN"
                                        placeholder={t(
                                            'TR00 0000 0000 0000 0000 0000 00',
                                        )}
                                        defaultValue={account?.iban}
                                        error={errors.iban}
                                        required
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <TextField
                                            name="account_number"
                                            label={t('Hesap no')}
                                            defaultValue={
                                                account?.account_number
                                            }
                                            error={errors.account_number}
                                        />
                                        <SelectField
                                            name="currency_code"
                                            label={t('Para birimi')}
                                            options={currencies}
                                            defaultValue={
                                                account?.currency_code ?? ''
                                            }
                                            placeholder={t('Belirtilmedi')}
                                            error={errors.currency_code}
                                        />
                                    </div>
                                    <TextField
                                        name="sort_order"
                                        label={t('Sıra')}
                                        type="number"
                                        min={0}
                                        defaultValue={account?.sort_order ?? 0}
                                        error={errors.sort_order}
                                    />
                                </FieldGroup>
                                <FormActions
                                    destroy={
                                        account ? (
                                            <DeleteButton
                                                action={destroy(account.id)}
                                                title={t(
                                                    ':bank_name hesabı silinsin mi?',
                                                    {
                                                        bank_name:
                                                            account.bank_name,
                                                    },
                                                )}
                                                description={t(
                                                    'Bu işlem geri alınamaz. Hesabı yalnızca gizlemek için pasifleştirebilirsiniz.',
                                                )}
                                                onSuccess={() =>
                                                    onOpenChange(false)
                                                }
                                            />
                                        ) : null
                                    }
                                >
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        {t('Kaydet')}
                                    </Button>
                                </FormActions>
                            </>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}

export default function BankAccountIndex({
    accounts,
    currencies,
    transferEnabled,
    discountRate,
}: Props) {
    const [sheet, setSheet] = useState<{
        open: boolean;
        account: AccountRow | null;
    }>({ open: false, account: null });
    const activeCount = accounts.filter((a) => a.status === 'active').length;

    return (
        <>
            <Head title={t('Banka Hesapları')} />

            <Page embedded>
                <PageTitle
                    compact
                    title={t('Banka Hesapları')}
                    meta={t(':accounts_length hesap · :active_count aktif', {
                        accounts_length: accounts.length,
                        active_count: activeCount,
                    })}
                    description={t(
                        'Havale / EFT ile ödemede müşteriye gösterilen hesaplar. Pasif hesaplar gösterilmez.',
                    )}
                    actions={
                        <Button
                            onClick={() =>
                                setSheet({ open: true, account: null })
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {t('Yeni hesap')}
                        </Button>
                    }
                />

                <TransferSwitch
                    enabled={transferEnabled}
                    hasActive={activeCount > 0}
                />

                <Card className="flex flex-col gap-3 p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-sm font-medium">
                            {t('Havale indirimi')}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {t(
                                'Ödeme ekranında havale / EFT seçilince KDV dahil toplamdan düşülür ve siparişe ayrı satır olarak yazılır. 0: indirim yok.',
                            )}
                        </span>
                    </div>
                    <Form
                        {...discount.form()}
                        options={{ preserveScroll: true }}
                        className="flex items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1">
                                    <Input
                                        name="discount_rate"
                                        type="number"
                                        min={0}
                                        max={50}
                                        step="0.01"
                                        defaultValue={discountRate}
                                        className="w-28"
                                        aria-label={t('Havale indirimi (%)')}
                                    />
                                    <InputError
                                        message={errors.discount_rate}
                                    />
                                </div>
                                <span className="pb-2 text-sm">%</span>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    <Save data-icon="inline-start" />
                                    {t('Kaydet')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Card>

                <TableCard>
                    {accounts.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Landmark />
                                </EmptyMedia>
                                <EmptyTitle>{t('Banka hesabı yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Aktif hesap olmadan havale / EFT ödeme seçeneği gösterilmez. İlk hesabı "Yeni hesap" ile ekleyin.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-16">
                                        {t('Sıra')}
                                    </TableHead>
                                    <TableHead>{t('Banka')}</TableHead>
                                    <TableHead>{t('Hesap sahibi')}</TableHead>
                                    <TableHead>{t('IBAN')}</TableHead>
                                    <TableHead>{t('Para birimi')}</TableHead>
                                    <TableHead>{t('Aktif')}</TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {accounts.map((account) => (
                                    <TableRow key={account.id}>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {account.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {account.bank_name}
                                            </div>
                                            {account.branch ? (
                                                <div className="text-xs text-muted-foreground">
                                                    {account.branch}
                                                </div>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            {account.account_holder}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs whitespace-nowrap">
                                            {account.iban}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {account.currency_code ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusSwitch
                                                status={account.status}
                                                url={statusRoute.url(
                                                    account.id,
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t(
                                                    ':bank_name düzenle',
                                                    {
                                                        bank_name:
                                                            account.bank_name,
                                                    },
                                                )}
                                                onClick={() =>
                                                    setSheet({
                                                        open: true,
                                                        account,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>

            <AccountSheet
                open={sheet.open}
                onOpenChange={(open) => setSheet((s) => ({ ...s, open }))}
                account={sheet.account}
                currencies={currencies}
            />
        </>
    );
}

BankAccountIndex.layout = {
    breadcrumbs: [{ title: t('Banka Hesapları'), href: index() }],
};
