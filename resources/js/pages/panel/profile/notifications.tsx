import { Form, Head } from '@inertiajs/react';
import { Bell, CheckCircle2 } from 'lucide-react';
import { markAsRead } from '@/actions/App/Http/Controllers/Panel/Profile/NotificationsController';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemMedia,
    ItemTitle,
} from '@/components/ui/item';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { notifications } from '@/routes/panel/profile';
import type { NotificationItem, Paginated } from '@/types';

type NotificationsProps = {
    notifications?: Paginated<NotificationItem>;
};

function getNotificationText(
    notification: NotificationItem,
    key: 'title' | 'message',
): string {
    const value = notification.data[key];

    return typeof value === 'string' ? value : '-';
}

export default function Notifications({
    notifications: items,
}: NotificationsProps) {
    const notificationItems = items?.data ?? [];

    return (
        <>
            <Head title={t('Bildirimler')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Bildirimler')}
                    description={t(
                        'Hesabınıza ait bildirimleri buradan takip edebilirsiniz.',
                    )}
                />

                {notificationItems.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Bell />
                            </EmptyMedia>
                            <EmptyTitle>{t('Henüz bildirim yok')}</EmptyTitle>
                            <EmptyDescription>
                                {t(
                                    'Yeni bildirimler oluştuğunda bu alanda listelenecek.',
                                )}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <div className="max-w-3xl space-y-4">
                        <ItemGroup className="gap-3">
                            {notificationItems.map((notification) => (
                                <Item
                                    key={notification.id}
                                    className={cn(
                                        'border bg-background shadow-xs',
                                        !notification.read_at &&
                                            'border-primary/20 bg-muted/35',
                                    )}
                                >
                                    <ItemMedia
                                        variant="icon"
                                        className={cn(
                                            !notification.read_at &&
                                                'border-primary/25 bg-primary/10 text-primary',
                                        )}
                                    >
                                        {notification.read_at ? (
                                            <CheckCircle2 />
                                        ) : (
                                            <Bell />
                                        )}
                                    </ItemMedia>
                                    <ItemContent className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <ItemTitle>
                                                {getNotificationText(
                                                    notification,
                                                    'title',
                                                )}
                                            </ItemTitle>
                                            {!notification.read_at && (
                                                <Badge variant="secondary">
                                                    {t('Yeni')}
                                                </Badge>
                                            )}
                                            {notification.read_at && (
                                                <Badge variant="outline">
                                                    {t('Okundu')}
                                                </Badge>
                                            )}
                                        </div>
                                        <ItemDescription>
                                            {getNotificationText(
                                                notification,
                                                'message',
                                            )}
                                        </ItemDescription>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                notification.created_at,
                                            ).toLocaleString('tr-TR')}
                                        </p>
                                    </ItemContent>

                                    {!notification.read_at && (
                                        <ItemActions className="ml-auto">
                                            <Form
                                                {...markAsRead.form(
                                                    notification.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        size="icon-sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                        aria-label={t(
                                                            'Okundu olarak işaretle',
                                                        )}
                                                        title={t(
                                                            'Okundu olarak işaretle',
                                                        )}
                                                    >
                                                        <CheckCircle2 />
                                                    </Button>
                                                )}
                                            </Form>
                                        </ItemActions>
                                    )}
                                </Item>
                            ))}
                        </ItemGroup>

                        {items && (
                            <DataPagination
                                paginator={items}
                                label={t('bildirim')}
                                showRange={false}
                            />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: t('Bildirimler'),
            href: notifications(),
        },
    ],
};
