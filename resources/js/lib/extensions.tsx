import { usePage } from '@inertiajs/react';
import type { ComponentType } from 'react';

/**
 * Ön yüz uzantı yuvaları. Çekirdek ekranlar adlandırılmış bir yuva (`<Slot>`)
 * bırakır; modüller kendi bileşenlerini `registerSlot()` ile o yuvaya koyar.
 * Modüllerin kayıtları `resources/js/extensions.ts` (modül paketinde) dosyasında
 * yapılır; çekirdek bu dosyaları uygulama açılırken yükler (bkz. ../extensions.ts).
 *
 * Her kayıt, onu yapan modül paketiyle (ör. `bsinternet/vitrin`) işaretlenir.
 * Panelden kapatılan modülün dosyaları derlemede kalır ama kayıtları gösterilmez:
 * `<Slot>` yalnız açık modüllerin (paylaşılan `modules` prop'u) bileşenlerini çizer.
 *
 * Bilinen yuvalar:
 * - `product-form.main`     ürün formu, ana sütun (bölüm kartları)
 * - `product-form.publish`  ürün formu, "Yayın" kartı (alanlar)
 * - `products.filters`      ürün listesi süzgeç çubuğu
 * - `top-bar.actions`       panel ve üye alanının üst çubuğu, sağdaki düğmeler
 * - `orders.show.sidebar`   panel sipariş ekranı, sağ sütun (Fatura kartının altı);
 *                           props: `order`, `extensions` (backend: `panel.orders.show.extensions` filtresi)
 */

type Entry = {
    component: ComponentType<never>;
    order: number;
    /** Kaydı yapan modül paketi; çekirdeğin kendi kaydıysa null. */
    module: string | null;
};

const slots = new Map<string, Entry[]>();

let loadingModule: string | null = null;

export function registerSlot<P extends object>(
    name: string,
    component: ComponentType<P>,
    order = 100,
): void {
    const list = slots.get(name) ?? [];
    list.push({
        component: component as ComponentType<never>,
        order,
        module: loadingModule,
    });
    list.sort((a, b) => a.order - b.order);
    slots.set(name, list);
}

/**
 * Modüllerin `extensions.ts` dosyalarını sırayla yükler; her dosyanın yaptığı
 * kayıtlar yolundaki paket adıyla (`vendor/{vendor}/{paket}/…`) işaretlenir.
 */
export async function loadModuleExtensions(
    loaders: Record<string, () => Promise<unknown>>,
): Promise<void> {
    for (const [path, load] of Object.entries(loaders)) {
        loadingModule = /vendor\/([^/]+\/[^/]+)\//.exec(path)?.[1] ?? null;

        try {
            await load();
        } finally {
            loadingModule = null;
        }
    }
}

function activeEntries(name: string, modules: string[]): Entry[] {
    return (slots.get(name) ?? []).filter(
        (entry) => entry.module === null || modules.includes(entry.module),
    );
}

/** Yuvada açık bir modülün (ya da çekirdeğin) bileşeni var mı? */
export function useHasSlot(name: string): boolean {
    const { modules = [] } = usePage().props;

    return activeEntries(name, modules).length > 0;
}

export function Slot<P extends object>({
    name,
    ...props
}: { name: string } & P) {
    const { modules = [] } = usePage().props;

    return (
        <>
            {activeEntries(name, modules).map(({ component }, i) => {
                const Component = component as ComponentType<typeof props>;

                return <Component key={i} {...props} />;
            })}
        </>
    );
}
