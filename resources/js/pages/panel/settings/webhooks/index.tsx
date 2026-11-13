import { Form, Head, router } from '@inertiajs/react';
import { Check, Copy, Plus, Send, Trash2, Webhook } from 'lucide-react';
import { useState } from 'react';

import { ConfirmDelete } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    destroy,
    index,
    store,
    test,
    update,
} from '@/routes/panel/settings/webhooks';
import type { Option } from '@/types';

type Delivery = {
    id: string;
    event: string;
    status: 'pending' | 'succeeded' | 'failed';
    attempts: number;
    response_status: number | null;
    error: string | null;
    created_at: string | null;
};

type WebhookItem = {
    id: string;
    url: string;
    events: string[];
    description: string | null;
    active: boolean;
    failure_count: number;
    last_delivery_at: string | null;
    deliveries: Delivery[];
};

type Props = {
    webhooks: WebhookItem[];
    events: Option<string>[];
    plainSecret: string | null;
};

const STATUS: Record<
    Delivery['status'],
    { label: string; variant: 'secondary' | 'destructive' | 'outline' }
> = {
    pending: { label: t('Bekliyor'), variant: 'outline' },
    succeeded: { label: t('Gönderildi'), variant: 'secondary' },
    failed: { label: t('Başarısız'), variant: 'destructive' },
};

function SecretAlert({ secret }: { secret: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <Alert className="border-primary/30 bg-primary/5">
            <Webhook />
            <AlertTitle>{t('İmza anahtarı')}</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>
                    {t(
                        'Gelen isteklerin X-Bayi-Signature başlığını bu anahtarla doğrulayın. Bir daha gösterilmez.',
                    )}
                </p>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        readOnly
                        value={secret}
                        className="font-mono text-xs"
                        onFocus={(event) => event.currentTarget.select()}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            void navigator.clipboard
                                ?.writeText(secret)
                                .then(() => setCopied(true))
                        }
                    >
                        {copied ? <Check /> : <Copy />}
                        {copied ? t('Kopyalandı') : t('Kopyala')}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}

function NewWebhookForm({ events }: { events: Option<string>[] }) {
    return (
        <Form
            {...store.form()}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-col gap-4 rounded-lg border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="webhook-url">
                                {t('Adres (https://…)')}
                            </FieldLabel>
                            <Input
                                id="webhook-url"
                                name="url"
                                type="url"
                                required
                                placeholder="https://ornek.com/bayi-webhook"
                            />
                            <InputError message={errors.url} />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="webhook-description">
                                {t('Açıklama (isteğe bağlı)')}
                            </FieldLabel>
                            <Input
                                id="webhook-description"
                                name="description"
                                maxLength={255}
                            />
                        </Field>
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">
                                {t('Olaylar')}
                            </span>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {events.map((event) => (
                                    <label
                                        key={event.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            name="events[]"
                                            value={event.value}
                                        />
                                        <span>{event.label}</span>
                                        <code className="text-xs text-muted-foreground">
                                            {event.value}
                                        </code>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.events} />
                        </div>
                    </FieldGroup>
                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <Plus />}
                            {t('Webhook ekle')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function WebhookCard({
    webhook,
    labels,
}: {
    webhook: WebhookItem;
    labels: Record<string, string>;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-mono text-sm break-all">{webhook.url}</p>
                    {webhook.description && (
                        <p className="text-sm text-muted-foreground">
                            {webhook.description}
                        </p>
                    )}
                    <div className="mt-2 flex flex-wrap gap-1">
                        {webhook.events.map((event) => (
                            <Badge key={event} variant="outline">
                                {event === '*'
                                    ? t('Tüm olaylar')
                                    : (labels[event] ?? event)}
                            </Badge>
                        ))}
                    </div>
                    {webhook.failure_count > 0 && (
                        <p className="mt-2 text-xs text-destructive">
                            {t('Art arda :count başarısız gönderim.', {
                                count: webhook.failure_count,
                            })}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Switch
                        checked={webhook.active}
                        aria-label={t('Açık')}
                        onCheckedChange={(active) =>
                            router.patch(
                                update(webhook.id).url,
                                { active },
                                { preserveScroll: true },
                            )
                        }
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.post(
                                test(webhook.id).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Send />
                        {t('Dene')}
                    </Button>
                    <ConfirmDelete
                        action={destroy(webhook.id)}
                        title={t('Webhook silinsin mi?')}
                        description={t(
                            'Bu adrese artık bildirim gönderilmez; gönderim geçmişi de silinir.',
                        )}
                        confirmLabel={t('Sil')}
                        preserveScroll
                    >
                        <Button size="sm" variant="outline">
                            <Trash2 />
                            {t('Sil')}
                        </Button>
                    </ConfirmDelete>
                </div>
            </div>

            {webhook.deliveries.length > 0 && (
                <ul className="divide-y rounded-md border text-sm">
                    {webhook.deliveries.map((d) => (
                        <li
                            key={d.id}
                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                        >
                            <span className="flex items-center gap-2">
                                <Badge variant={STATUS[d.status].variant}>
                                    {STATUS[d.status].label}
                                </Badge>
                                <code className="text-xs">{d.event}</code>
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {d.response_status !== null &&
                                    `HTTP ${d.response_status} · `}
                                {d.error && `${d.error} · `}
                                {t(':count deneme', {
                                    count: d.attempts,
                                })}{' '}
                                · {formatDateTime(d.created_at)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Webhooks({ webhooks, events, plainSecret }: Props) {
    const labels = Object.fromEntries(events.map((e) => [e.value, e.label]));

    return (
        <>
            <Head title={t('Webhook’lar')} />

            <div className="flex max-w-5xl flex-col gap-8">
                <div className="border-b pb-5">
                    <h1 className="text-xl font-semibold">
                        {t('Webhook’lar')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Seçtiğiniz olaylar olduğunda (sipariş açıldı, durumu değişti, ödeme alındı …) adresinize JSON gövdeli, imzalı bir POST isteği gönderilir. 2xx dönmeyen gönderim artan aralıklarla 6 kez denenir.',
                        )}
                    </p>
                </div>

                {plainSecret && <SecretAlert secret={plainSecret} />}

                <section className="space-y-4">
                    <h2 className="text-sm font-medium">{t('Yeni webhook')}</h2>
                    <NewWebhookForm events={events} />
                </section>

                <section className="space-y-4">
                    <h2 className="text-sm font-medium">
                        {t('Kayıtlı adresler')}
                    </h2>
                    {webhooks.length === 0 ? (
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Webhook />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {t('Henüz webhook yok')}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {t(
                                        'Dış sisteminizin bildirim alacağı adresi yukarıdan ekleyin.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        webhooks.map((webhook) => (
                            <WebhookCard
                                key={webhook.id}
                                webhook={webhook}
                                labels={labels}
                            />
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

Webhooks.layout = {
    breadcrumbs: [{ title: t('Webhook’lar'), href: index() }],
};
