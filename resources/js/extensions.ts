/**
 * Modüllerin ön yüz uzantıları: her modül paketi isterse
 * `resources/js/extensions.ts` dosyasında yuva kaydı yapar (bkz.
 * lib/extensions.tsx). Uygulama açılırken (app.tsx, ssr.tsx) hepsi yüklenir;
 * kapalı modüllerin kayıtları `<Slot>` tarafından gösterilmez.
 * Globlar statik literaldir; vendor listesi config/bayiyazilimi.php
 * `module_vendors` ile aynı tutulur.
 */
import { loadModuleExtensions } from '@/lib/extensions';

await loadModuleExtensions(
    import.meta.glob(['../../vendor/bsinternet/*/resources/js/extensions.ts']),
);
