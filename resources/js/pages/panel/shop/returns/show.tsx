import { Form, Head, Link } from '@inertiajs/react';
import { Mail, Phone, RefreshCw, Save, X } from 'lucide-react';
import { useState } from 'react';

import { PaymentStatusBadge } from '@/components/account/status-badge';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    InfoList,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import InputError from '@/components/input-error';
import { ReturnStatusBadge } from '@/components/shop/return-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { show as orderShow } from '@/routes/panel/shop/orders';
import { index, status as statusRoute } from '@/routes/panel/shop/returns';
import type { ShopOption } from '@/types';

type Props = {
    returnRequest: {
        id: string;
        number: string;
        status: string;
        status_label: string;
        is_final: boolean;
        reason_label: string;
        customer_note: string | null;
        admin_note: string | null;
        refund_amount: string | null;
        suggested_refund: string;
        currency_code: string;
        restocked: boolean;
        created_at: string | null;
        resolved_at: string | null;
        resolver: string | null;
        customer: string;
        order: {
            id: string;
            number: string;
            email: string;
            phone: string;
            status_label: string;
            payment_status: string;
            payment_status_label: string;
            grand_total: string;
        };
        items: {
            id: string;
            name: string | null;
            sku: string | null;
            quantity: number;
            ordered: number | null;
            unit_total: string | null;
        }[];
    };
    statuses: ShopOption[];
};

function money(value: string | number, code: string): string {
    return `${Number(value).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${code}`;
}

