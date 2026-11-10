import { router, usePage } from '@inertiajs/react';
import { RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { update } from '@/routes/panel/system';

/**
 * Kod yeni sürüme geçmiş ama veritabanı / eklenti kurulum adımları çalışmamışsa
 * (paylaşımlı hostingte dosyalar yüklendikten sonra) Super Admin'e gösterilir.
 * Düğme `bayi:update` ile aynı adımları çalıştırır.
 */
export function SystemUpdateBanner() {
    const { systemUpdate } = usePage().props;
    const [processing, setProcessing] = useState(false);

    if (!systemUpdate) {
        return null;
    }

    return (
        <div className="mx-4 mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 md:mx-6 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            <TriangleAlert className="size-5 shrink-0" />
            <p className="min-w-0 flex-1">
                {t(
                    'Sistem güncellemesi tamamlanmadı: veritabanı ya da eklenti kurulum adımları bekliyor. Güncellemeden önce yedek aldığınızdan emin olun.',
                )}
            </p>
            <Button
                size="sm"
                disabled={processing}
                onClick={() =>
                    router.post(
                        update().url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                        },
                    )
                }
            >
                {processing ? (
                    <Spinner data-icon="inline-start" />
                ) : (
                    <RefreshCw data-icon="inline-start" />
                )}
                {t('Güncellemeyi tamamla')}
            </Button>
        </div>
    );
}
