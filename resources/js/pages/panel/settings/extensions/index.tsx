import { Deferred, Head, router } from '@inertiajs/react';
import {
    Blocks,
    CircleCheck,
    ExternalLink,
    Power,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useState } from 'react';

import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { index, update } from '@/routes/panel/settings/extensions';
import type { CatalogModule, InstalledModule } from '@/types';

type Props = {
    modules: InstalledModule[];
    /** Ertelenmiş prop: sayfa açıldıktan sonra uzaktan çekilir. */
    catalog?: CatalogModule[];
};

function ModuleRow({
    module,
    onRequestDisable,
}: {
    module: InstalledModule;
    onRequestDisable: (module: InstalledModule) => void;
}) {
    const [processing, setProcessing] = useState(false);

    const enable = () => {
        setProcessing(true);
        router.patch(
            update(module.key).url,
            { enabled: true },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <li className="flex items-start gap-4 py-5">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/40">
                <Blocks className="size-5 text-muted-foreground" />
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-sm font-medium">{module.name}</h2>
                    <Badge variant="outline" className="tabular-nums">
                        v{module.version}
                    </Badge>
                    {!module.enabled && (
                        <Badge variant="secondary">{t('Kapalı')}</Badge>
                    )}
                    {module.setup === 'pending' && (
                        <Badge
                            variant="outline"
                            className="border-amber-500/50 text-amber-700 dark:text-amber-400"
                        >
                            <TriangleAlert />
                            {t('Kurulum tamamlanmadı')}
                        </Badge>
                    )}
                    {module.setup === 'update' && (
                        <Badge
                            variant="outline"
                            className="border-amber-500/50 text-amber-700 dark:text-amber-400"
                        >
                            <TriangleAlert />
                            {t('Güncelleme tamamlanmadı')}
                        </Badge>
                    )}
                </div>

                {module.description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {module.description}
                    </p>
                )}

                {module.setup !== 'done' && (
                    <p className="mt-2 text-xs text-amber-700 dark:text-amber-400">
                        {t(
                            'Eklentinin kurulum adımı (veritabanı tabloları, yetkiler) çalıştırılmamış. Sunucuda şu komutu çalıştırın: :command',
                            {
                                command: `php artisan bayi:install ${module.key}`,
                            },
                        )}
                    </p>
                )}

                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    {module.installed_at && (
                        <span>
                            {t('Kuruldu: :date', {
                                date: formatDate(module.installed_at),
                            })}
                        </span>
                    )}
                    {module.author && (
                        <span>
                            {t('Geliştiren: :author', {
                                author: module.author,
                            })}
                        </span>
                    )}
                    {module.requires.length > 0 && (
                        <span>
                            {t('Gerekli eklentiler: :modules', {
                                modules: module.requires.join(', '),
                            })}
                        </span>
                    )}
                    {module.required_by.length > 0 && (
                        <span>
                            {t('Kullanan eklentiler: :modules', {
                                modules: module.required_by.join(', '),
                            })}
                        </span>
                    )}
                </div>
            </div>

            <Switch
                checked={module.enabled}
                disabled={processing}
                aria-label={t(':name eklentisini aç / kapat', {
                    name: module.name,
                })}
                onCheckedChange={(checked) =>
                    checked ? enable() : onRequestDisable(module)
                }
            />
        </li>
    );
}

function Catalog({ catalog }: { catalog: CatalogModule[] }) {
    if (catalog.length === 0) {
        return null;
    }

    return (
        <section className="border-t pt-6">
            <h2 className="text-sm font-medium">{t('Tüm eklentiler')}</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {t(
                    'Sisteminize kurabileceğiniz eklentiler. Kurulu olanlar işaretlidir; diğerleri için kurulum ve fiyat bilgisini inceleyin.',
                )}
            </p>

            <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {catalog.map((item) => (
                    <li
                        key={item.key}
                        className="flex flex-col gap-3 rounded-lg border p-4"
                    >
                        <div className="flex items-center gap-2">
                            <Blocks className="size-4 text-muted-foreground" />
                            <h3 className="text-sm font-medium">{item.name}</h3>
                            {item.installed && (
                                <Badge className="ml-auto">
                                    <CircleCheck />
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

export default function Modules({ modules, catalog }: Props) {
    const [disabling, setDisabling] = useState<InstalledModule | null>(null);
    const [processing, setProcessing] = useState(false);

    const confirmDisable = () => {
        if (!disabling) {
            return;
        }

        setProcessing(true);
        router.patch(
            update(disabling.key).url,
            { enabled: false },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setDisabling(null);
                },
            },
        );
    };

    return (
        <>
            <Head title={t('Eklentiler')} />

            <div className="flex max-w-5xl flex-col gap-8">
                <div className="border-b pb-5">
                    <h1 className="text-xl font-semibold">{t('Eklentiler')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Kurulu eklentileri açıp kapatın. Kapatılan eklentinin menüsü, sayfaları ve özellikleri devre dışı kalır; verileri silinmez.',
                        )}
                    </p>
                </div>

                <section>
                    <h2 className="text-sm font-medium">
                        {t('Kurulu eklentiler')}
                    </h2>

                    {modules.length > 0 ? (
                        <ul className="divide-y">
                            {modules.map((module) => (
                                <ModuleRow
                                    key={module.key}
                                    module={module}
                                    onRequestDisable={setDisabling}
                                />
                            ))}
                        </ul>
                    ) : (
                        <p className="py-5 text-sm text-muted-foreground">
                            {t(
                                'Kurulu eklenti yok. Sistem varsayılan B2B özellikleriyle çalışıyor.',
                            )}
                        </p>
                    )}
                </section>

                <Deferred
                    data="catalog"
                    fallback={
                        <p className="flex items-center gap-2 border-t pt-6 text-sm text-muted-foreground">
                            <Spinner />
                            {t('Eklenti listesi yükleniyor…')}
                        </p>
                    }
                >
                    <Catalog catalog={catalog ?? []} />
                </Deferred>
            </div>

            <AlertDialog
                open={disabling !== null}
                onOpenChange={(open) =>
                    !open && !processing && setDisabling(null)
                }
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {t(':name eklentisi kapatılsın mı?', {
                                name: disabling?.name ?? '',
                            })}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {t(
                                'Eklentinin menüsü, sayfaları ve özellikleri devre dışı kalır. Verileri silinmez; yeniden açtığınızda kaldığı yerden devam eder.',
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>
                            <X />
                            {t('Vazgeç')}
                        </AlertDialogCancel>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={confirmDisable}
                        >
                            {processing ? <Spinner /> : <Power />}
                            {t('Kapat')}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

Modules.layout = {
    breadcrumbs: [{ title: t('Eklentiler'), href: index() }],
};
