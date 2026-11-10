import { Head, Link } from '@inertiajs/react';
import { Check, Copy, KeyRound, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { ConfirmDelete } from '@/components/confirm-delete';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { create, destroy, index } from '@/routes/panel/settings/api-tokens';
import type { ApiTokenItem } from '@/types';

type Props = {
    tokens: ApiTokenItem[];
    /** Yeni oluşturulan anahtar (yalnız oluşturma sonrası bir kez gelir). */
    plainToken: string | null;
    baseUrl: string;
};

function NewTokenAlert({ token }: { token: string }) {
    const [copied, setCopied] = useState(false);

    const copy = () => {
        void navigator.clipboard?.writeText(token).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <Alert className="border-primary/30 bg-primary/5">
            <KeyRound />
            <AlertTitle>{t('Yeni API anahtarınız')}</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>
                    {t(
                        'Anahtarı şimdi kopyalayıp güvenli bir yere kaydedin; bu sayfadan ayrıldığınızda bir daha gösterilmez.',
                    )}
                </p>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        readOnly
                        value={token}
                        className="font-mono text-xs"
                        onFocus={(event) => event.currentTarget.select()}
                    />
                    <Button type="button" variant="outline" onClick={copy}>
                        {copied ? <Check /> : <Copy />}
                        {copied ? t('Kopyalandı') : t('Kopyala')}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}

function TokenList({ tokens }: { tokens: ApiTokenItem[] }) {
    if (tokens.length === 0) {
        return (
            <Empty className="max-w-3xl border">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <KeyRound />
                    </EmptyMedia>
                    <EmptyTitle>{t('Henüz API anahtarı yok')}</EmptyTitle>
                    <EmptyDescription>
                        {t(
                            'Bir entegrasyonu bağlamak için yeni bir anahtar oluşturun.',
                        )}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t('Ad')}</TableHead>
                        <TableHead>{t('Sahibi')}</TableHead>
                        <TableHead>{t('Yetkiler')}</TableHead>
                        <TableHead>{t('Son kullanım')}</TableHead>
                        <TableHead>{t('Oluşturulma')}</TableHead>
                        <TableHead className="text-right">
                            {t('İşlem')}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {tokens.map((token) => (
                        <TableRow key={token.id}>
                            <TableCell className="font-medium">
                                <div className="flex flex-wrap items-center gap-2">
                                    {token.name}
                                    {token.expired && (
                                        <Badge variant="destructive">
                                            {t('Süresi doldu')}
                                        </Badge>
                                    )}
                                </div>
                                {token.expires_at && !token.expired && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('Geçerlilik sonu: :date', {
                                            date: formatDate(token.expires_at),
                                        })}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell>{token.owner ?? '-'}</TableCell>
                            <TableCell>
                                <div className="flex max-w-sm flex-wrap gap-1">
                                    {token.abilities.map((ability) => (
                                        <Badge
                                            key={ability.value}
                                            variant="outline"
                                            title={ability.value}
                                        >
                                            {ability.label}
                                        </Badge>
                                    ))}
                                </div>
                            </TableCell>
                            <TableCell className="whitespace-nowrap">
                                {token.last_used_at
                                    ? formatDateTime(token.last_used_at)
                                    : t('Hiç kullanılmadı')}
                            </TableCell>
                            <TableCell className="whitespace-nowrap">
                                {formatDateTime(token.created_at)}
                            </TableCell>
                            <TableCell className="text-right">
                                <ConfirmDelete
                                    action={destroy(token.id)}
                                    title={t('API anahtarı iptal edilsin mi?')}
                                    description={t(
                                        ':name anahtarını kullanan entegrasyonlar hemen erişimini kaybeder. Bu işlem geri alınamaz.',
                                        { name: token.name },
                                    )}
                                    confirmLabel={t('İptal et')}
                                    preserveScroll
                                >
                                    <Button size="sm" variant="outline">
                                        <Trash2 />
                                        {t('İptal et')}
                                    </Button>
                                </ConfirmDelete>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

export default function ApiTokens({ tokens, plainToken, baseUrl }: Props) {
    return (
        <>
            <Head title={t('API Anahtarları')} />

            <div className="flex max-w-5xl flex-col gap-8">
                <div className="border-b pb-5">
                    <h1 className="text-xl font-semibold">
                        {t('API Anahtarları')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'ERP, muhasebe programı ya da özel yazılımlarınız ürün, stok, sipariş ve cari verilerine REST API ile erişir. İstekler Authorization: Bearer başlığıyla anahtar gönderir.',
                        )}
                    </p>
                    <p className="mt-2 text-sm">
                        {t('API adresi:')}{' '}
                        <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                            {baseUrl}
                        </code>
                    </p>
                </div>

                {plainToken && <NewTokenAlert token={plainToken} />}

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <h2 className="text-sm font-medium">
                            {t('Mevcut anahtarlar')}
                        </h2>
                        <Button asChild size="sm">
                            <Link href={create()}>
                                <Plus />
                                {t('Yeni anahtar')}
                            </Link>
                        </Button>
                    </div>
                    <TokenList tokens={tokens} />
                </section>
            </div>
        </>
    );
}

ApiTokens.layout = {
    breadcrumbs: [{ title: t('API Anahtarları'), href: index() }],
};
