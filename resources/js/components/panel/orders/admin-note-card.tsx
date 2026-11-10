import { Form } from '@inertiajs/react';
import { Pencil, Save, X } from 'lucide-react';
import { useState } from 'react';

import { SectionCard } from '@/components/admin/page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { note } from '@/routes/panel/shop/orders';

import type { OrderDetail } from './types';

/** Yalnızca panelde görünen yönetici notu (satır içi düzenleme). */
export function AdminNoteCard({
    order,
    formKey,
}: {
    order: OrderDetail;
    formKey: string;
}) {
    const [editingNote, setEditingNote] = useState(false);

    return (
        <SectionCard
            title={t('Yönetici notu')}
            description={t('Yalnızca panelde görünür.')}
            action={
                !editingNote && (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('Notu düzenle')}
                        onClick={() => setEditingNote(true)}
                    >
                        <Pencil />
                    </Button>
                )
            }
        >
            {editingNote ? (
                <Form
                    key={formKey}
                    {...note.form(order.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEditingNote(false)}
                    className="flex flex-col gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <Textarea
                                name="admin_note"
                                rows={4}
                                defaultValue={order.admin_note ?? ''}
                                aria-label={t('Yönetici notu')}
                                aria-invalid={Boolean(errors.admin_note)}
                                autoFocus
                            />
                            <InputError message={errors.admin_note} />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => setEditingNote(false)}
                                >
                                    <X data-icon="inline-start" />
                                    {t('Vazgeç')}
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <Save data-icon="inline-start" />
                                    )}
                                    {t('Kaydet')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            ) : order.admin_note ? (
                <p className="text-sm whitespace-pre-line">
                    {order.admin_note}
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t('Not eklenmemiş.')}
                </p>
            )}
        </SectionCard>
    );
}
