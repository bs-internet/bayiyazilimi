import { Head, router } from '@inertiajs/react';
import { Minus, Pencil, Plus, RefreshCw, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    InfoList,
    MetaDot,
    Page,
    PageTitle,
    SectionCard,
    TableCard,
} from '@/components/admin/page';
import { SavedCartDialog } from '@/components/dealer-shop/saved-cart-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import savedCarts from '@/routes/app/shop/saved-carts';

type Line =
    | { id: string; available: false; quantity: number }
    | {
          id: string;
          available: true;
          slug: string;
          name: string;
          sku: string;
          brand: string | null;
          image: string;
          quantity: number;
          has_price: boolean;
          unit_gross_formatted: string | null;
          line_gross_formatted: string | null;
          stock: number;
          warning: string | null;
      };

type Props = {
    savedCart: {
        id: string;
        name: string;
        note: string | null;
        created_at: string | null;
        updated_at: string | null;
        created_by: string | null;
        item_count: number;
        estimated_total_formatted: string;
        has_warnings: boolean;
    };
    lines: Line[];
};

/** Adet kutusu (− / +); değer yerel tutulur, "Sepeti güncelle" ile kaydedilir. */
function QuantityStepper({
    name,
    value,
    onChange,
}: {
    name: string;
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <div className="ml-auto flex w-32 items-center">
            <Button
                type="button"
                variant="outline"
                size="icon-sm"
                className="rounded-r-none"
                onClick={() => onChange(Math.max(0, value - 1))}
                aria-label={t(':name adedini azalt', { name })}
            >
                <Minus />
            </Button>
            <Input
                type="number"
                inputMode="numeric"
                min={0}
                value={value}
                onChange={(e) =>
                    onChange(
                        Math.max(0, Number.parseInt(e.target.value, 10) || 0),
                    )
                }
                aria-label={t(':name adedi', { name })}
                className="h-8 [appearance:textfield] rounded-none border-x-0 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none"
            />
            <Button
                type="button"
                variant="outline"
                size="icon-sm"
                className="rounded-l-none"
                onClick={() => onChange(value + 1)}
                aria-label={t(':name adedini artır', { name })}
            >
                <Plus />
            </Button>
        </div>
    );
}

