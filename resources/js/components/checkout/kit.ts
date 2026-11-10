import type {
    ComponentType,
    InputHTMLAttributes,
    ReactNode,
    TextareaHTMLAttributes,
} from 'react';
import type { BasketTotals } from '@/types';
import type {
    CheckoutCarrier,
    CheckoutLine,
    CheckoutSavedAddress,
} from '@/types/checkout';

export type LocationSelectsProps = {
    prefix: string;
    cities: { id: string; name: string }[];
    defaultCity?: string | null;
    defaultDistrict?: string | null;
    cityError?: string;
    districtError?: string;
};

/**
 * Ödeme formunun görünüm takımı. Form mantığı (CheckoutForm) tek; vitrin
 * kendi bileşenleriyle, bayi paneli shadcn bileşenleriyle çizer.
 */
export type CheckoutKit = {
    /** Numaralı adım kartı. */
    Step: ComponentType<{ title: string; step: number; children: ReactNode }>;
    TextField: ComponentType<
        InputHTMLAttributes<HTMLInputElement> & {
            label: string;
            error?: string;
        }
    >;
    TextAreaField: ComponentType<
        TextareaHTMLAttributes<HTMLTextAreaElement> & {
            label: string;
            error?: string;
        }
    >;
    CheckField: ComponentType<{
        label: ReactNode;
        error?: string;
        name?: string;
        value?: string;
        required?: boolean;
        checked?: boolean;
        onCheckedChange?: (checked: boolean) => void;
    }>;
    /** Radyo seçeneği; `card` → kenarlıklı seçim kartı (kargo, ödeme). */
    Choice: ComponentType<{
        name: string;
        value?: string;
        checked: boolean;
        onChange: () => void;
        card?: boolean;
        children: ReactNode;
    }>;
    LocationSelects: ComponentType<LocationSelectsProps>;
    SavedAddressSelect: ComponentType<{
        addresses: CheckoutSavedAddress[];
        value: string;
        onChange: (id: string) => void;
        label: string;
    }>;
    /** Bilgi / uyarı metni. */
    Note: ComponentType<{ tone?: 'muted' | 'error'; children: ReactNode }>;
    /** Salt okunur adres özeti ("fatura adresi = teslimat adresi"). */
    AddressPreview: ComponentType<{ children: ReactNode }>;
    Divider: ComponentType;
    /** Sağ sütun: sipariş özeti kartı (başlık + içerik). */
    Summary: ComponentType<{ children: ReactNode }>;
    SummaryLines: ComponentType<{ lines: CheckoutLine[] }>;
    Totals: ComponentType<{
        totals: BasketTotals;
        carrier: CheckoutCarrier | null;
        shippingFormatted: string;
        totalFormatted: string;
    }>;
    SubmitButton: ComponentType<{
        processing?: boolean;
        className?: string;
        children: ReactNode;
    }>;
    CarrierLogo: ComponentType<{ carrier: CheckoutCarrier }>;
    linkClass: string;
    /** Form ızgarası (ana sütun + özet). */
    gridClass: string;
    asideClass: string;
};
