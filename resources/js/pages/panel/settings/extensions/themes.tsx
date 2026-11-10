import { Deferred, Head, router } from '@inertiajs/react';
import { CircleCheck, ExternalLink, Palette } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/panel/settings/themes';
import type { CatalogModule, InstalledTheme } from '@/types';

type Props = {
    themes: InstalledTheme[];
    /** Etkin temanın anahtarı; null = varsayılan tema. */
    active: string | null;
    catalog?: CatalogModule[];
};

type Choice = {
    key: string | null;
    name: string;
    description: string | null;
    meta: string | null;
};

function ThemeCard({
    choice,
    active,
    processing,
    onActivate,
}: {
    choice: Choice;
    active: boolean;
    processing: boolean;
    onActivate: () => void;
}) {
    return (
        <li
            className={cn(
                'flex flex-col gap-3 rounded-lg border p-4',
                active && 'border-primary ring-1 ring-primary',
            )}
        >
            <div className="flex items-center gap-2">
                <Palette className="size-4 text-muted-foreground" />
                <h3 className="text-sm font-medium">{choice.name}</h3>
                {active && (
                    <Badge className="ml-auto">
                        <CircleCheck />
                        {t('Etkin')}
                    </Badge>
                )}
            </div>
            {choice.description && (
                <p className="flex-1 text-sm text-muted-foreground">
                    {choice.description}
                </p>
            )}
            {choice.meta && (
                <p className="text-xs text-muted-foreground">{choice.meta}</p>
            )}
            {!active && (
                <Button
                    variant="outline"
                    size="sm"
                    className="self-start"
                    disabled={processing}
                    onClick={onActivate}
                >
                    {processing ? <Spinner /> : <CircleCheck />}
                    {t('Etkinleştir')}
                </Button>
            )}
        </li>
    );
}

function Catalog({ catalog }: { catalog: CatalogModule[] }) {
    if (catalog.length === 0) {
        return null;
    }

    return (
        <section className="border-t pt-6">
            <h2 className="text-sm font-medium">{t('Tüm temalar')}</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {t(
                    'Mağazanız için kurabileceğiniz temalar. Kurulu olanlar işaretlidir; diğerleri için önizleme ve fiyat bilgisini inceleyin.',
                )}
            </p>

            <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {catalog.map((item) => (
                    <li
                        key={item.key}
                        className="flex flex-col gap-3 rounded-lg border p-4"
                    >
                        <div className="flex items-center gap-2">
                            <Palette className="size-4 text-muted-foreground" />
                            <h3 className="text-sm font-medium">{item.name}</h3>
                            {item.installed && (
                                <Badge variant="secondary" className="ml-auto">
                                    {t('Kurulu')}
                                </Badge>
                            )}
                        </div>
                        {item.description && (
                            <p className="flex-1 text-sm text-muted-foreground">
                                {item.description}
                            </p>
                        )}
                        {item.url && (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="self-start"
                            >
                                <a
                                    href={item.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink />
                                    {t('İncele')}
                                </a>
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Themes({ themes, active, catalog }: Props) {
    const [processing, setProcessing] = useState<string | null>(null);

    const choices: Choice[] = [
        {
            key: null,
            name: t('Varsayılan tema'),
            description: t(
                'Mağaza ön yüzünü sunan eklentinin (ör. Vitrin) kendi tasarımı. Renkleri eklentinin ayarlarından değiştirilebilir.',
            ),
            meta: null,
        },
        ...themes.map((theme) => ({
            key: theme.key,
            name: theme.name,
            description: theme.description,
            meta: [`v${theme.version}`, theme.author]
                .filter(Boolean)
                .join(' · '),
        })),
    ];

    const activate = (key: string | null) => {
        setProcessing(key ?? '');
        router.put(
            update().url,
            { theme: key },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(null),
            },
        );
    };

    return (
        <>
            <Head title={t('Temalar')} />

            <div className="flex max-w-5xl flex-col gap-8">
                <div className="border-b pb-5">
                    <h1 className="text-xl font-semibold">{t('Temalar')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Tema, mağaza ön yüzünün görünümünü değiştirir; giriş ekranı ve paneller etkilenmez. Temanın vermediği sayfalarda varsayılan tema kullanılır.',
                        )}
                    </p>
                </div>

                <section>
                    <h2 className="text-sm font-medium">
                        {t('Kurulu temalar')}
                    </h2>
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {choices.map((choice) => (
                            <ThemeCard
                                key={choice.key ?? 'default'}
                                choice={choice}
                                active={choice.key === active}
                                processing={processing === (choice.key ?? '')}
                                onActivate={() => activate(choice.key)}
                            />
                        ))}
                    </ul>
                </section>

                <Deferred
                    data="catalog"
                    fallback={
                        <p className="flex items-center gap-2 border-t pt-6 text-sm text-muted-foreground">
                            <Spinner />
                            {t('Tema listesi yükleniyor…')}
                        </p>
                    }
                >
                    <Catalog catalog={catalog ?? []} />
                </Deferred>
            </div>
        </>
    );
}

Themes.layout = {
    breadcrumbs: [{ title: t('Temalar'), href: index() }],
};