/** Bayi paneli: kayıtlı sepet detayı — adetleri güncelle, doğrudan sipariş ver. */
export default function SavedCartShow({ savedCart, lines }: Props) {
    const [quantities, setQuantities] = useState<Record<string, number>>(() =>
        Object.fromEntries(lines.map((l) => [l.id, l.quantity])),
    );
    const [busy, setBusy] = useState(false);
    const dirty = lines.some((l) => quantities[l.id] !== l.quantity);

    const setQty = (id: string, qty: number) =>
        setQuantities((prev) => ({ ...prev, [id]: qty }));

    const saveItems = (then?: () => void) =>
        router.put(
            savedCarts.items.url(savedCart.id),
            { quantities },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onSuccess: () => then?.(),
            },
        );

    // Kaydedilmemiş değişiklik varsa önce kaydedilir, sonra ödemeye geçilir.
    const order = () =>
        dirty
            ? saveItems(() =>
                  router.visit(savedCarts.checkout.url(savedCart.id)),
              )
            : router.visit(savedCarts.checkout.url(savedCart.id));

    return (
        <>
            <Head title={savedCart.name} />
            <Page>
                <PageTitle
                    title={savedCart.name}
                    trail={[
                        {
                            title: t('Kayıtlı sepetler'),
                            href: savedCarts.index(),
                        },
                        { title: savedCart.name },
                    ]}
                    badges={
                        savedCart.has_warnings ? (
                            <Badge variant="destructive">
                                {t('Uyarı var')}
                            </Badge>
                        ) : undefined
                    }
                    meta={
                        <>
                            <span>{formatDateTime(savedCart.created_at)}</span>
                            <MetaDot />
                            <span>
                                {t(':item_count ürün', {
                                    item_count: savedCart.item_count,
                                })}
                            </span>
                            <MetaDot />
                            <span className="tabular-nums">
                                {t('Tahmini :estimated_total_formatted', {
                                    estimated_total_formatted:
                                        savedCart.estimated_total_formatted,
                                })}
                            </span>
                        </>
                    }
                    actions={
                        <>
                            <SavedCartDialog
                                savedCart={savedCart}
                                trigger={
                                    <Button variant="outline">
                                        <Pencil data-icon="inline-start" />
                                        {t('Düzenle')}
                                    </Button>
                                }
                            />
                            <Button
                                variant="outline"
                                disabled={!dirty || busy}
                                onClick={() => saveItems()}
                            >
                                <RefreshCw data-icon="inline-start" />
                                {t('Sepeti güncelle')}
                            </Button>
                            <Button disabled={busy} onClick={order}>
                                <ShoppingBag data-icon="inline-start" />
                                {t('Sipariş ver')}
                            </Button>
                        </>
                    }
                />

                <EntityLayout>
                    <EntityMain>
                        {savedCart.has_warnings && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {t(
                                        'Bazı ürünler artık satışta değil, yalnızca teklifle satılıyor ya da stoğu yetersiz. Sipariş vermeden önce adetlerini düzeltin ya da 0 yapıp çıkarın.',
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}
                        <TableCard>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Ürün')}</TableHead>
                                            <TableHead className="text-right">
                                                {t('Birim fiyat')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('Adet')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('Tutar')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {lines.map((line) =>
                                            line.available ? (
                                                <TableRow key={line.id}>
                                                    <TableCell>
                                                        <div className="flex min-w-60 items-center gap-3">
                                                            <img
                                                                src={line.image}
                                                                alt=""
                                                                className="size-12 shrink-0 rounded-md border bg-white object-contain"
                                                                loading="lazy"
                                                            />
                                                            <div className="min-w-0">
                                                                <div className="line-clamp-2 font-medium whitespace-normal">
                                                                    {line.name}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {line.brand
                                                                        ? `${line.brand} · `
                                                                        : ''}
                                                                    {t(
                                                                        'SKU: :sku',
                                                                        {
                                                                            sku: line.sku,
                                                                        },
                                                                    )}
                                                                </div>
                                                                {line.warning && (
                                                                    <Badge
                                                                        variant="destructive"
                                                                        className="mt-1"
                                                                    >
                                                                        {
                                                                            line.warning
                                                                        }
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {line.unit_gross_formatted ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <QuantityStepper
                                                            name={line.name}
                                                            value={
                                                                quantities[
                                                                    line.id
                                                                ] ?? 0
                                                            }
                                                            onChange={(v) =>
                                                                setQty(
                                                                    line.id,
                                                                    v,
                                                                )
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {line.line_gross_formatted ??
                                                            '—'}
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                <TableRow key={line.id}>
                                                    <TableCell
                                                        colSpan={4}
                                                        className="text-muted-foreground"
                                                    >
                                                        <Badge variant="destructive">
                                                            {t('Satışta değil')}
                                                        </Badge>{' '}
                                                        {t(
                                                            'Bu ürün artık satışta değil (:quantity adet).',
                                                            {
                                                                quantity:
                                                                    line.quantity,
                                                            },
                                                        )}{' '}
                                                        <Button
                                                            type="button"
                                                            variant="link"
                                                            size="sm"
                                                            className="h-auto p-0"
                                                            onClick={() =>
                                                                setQty(
                                                                    line.id,
                                                                    0,
                                                                )
                                                            }
                                                        >
                                                            {quantities[
                                                                line.id
                                                            ] === 0
                                                                ? t(
                                                                      'Çıkarılacak',
                                                                  )
                                                                : t(
                                                                      'Sepetten çıkar',
                                                                  )}
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </TableCard>
                    </EntityMain>
                    <EntitySidebar>
                        <SectionCard title={t('Sepet bilgileri')}>
                            <InfoList
                                items={[
                                    {
                                        label: t('Tahmini tutar (KDV dahil)'),
                                        value: (
                                            <span className="tabular-nums">
                                                {
                                                    savedCart.estimated_total_formatted
                                                }
                                            </span>
                                        ),
                                    },
                                    {
                                        label: t('Oluşturan'),
                                        value: savedCart.created_by,
                                    },
                                    {
                                        label: t('Oluşturma'),
                                        value: formatDateTime(
                                            savedCart.created_at,
                                        ),
                                    },
                                    {
                                        label: t('Son güncelleme'),
                                        value: formatDateTime(
                                            savedCart.updated_at,
                                        ),
                                    },
                                ]}
                            />
                        </SectionCard>
                        {savedCart.note && (
                            <SectionCard title={t('Not')}>
                                <p className="text-sm whitespace-pre-line">
                                    {savedCart.note}
                                </p>
                            </SectionCard>
                        )}
                    </EntitySidebar>
                </EntityLayout>
            </Page>
        </>
    );
}

SavedCartShow.layout = {
    breadcrumbs: [{ title: t('Kayıtlı sepetler'), href: savedCarts.index() }],
};
