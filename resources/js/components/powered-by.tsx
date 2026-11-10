import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Lisans atfı: "Bayi Yazılımı altyapısıyla çalışır · © Bülent Sakarya".
 *
 * AGPLv3 madde 7(b) ek koşulu gereği giriş ekranında görünür kalmalıdır;
 * kaldırılamaz ve gizlenemez (bkz. EK-KOSULLAR.md). Yönetim panelindeki atıf
 * içerik altbilgisindedir (panel/app-footer.tsx).
 */
export function PoweredBy({ className }: { className?: string }) {
    return (
        <p className={cn('text-xs text-muted-foreground', className)}>
            <a
                href="https://bayiyazilimi.com"
                target="_blank"
                rel="noopener"
                className="underline-offset-4 hover:text-foreground hover:underline"
            >
                {t('Bayi Yazılımı altyapısıyla çalışır')}
            </a>
            {' · © Bülent Sakarya'}
        </p>
    );
}
