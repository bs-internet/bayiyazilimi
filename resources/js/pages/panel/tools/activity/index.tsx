import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    ChevronDownIcon,
    RotateCcw,
    Search,
} from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
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
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { activity } from '@/routes/panel/tools';
import type {
    Activity,
    ActivityCauserTypeOption,
    ActivityFilters,
    ActivitySubjectTypeOption,
} from '@/types/activity';
import type { Paginated } from '@/types/pagination';

type PageProps = InertiaPageProps & {
    activities: Paginated<Activity>;
    filters: ActivityFilters;
    causer_types: ActivityCauserTypeOption[];
    subject_types: ActivitySubjectTypeOption[];
};

const EVENT_LABELS: Record<string, string> = {
    created: t('Oluşturuldu'),
    updated: t('Güncellendi'),
    deleted: 'Silindi',
    restored: t('Geri Yüklendi'),
    force_deleted: t('Kalıcı Silindi'),
    activated: t('Aktifleştirildi'),
    deactivated: t('Pasifleştirildi'),
    cleared: 'Temizlendi',
    bulk_added: t('Toplu Eklendi'),
    permissions_synced: t('Yetkiler Güncellendi'),
    role_assigned: t('Rol Atandı'),
    role_revoked: t('Rol Kaldırıldı'),
    status_updated: t('Durum Güncellendi'),
    two_factor_reset: t('İki Adımlı Doğrulama Sıfırlandı'),
    preferences_updated: t('Tercihler Güncellendi'),
    password_updated: t('Şifre Güncellendi'),
    password_reset_requested: t('Şifre Sıfırlama İstendi'),
    email_updated: t('E-posta Güncellendi'),
    email_changed: t('E-posta Değiştirildi'),
    email_change_requested: t('E-posta Değişikliği İstendi'),
    email_verified: t('E-posta Doğrulandı'),
    email_verification_requested: t('E-posta Doğrulaması İstendi'),
    verified: t('Doğrulandı'),
    session_revoked: t('Oturum Kapatıldı'),
    revoked: t('İptal Edildi'),
    new_device_login: t('Yeni Cihaz Girişi'),
    media_directory_assigned: t('Medya Dizini Atandı'),
};

const DESTRUCTIVE_EVENTS = new Set([
    'deleted',
    'force_deleted',
    'deactivated',
    'role_revoked',
    'session_revoked',
    'revoked',
]);

const POSITIVE_EVENTS = new Set([
    'created',
    'restored',
    'activated',
    'role_assigned',
    'email_verified',
    'verified',
    'bulk_added',
]);

function getEventVariant(
    event: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (event === null) {
        return 'outline';
    }

    if (DESTRUCTIVE_EVENTS.has(event)) {
        return 'destructive';
    }

    if (POSITIVE_EVENTS.has(event)) {
        return 'default';
    }

    return 'secondary';
}

function getEventLabel(event: string | null): string {
    if (event === null) {
        return '-';
    }

    return (
        EVENT_LABELS[event] ??
        event
            .replace(/_/g, ' ')
            .replace(/^\w/, (char) => char.toLocaleUpperCase('tr-TR'))
    );
}

export default function ActivityIndex() {
    const { activities, filters, causer_types, subject_types } =
        usePage<PageProps>().props;

    const form = useForm<ActivityFilters>({
        user_id: filters.user_id,
        subject_type: filters.subject_type,
        causer_type: filters.causer_type,
        from: filters.from,
        to: filters.to,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.get(activity({ query: form.data }).url);
    };

    const handleReset = () => {
        // form.reset() başlangıç (uygulanmış filtre) değerlerine döndüğü için
        // seçimi temizlemez; alanları açıkça boşaltıp filtresiz sayfaya gidiyoruz.
        form.setData({
            user_id: '',
            subject_type: '',
            causer_type: '',
            from: '',
            to: '',
        });
        form.get(activity().url);
    };

    return (
        <>
            <Head title={t('Etkinlik Kayıtları')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl">
                <form
                    onSubmit={handleSubmit}
                    className="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="causer_type"
                            className="text-sm font-medium"
                        >
                            {t('Kullanıcı Türü')}
                        </label>
                        <div className="relative w-full">
                            <select
                                id="causer_type"
                                value={form.data.causer_type}
                                onChange={(e) =>
                                    form.setData('causer_type', e.target.value)
                                }
                                className="h-9 w-full min-w-0 appearance-none rounded-md border border-input bg-transparent px-3 py-2 pr-9 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed dark:bg-input/30 dark:hover:bg-input/50"
                            >
                                <option value="">{t('Tümü')}</option>
                                {causer_types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <ChevronDownIcon className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted-foreground opacity-50 select-none" />
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="subject_type"
                            className="text-sm font-medium"
                        >
                            {t('Kayıt Türü')}
                        </label>
                        <div className="relative w-full">
                            <select
                                id="subject_type"
                                value={form.data.subject_type}
                                onChange={(e) =>
                                    form.setData('subject_type', e.target.value)
                                }
                                className="h-9 w-full min-w-0 appearance-none rounded-md border border-input bg-transparent px-3 py-2 pr-9 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed dark:bg-input/30 dark:hover:bg-input/50"
                            >
                                <option value="">{t('Tümü')}</option>
                                {subject_types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <ChevronDownIcon className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted-foreground opacity-50 select-none" />
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="from" className="text-sm font-medium">
                            {t('Başlangıç Tarihi')}
                        </label>
                        <Input
                            id="from"
                            type="date"
                            value={form.data.from}
                            onChange={(e) =>
                                form.setData('from', e.target.value)
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="to" className="text-sm font-medium">
                            {t('Bitiş Tarihi')}
                        </label>
                        <Input
                            id="to"
                            type="date"
                            value={form.data.to}
                            onChange={(e) => form.setData('to', e.target.value)}
                        />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            <Search className="size-4" />
                            {t('Filtrele')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleReset}
                        >
                            <RotateCcw className="size-4" />
                            {t('Sıfırla')}
                        </Button>
                    </div>
                </form>

                {activities.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ActivityIcon className="size-6" />
                            </EmptyMedia>
                            <EmptyTitle>{t('Kayıt Bulunamadı')}</EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Seçili filtrelere uygun etkinlik kaydı bulunmuyor.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <div className="relative overflow-hidden rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Tarih')}</TableHead>
                                        <TableHead>{t('Kullanıcı')}</TableHead>
                                        <TableHead>{t('Olay')}</TableHead>
                                        <TableHead>{t('Kayıt Türü')}</TableHead>
                                        <TableHead>{t('Açıklama')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {activities.data.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                {item.created_at
                                                    ? new Date(
                                                          item.created_at,
                                                      ).toLocaleString('tr-TR')
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.causer?.name ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={getEventVariant(
                                                        item.event,
                                                    )}
                                                >
                                                    {getEventLabel(item.event)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {item.subject_label ?? '-'}
                                            </TableCell>
                                            <TableCell className="max-w-xs truncate">
                                                {item.description ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <DataPagination paginator={activities} />
                    </>
                )}
            </div>
        </>
    );
}

ActivityIndex.layout = {
    breadcrumbs: [
        {
            title: t('Etkinlik Kayıtları'),
            href: dashboard(),
        },
    ],
};
