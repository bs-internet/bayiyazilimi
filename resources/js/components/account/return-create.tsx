import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';

import { AccountTitle } from '@/components/account/page-header';
import { flushTableClass } from '@/components/account/parts';
import type { AccountArea } from '@/components/account/routes';
import { accountRoutes } from '@/components/account/routes';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    SectionCard,
} from '@/components/admin/page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { t } from '@/lib/i18n';

export type ReturnCreateProps = {
    order: {
        id: string;
        number: string;
        items: {
            id: string;
            name: string;
            sku: string | null;
            quantity: number;
            returnable: number;
            line_total: string;
        }[];
    };
    reasons: { value: string; label: string }[];
    returnDays: number;
};

export function ReturnCreateContent({
    area,
    order,
    reasons,
    returnDays,
}: ReturnCreateProps & { area: AccountArea }) {
    const routes = accountRoutes(area);

    return (
        <Form
            {...routes.returns.store.form(order.id)}
            className="flex flex-col gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <AccountTitle
                        area={area}
                        title={t('İade talebi oluştur')}
                        parent={{
                            title: t('İadelerim'),
                            href: routes.returns.index(),
                        }}
                        description={t(
                            ':number numaralı siparişinizden iade etmek istediğiniz ürünleri ve adetleri seçin. İade süresi: :return_days gün.',
                            { number: order.number, return_days: returnDays },
                        )}
                        actions={
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Send data-icon="inline-start" />
                                )}
                                {t('İade talebini gönder')}
                            </Button>
                        }
                    />

                    <EntityLayout>
                        <EntityMain>
                            <SectionCard
                                title={t('Ürünler')}
                                flush
                                footer={
                                    errors.items ? (
                                        <InputError message={errors.items} />
                                    ) : undefined
                                }
                            >
                                <div className={flushTableClass}>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {t('Ürün')}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t('Sipariş')}
                                                </TableHead>
                                                <TableHead className="w-32 text-right">
                                                    {t('İade adedi')}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {order.items.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell>
                                                        <div className="font-medium">
                                                            {item.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {item.sku
                                                                ? t(
                                                                      'Kod: :sku · ',
                                                                      {
                                                                          sku: item.sku,
                                                                      },
                                                                  )
                                                                : ''}
                                                            {item.line_total}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {item.quantity}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {item.returnable > 0 ? (
                                                            <Input
                                                                type="number"
                                                                name={`items[${item.id}]`}
                                                                min={0}
                                                                max={
                                                                    item.returnable
                                                                }
                                                                defaultValue={0}
                                                                className="text-right"
                                                                aria-label={t(
                                                                    ':item_name iade adedi',
                                                                    {
                                                                        item_name:
                                                                            item.name,
                                                                    },
                                                                )}
                                                            />
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                {t(
                                                                    'İade edilemez',
                                                                )}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </SectionCard>
                        </EntityMain>

                        <EntitySidebar>
                            <SectionCard title={t('İade nedeni')}>
                                <FieldGroup>
                                    <Field
                                        data-invalid={Boolean(errors.reason)}
                                    >
                                        <FieldLabel htmlFor="reason">
                                            {t('Neden')}
                                        </FieldLabel>
                                        <NativeSelect
                                            id="reason"
                                            name="reason"
                                            required
                                            defaultValue=""
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.reason,
                                            )}
                                        >
                                            <NativeSelectOption
                                                value=""
                                                disabled
                                            >
                                                {t('Seçin')}
                                            </NativeSelectOption>
                                            {reasons.map((r) => (
                                                <NativeSelectOption
                                                    key={r.value}
                                                    value={r.value}
                                                >
                                                    {r.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                        <InputError message={errors.reason} />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="customer_note">
                                            {t('Açıklama')}
                                        </FieldLabel>
                                        <Textarea
                                            id="customer_note"
                                            name="customer_note"
                                            rows={4}
                                            placeholder={t(
                                                'Ürünün durumu, sorun vb.',
                                            )}
                                        />
                                        <InputError
                                            message={errors.customer_note}
                                        />
                                    </Field>
                                </FieldGroup>
                            </SectionCard>
                        </EntitySidebar>
                    </EntityLayout>
                </>
            )}
        </Form>
    );
}
