import { useBranding } from '@/hooks/use-branding';

/**
 * Panel içeriğinin altbilgisi: solda ürün sürümü, sağda lisans atfı.
 *
 * Atıf AGPLv3 madde 7(b) ek koşulu gereği yönetim panelinde görünür kalmalıdır;
 * kaldırılamaz ve gizlenemez (bkz. EK-KOSULLAR.md). Müşterinin markasından
 * bağımsızdır.
 */
export function AppFooter() {
    const { version } = useBranding();

    return (
        <footer className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t px-4 py-3 text-xs text-muted-foreground md:px-6">
            <span>Bayi Yazılımı v{version}</span>
            <span>
                <a
                    href="https://bayiyazilimi.com"
                    target="_blank"
                    rel="noopener"
                    className="underline-offset-4 hover:text-foreground hover:underline"
                >
                    Bayi Yazılımı
                </a>
                {' · © Bülent Sakarya'}
            </span>
        </footer>
    );
}
