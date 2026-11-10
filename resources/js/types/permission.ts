/** Yetkiler ekranındaki izin satırı (PermissionResource). */
export type Permission = {
    uuid: string;
    name: string;
    group: string | null;
    label: string | null;
    roles_count: number;
};

/** Rol atama ızgaralarında bir grup altındaki izin (ad + etiket). */
export type PermissionRow = {
    name: string;
    label: string;
    /** Doğrudan ön koşullar (bölümü görme yetkileri). */
    requires?: string[];
};

/** "Rotalardan Keşfet" ekranındaki, henüz izne dönüşmemiş aday panel rotası. */
export type DiscoverableRoute = {
    name: string;
    suggested_group: string;
    suggested_label: string;
};
