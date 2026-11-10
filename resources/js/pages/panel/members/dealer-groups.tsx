import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Save, Users, UsersRound } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, TableCard } from '@/components/admin/page';
import { DeleteButton, FormActions } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { CreditTermsFields } from '@/components/ledger/credit-terms-fields';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
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
import { formatMoney, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { index as membersIndex } from '@/routes/panel/members';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/panel/shop/dealer-groups';
import type { CreditTermOptions, CreditTermsValues } from '@/types';

type DealerGroup = {
    id: string;
    name: string;
    discount_rate: string;
    description: string | null;
    dealers_count: number;
    created_at: string;
    terms: CreditTermsValues;
    terms_label: string;
};

type Props = {
    groups: DealerGroup[];
    termOptions: CreditTermOptions;
};

function GroupFields({
    group,
    errors,
    termOptions,
}: {
    group?: DealerGroup;
    errors: Partial<Record<string, string>>;
    termOptions: CreditTermOptions;
}) {
    return (
        <FieldGroup>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="name">{t('Grup adı')}</FieldLabel>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder={t('Örn. Altın Bayi')}
                    defaultValue={group?.name ?? ''}
                    aria-invalid={Boolean(errors.name)}
                />
                <InputError message={errors.name} />
            </Field>
            <Field data-invalid={Boolean(errors.discount_rate)}>
                <FieldLabel htmlFor="discount_rate">
                    {t('İndirim oranı (%)')}
                </FieldLabel>
                <Input
                    id="discount_rate"
                    name="discount_rate"
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    required
                    defaultValue={group?.discount_rate ?? ''}
                    aria-invalid={Boolean(errors.discount_rate)}
                />
                <InputError message={errors.discount_rate} />
            </Field>
            <Field data-invalid={Boolean(errors.description)}>
                <FieldLabel htmlFor="description">{t('Açıklama')}</FieldLabel>
                <Textarea
                    id="description"
                    name="description"
                    rows={3}
                    defaultValue={group?.description ?? ''}
                    aria-invalid={Boolean(errors.description)}
                />
                <InputError message={errors.description} />
            </Field>
            <CreditTermsFields
                values={group?.terms}
                options={termOptions}
                errors={errors}
            />
        </FieldGroup>
    );
}

function SaveButton({ processing }: { processing: boolean }) {
    return (
        <div className="flex justify-end">
            <Button type="submit" disabled={processing}>
                {processing ? <Spinner /> : <Save data-icon="inline-start" />}
                {t('Kaydet')}
            </Button>
        </div>
    );
}

function CreateGroupSheet({ termOptions }: { termOptions: CreditTermOptions }) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button>
                    <Plus data-icon="inline-start" />
                    {t('Yeni grup')}
                </Button>
            </SheetTrigger>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{t('Bayi grubu ekle')}</SheetTitle>
                    <SheetDescription>
                        {t(
                            'Gruptaki bayilere uygulanacak indirim oranını girin.',
                        )}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <GroupFields
                                errors={errors}
                                termOptions={termOptions}
                            />
                            <SaveButton processing={processing} />
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function EditGroupSheet({
    group,
    termOptions,
}: {
    group: DealerGroup;
    termOptions: CreditTermOptions;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('Düzenle')}
                >
                    <Pencil />
                    <span className="sr-only">{t('Düzenle')}</span>
                </Button>
            </SheetTrigger>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{t('Bayi grubunu düzenle')}</SheetTitle>
                    <SheetDescription>
                        {t(':group_name grubunu düzenleyin.', {
                            group_name: group.name,
                        })}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...update.form(group.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <GroupFields
                                group={group}
                                errors={errors}
                                termOptions={termOptions}
                            />
                            {group.dealers_count > 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Bu grupta :dealers_count bayi var; silmeden önce bayileri başka gruba taşıyın.',
                                        { dealers_count: group.dealers_count },
                                    )}
                                </p>
                            )}
                            <FormActions
                                destroy={
                                    <DeleteButton
                                        action={destroy(group.id)}
                                        title={t(
                                            ':group_name grubu silinsin mi?',
                                            { group_name: group.name },
                                        )}
                                        description={t(
                                            'Bu işlem geri alınamaz.',
                                        )}
                                        disabled={group.dealers_count > 0}
                                    />
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
            </SheetContent>
        </Sheet>
    );
}

export default function DealerGroups({ groups, termOptions }: Props) {
    return (
        <>
            <Head title={t('Bayi Grupları')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Bayiler'), href: membersIndex() },
                        { title: t('Bayi grupları') },
                    ]}
                    title={t('Bayi grupları')}
                    description={t(
                        'Her grubun tek bir indirim oranı vardır. Bayiye özel oran girilirse grubun oranının yerine geçer; indirimli ürün ve kategorilerde bayi indirimi uygulanmaz.',
                    )}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={membersIndex()}>
                                    <Users data-icon="inline-start" />
                                    {t('Bayiler')}
                                </Link>
                            </Button>
                            <CreateGroupSheet termOptions={termOptions} />
                        </>
                    }
                />

                <TableCard>
                    {groups.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UsersRound />
                                </EmptyMedia>
                                <EmptyTitle>{t('Bayi grubu yok')}</EmptyTitle>
                                <EmptyDescription>
                                    {t('İlk grubu "Yeni grup" ile oluşturun.')}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Grup')}</TableHead>
                                    <TableHead>{t('Çalışma şekli')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('İndirim')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Bayi sayısı')}
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            {t('İşlemler')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {groups.map((group) => (
                                    <TableRow key={group.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {group.name}
                                            </div>
                                            {group.description && (
                                                <div className="max-w-md truncate text-sm text-muted-foreground">
                                                    {group.description}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {group.terms_label}
                                            {group.terms.payment_terms ===
                                                'credit' && (
                                                <div className="text-xs text-muted-foreground">
                                                    {group.terms.credit_limit
                                                        ? t(
                                                              ':days gün · limit :limit',
                                                              {
                                                                  days: group
                                                                      .terms
                                                                      .payment_term_days,
                                                                  limit: formatMoney(
                                                                      group
                                                                          .terms
                                                                          .credit_limit,
                                                                  ),
                                                              },
                                                          )
                                                        : t(
                                                              ':days gün · limitsiz',
                                                              {
                                                                  days: group
                                                                      .terms
                                                                      .payment_term_days,
                                                              },
                                                          )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            %{formatNumber(group.discount_rate)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            <Link
                                                href={membersIndex({
                                                    query: {
                                                        type: 'dealer',
                                                        group: group.id,
                                                    },
                                                })}
                                                className="hover:underline"
                                            >
                                                {group.dealers_count}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <EditGroupSheet
                                                key={`${group.id}-${group.name}-${group.discount_rate}-${group.description}-${JSON.stringify(group.terms)}`}
                                                group={group}
                                                termOptions={termOptions}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </TableCard>
            </Page>
        </>
    );
}

DealerGroups.layout = {
    breadcrumbs: [
        {
            title: t('Bayi Grupları'),
            href: index(),
        },
    ],
};
