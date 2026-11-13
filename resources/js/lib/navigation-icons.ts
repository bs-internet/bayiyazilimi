import {
    Activity,
    Archive,
    Blocks,
    BookMarked,
    Bolt,
    Building2,
    ChartColumn,
    ClipboardList,
    Coins,
    Factory,
    Cookie,
    CreditCard,
    FileText,
    Images,
    KeyRound,
    Landmark,
    Languages,
    Palette,
    Wallet,
    LayoutDashboard,
    LayoutGrid,
    MapPin,
    Menu,
    Package,
    PanelsTopLeft,
    Percent,
    Phone,
    Ruler,
    ScanText,
    ShieldCheck,
    ShoppingBag,
    ShoppingBasket,
    ShoppingCart,
    SlidersHorizontal,
    SquareUser,
    Tag,
    Store,
    Truck,
    Undo2,
    UserRound,
    Users,
    Warehouse,
    Webhook,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Backend (MenuRegistry) ikonu string anahtar olarak gönderir; React burada
 * Lucide bileşenine çözer. Böylece PHP↔JS sınırından bileşen referansı geçmez.
 *
 * Modüller yeni bir ikon kullanmak istediğinde anahtarını buraya ekler.
 */
export const navigationIcons: Record<string, LucideIcon> = {
    activity: Activity,
    archive: Archive,
    blocks: Blocks,
    palette: Palette,
    bookMarked: BookMarked,
    bolt: Bolt,
    building: Building2,
    chartColumn: ChartColumn,
    clipboardList: ClipboardList,
    coins: Coins,
    factory: Factory,
    creditCard: CreditCard,
    fileText: FileText,
    images: Images,
    keyRound: KeyRound,
    webhook: Webhook,
    landmark: Landmark,
    wallet: Wallet,
    languages: Languages,
    layoutDashboard: LayoutDashboard,
    layoutGrid: LayoutGrid,
    mapPin: MapPin,
    menu: Menu,
    package: Package,
    panelsTopLeft: PanelsTopLeft,
    percent: Percent,
    phone: Phone,
    ruler: Ruler,
    scanText: ScanText,
    shieldCheck: ShieldCheck,
    shoppingBag: ShoppingBag,
    shoppingBasket: ShoppingBasket,
    shoppingCart: ShoppingCart,
    slidersHorizontal: SlidersHorizontal,
    squareUser: SquareUser,
    store: Store,
    cookie: Cookie,
    tag: Tag,
    truck: Truck,
    undo2: Undo2,
    userRound: UserRound,
    users: Users,
    warehouse: Warehouse,
};

/**
 * String anahtarı veya doğrudan verilen bileşeni Lucide bileşenine çözer.
 */
export function resolveNavIcon(
    icon: string | LucideIcon | null | undefined,
): LucideIcon | null {
    if (!icon) {
        return null;
    }

    if (typeof icon !== 'string') {
        return icon;
    }

    return navigationIcons[icon] ?? null;
}