export default function ReturnShow({ returnRequest: r, statuses }: Props) {
    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState(r.status);
    const [restock, setRestock] = useState(!r.restocked);
    const [markRefunded, setMarkRefunded] = useState(
        r.order.payment_status === 'paid',
    );

    const canRestock =
        !r.restocked && (status === 'received' || status === 'refunded');
    const itemCount = r.items.reduce((sum, i) => sum + i.quantity, 0);

    // Diyalog her açılışta sunucudaki son değerlerle başlar.
    const openDialog = () => {
        setStatus(r.status);
        setRestock(!r.restocked);
        setMarkRefunded(r.order.payment_status === 'paid');
        setOpen(true);
    };

    const formKey = [r.status, r.admin_note, r.refund_amount, r.restocked].join(
        '|',
    );

    const history = [
        {
            key: 'created',
            title: t('Talep oluşturuldu'),
            at: r.created_at,
            by: r.customer,
            note: null as string | null,
        },
        ...(r.resolved_at
            ? [
                  {
                      key: 'resolved',
                      title: t('Sonuçlandı: :status_label', {
                          status_label: r.status_label,
                      }),
                      at: r.resolved_at,
                      by: r.resolver,
                      note: r.admin_note,
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title={t('İade :number', { number: r.number })} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Satış') },
                        { title: t('İadeler'), href: index() },
                        { title: `#${r.number}` },
                    ]}
                    title={t('İade #:number', { number: r.number })}
                    badges={
                        <>
                            <ReturnStatusBadge
                                status={r.status}
                                label={r.status_label}
                            />
                            {r.restocked && (
                                <Badge variant="outline">
                                    {t('Stoğa eklendi')}
                                </Badge>
                            )}
                        </>
                    }
                    meta={
                        <>
                            <span>{formatDateTime(r.created_at)}</span>
                            <MetaDot />
                            <span>
                                {t(':item_count ürün', {
                                    item_count: itemCount,
                                })}
                            </span>
                            <MetaDot />
                            <span>{r.reason_label}</span>
                        </>
                    }
                    actions={
                        !r.is_final && (
                            <Button onClick={openDialog}>
                                <RefreshCw data-icon="inline-start" />
                                {t('Durumu değiştir')}
                            </Button>
                        )
                    }
                />

                <EntityLayout>
                    <EntityMain>
                        <SectionCard title={t('İade edilen ürünler')} flush>
                            <div className="overflow-x-auto">
                                <Table className="[&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th]:bg-muted/40 [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_th]:text-muted-foreground [&_th]:uppercase [&_th:first-child]:pl-5 [&_th:last-child]:pr-5">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Ürün')}</TableHead>
                                            <TableHead className="text-right">
                                                {t('İade / Sipariş')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('Birim (KDV dahil)')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {r.items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="min-w-56">
                                                    <div className="font-medium whitespace-normal">
                                                        {item.name ?? '—'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('SKU:')}{' '}
                                                        <span className="font-mono">
                                                            {item.sku ?? '—'}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {item.quantity} /{' '}
                                                    {item.ordered ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-right whitespace-nowrap tabular-nums">
                                                    {item.unit_total ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </SectionCard>

                        <SectionCard title={t('Neden ve açıklama')}>
                            <InfoList
                                items={[
                                    {
                                        label: t('İade nedeni'),
                                        value: r.reason_label,
                                    },
                                    {
                                        label: t('Müşteri açıklaması'),
                                        value: r.customer_note ? (
                                            <span className="font-normal whitespace-pre-line">
                                                {r.customer_note}
                                            </span>
                                        ) : (
                                            '—'
                                        ),
                                    },
                                    ...(r.admin_note
                                        ? [
                                              {
                                                  label: t('Müşteriye not'),
                                                  value: (
                                                      <span className="font-normal whitespace-pre-line">
                                                          {r.admin_note}
                                                      </span>
                                                  ),
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                        </SectionCard>

                        <SectionCard title={t('Geçmiş')}>
                            <ol className="relative flex flex-col gap-5 border-l pl-5 text-sm">
                                {history.map((h) => (
                                    <li key={h.key} className="relative">
                                        <span
                                            aria-hidden
                                            className="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full border-2 border-background bg-primary"
                                        />
                                        <div className="font-medium">
                                            {h.title}
                                        </div>
                                        <div className="text-xs text-muted-foreground tabular-nums">
                                            {formatDateTime(h.at)}
                                            {h.by ? ` · ${h.by}` : ''}
                                        </div>
                                        {h.note && (
                                            <p className="mt-1 text-muted-foreground">
                                                {h.note}
                                            </p>
                                        )}
                                    </li>
                                ))}
                                {!r.is_final && (
                                    <li className="relative text-muted-foreground">
                                        <span
                                            aria-hidden
                                            className="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full border-2 border-background bg-muted-foreground/40"
                                        />
                                        {t('Şu an: :status', {
                                            status: r.status_label,
                                        })}
                                    </li>
                                )}
                            </ol>
                        </SectionCard>
                    </EntityMain>

                    <EntitySidebar>
                        <SectionCard title={t('Sipariş')}>
                            <InfoList
                                items={[
                                    {
                                        label: t('Sipariş no'),
                                        value: (
                                            <Link
                                                href={orderShow(r.order.id)}
                                                className="font-mono text-primary hover:underline"
                                            >
                                                #{r.order.number}
                                            </Link>
                                        ),
                                    },
                                    {
                                        label: t('Sipariş durumu'),
                                        value: r.order.status_label,
                                    },
                                    {
                                        label: t('Ödeme'),
                                        value: (
                                            <PaymentStatusBadge
                                                status={r.order.payment_status}
                                                label={
                                                    r.order.payment_status_label
                                                }
                                            />
                                        ),
                                    },
                                    {
                                        label: t('Sipariş tutarı'),
                                        value: (
                                            <span className="tabular-nums">
                                                {r.order.grand_total}
                                            </span>
                                        ),
                                    },
                                ]}
                            />
                        </SectionCard>

                        <SectionCard title={t('Müşteri')}>
                            <div className="flex flex-col gap-2 text-sm">
                                <span className="font-medium">
                                    {r.customer}
                                </span>
                                <a
                                    href={`mailto:${r.order.email}`}
                                    className="flex items-center gap-2 break-all hover:underline"
                                >
                                    <Mail className="size-4 shrink-0 text-muted-foreground" />
                                    {r.order.email}
                                </a>
                                {r.order.phone && (
                                    <a
                                        href={`tel:${r.order.phone}`}
                                        className="flex items-center gap-2 hover:underline"
                                    >
                                        <Phone className="size-4 shrink-0 text-muted-foreground" />
                                        {r.order.phone}
                                    </a>
                                )}
                            </div>
                        </SectionCard>

                        <SectionCard title={t('İade tutarı')}>
                            <InfoList
                                items={[
                                    {
                                        label: r.refund_amount
                                            ? t('İade edilen')
                                            : t(
                                                  'Önerilen (ürün tutarlarından)',
                                              ),
                                        value: (
                                            <span className="text-base tabular-nums">
                                                {money(
                                                    r.refund_amount ??
                                                        r.suggested_refund,
                                                    r.currency_code,
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        label: t('Stok'),
                                        value: r.restocked
                                            ? t('Ürünler stoğa geri eklendi')
                                            : t('Stoğa eklenmedi'),
                                    },
                                ]}
                            />
                        </SectionCard>
                    </EntitySidebar>
                </EntityLayout>
            </Page>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('İade durumunu değiştir')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Durum değişikliğinde müşteriye e-posta gönderilir.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        key={formKey}
                        {...statusRoute.form(r.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="flex flex-col gap-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FieldGroup>
                                    <Field
                                        data-invalid={Boolean(errors.status)}
                                    >
                                        <FieldLabel htmlFor="status">
                                            {t('Durum')}
                                        </FieldLabel>
                                        <div className="*:data-[slot=native-select-wrapper]:w-full">
                                            <NativeSelect
                                                id="status"
                                                name="status"
                                                value={status}
                                                onChange={(e) =>
                                                    setStatus(e.target.value)
                                                }
                                            >
                                                {statuses.map((s) => (
                                                    <NativeSelectOption
                                                        key={s.value}
                                                        value={s.value}
                                                    >
                                                        {s.label}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                        </div>
                                        <InputError message={errors.status} />
                                    </Field>
                                    {(status === 'refunded' ||
                                        status === 'received') && (
                                        <Field
                                            data-invalid={Boolean(
                                                errors.refund_amount,
                                            )}
                                        >
                                            <FieldLabel htmlFor="refund_amount">
                                                {t(
                                                    'İade tutarı (:currency_code)',
                                                    {
                                                        currency_code:
                                                            r.currency_code,
                                                    },
                                                )}
                                            </FieldLabel>
                                            <Input
                                                id="refund_amount"
                                                name="refund_amount"
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                defaultValue={
                                                    r.refund_amount ??
                                                    r.suggested_refund
                                                }
                                                className="tabular-nums"
                                            />
                                            <InputError
                                                message={errors.refund_amount}
                                            />
                                        </Field>
                                    )}
                                    {canRestock && (
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="hidden"
                                                name="restock"
                                                value={restock ? '1' : '0'}
                                            />
                                            <Checkbox
                                                checked={restock}
                                                onCheckedChange={(v) =>
                                                    setRestock(v === true)
                                                }
                                            />
                                            <span>
                                                {t('Ürünleri stoğa geri ekle')}
                                                <span className="block text-xs text-muted-foreground">
                                                    {t(
                                                        'Siparişin deposuna &quot;İade&quot; stok hareketi yazılır.',
                                                    )}
                                                </span>
                                            </span>
                                        </label>
                                    )}
                                    {status === 'refunded' && (
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="hidden"
                                                name="mark_refunded"
                                                value={markRefunded ? '1' : '0'}
                                            />
                                            <Checkbox
                                                checked={markRefunded}
                                                onCheckedChange={(v) =>
                                                    setMarkRefunded(v === true)
                                                }
                                            />
                                            <span>
                                                {t(
                                                    'Siparişin ödeme durumunu &quot;İade Edildi&quot; yap',
                                                )}
                                            </span>
                                        </label>
                                    )}
                                    <Field
                                        data-invalid={Boolean(
                                            errors.admin_note,
                                        )}
                                    >
                                        <FieldLabel htmlFor="admin_note">
                                            {t('Müşteriye not')}
                                        </FieldLabel>
                                        <Textarea
                                            id="admin_note"
                                            name="admin_note"
                                            rows={3}
                                            defaultValue={r.admin_note ?? ''}
                                        />
                                        <InputError
                                            message={errors.admin_note}
                                        />
                                    </Field>
                                </FieldGroup>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <X data-icon="inline-start" />
                                            {t('Vazgeç')}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        {t('Kaydet')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ReturnShow.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('İadeler'), href: index() },
    ],
};
