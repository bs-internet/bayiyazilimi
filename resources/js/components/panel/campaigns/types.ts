import type { useForm } from '@inertiajs/react';

import type { ProductPick, Status } from '@/types';

export type Option = { value: string; label: string };

export type ConditionDef = {
    key: string;
    label: string;
    scope: 'audience' | 'product';
    operators: Option[];
    input: 'select' | 'multi' | 'number' | 'products' | 'text';
    options: Option[];
    unit: string | null;
};

export type ActionField = {
    key: string;
    label: string;
    type: 'number' | 'money' | 'select' | 'product' | 'tiers';
    options?: Option[];
    suffix?: string;
    help?: string;
};

export type ActionDef = { key: string; label: string; fields: ActionField[] };

export type RuleValue = string | string[];

export type Rule = { field: string; operator: string; value: RuleValue };

export type CampaignData = {
    id: string;
    name: string;
    badge: string | null;
    starts_at: string | null;
    ends_at: string | null;
    sort_order: number;
    status: Status;
    audience_rules: Rule[][];
    product_scope: 'all' | 'match';
    product_rules: Rule[];
    action: string;
    settings: Record<string, string | number | null | unknown[]>;
    shipping_mode: ShippingMode;
    shipping_amount: string | null;
    currency_code: string | null;
    combinable: string[];
    usage_limit: number | null;
    usage_limit_per_customer: number | null;
    budget: string | null;
    /** Kullanım özeti (iptal / başarısız siparişler hariç). */
    usage: { count: number; value: string };
};

export type Props = {
    campaign: CampaignData | null;
    conditions: ConditionDef[];
    actions: ActionDef[];
    products: Record<string, ProductPick>;
    /** İşlem tutarlarının para birimi seçenekleri. */
    currencies: (Option & { symbol: string })[];
    /** Varsayılan para birimi kodu (sabit kargo her zaman bu birimde). */
    baseCurrency: string;
    /** Birlikte uygulanabilecek diğer kampanyalar. */
    otherCampaigns: Option[];
    /** Vitrin kurulu mu? "Vitrin etiketi" yalnız o zaman çıkar. */
    storefront: boolean;
};

export type ShippingMode = 'cart' | 'free' | 'fixed';

/** Yalnız kargo uygulayan işlem (indirim yok). */
export const SHIPPING_ACTION = 'shipping';

export type FormData = {
    name: string;
    badge: string;
    starts_at: string;
    ends_at: string;
    sort_order: string;
    audience_rules: Rule[][];
    product_scope: 'all' | 'match';
    product_rules: Rule[];
    action: string;
    settings: Record<string, string>;
    shipping_mode: ShippingMode;
    shipping_amount: string;
    currency_code: string;
    combinable: string[];
    usage_limit: string;
    usage_limit_per_customer: string;
    budget: string;
};

/** Koşulun ilk mantığı ve boş değeriyle yeni satır. */
export function newRule(def: ConditionDef): Rule {
    return {
        field: def.key,
        operator: def.operators[0]?.value ?? '',
        value: def.input === 'multi' || def.input === 'products' ? [] : '',
    };
}

/** İşlemin seçim alanları ilk seçenekle başlar (formda görünen değer gönderilsin). */
export function defaultSettings(action?: ActionDef): Record<string, string> {
    return Object.fromEntries(
        (action?.fields ?? [])
            .filter((f) => f.type === 'select' && f.options?.length)
            .map((f) => [f.key, f.options![0].value]),
    );
}

/** Form bölümlerine verilen ortak durum. */
export type SectionProps = {
    form: ReturnType<typeof useForm<FormData>>;
    data: FormData;
    setData: ReturnType<typeof useForm<FormData>>['setData'];
    errors: Record<string, string>;
    campaign: CampaignData | null;
    actions: ActionDef[];
    action: ActionDef | undefined;
    audienceDefs: ConditionDef[];
    productDefs: ConditionDef[];
    products: Record<string, ProductPick>;
    rememberProducts: (picked: ProductPick[]) => void;
    setGroups: (groups: Rule[][]) => void;
    hasMoney: boolean;
    symbolOf: (code: string) => string;
    currencies: Props['currencies'];
    baseCurrency: string;
    otherCampaigns: Option[];
    storefront: boolean;
};
