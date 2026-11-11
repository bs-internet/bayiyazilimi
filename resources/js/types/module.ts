/** Kurulu bir modül (ModuleService::installed). */
export type InstalledModule = {
    key: string;
    name: string;
    description: string | null;
    version: string;
    author: string | null;
    enabled: boolean;
    /** İlk kurulum (bayi:install) tarihi; kayıt yoksa null. */
    installed_at: string | null;
    /**
     * Kurulum durumu: `done` tamam, `pending` bayi:install çalışmamış,
     * `update` yeni sürümün kurulumu çalışmamış.
     */
    setup: 'done' | 'pending' | 'update';
    /** Bu modülün ihtiyaç duyduğu modüllerin adları. */
    requires: string[];
    /** Bu modüle ihtiyaç duyan kurulu modüllerin adları. */
    required_by: string[];
};

/** Tanıtılan modül (market.bayiyazilimi.com/modules.json listesi). */
export type CatalogModule = {
    key: string;
    name: string;
    description: string | null;
    url: string | null;
    /** Eklenti ya da tema. */
    type: 'extension' | 'theme';
    /** Bu kurulumda kurulu mu? */
    installed: boolean;
};

/** Kurulu mağaza teması (ThemesController). */
export type InstalledTheme = {
    key: string;
    name: string;
    description: string | null;
    version: string;
    author: string | null;
};
