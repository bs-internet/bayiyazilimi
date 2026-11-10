export type Address = {
    address: string | null;
    postal_code: string | null;
    district_id: string | null;
    city_id: string | null;
    country_id: string | null;
};

/** Fatura türü: bireysel (TC kimlik no) ya da kurumsal (vergi dairesi + no). */
export type BillingType = 'individual' | 'corporate';

export type Account = {
    id: string;
    code: string;
    /** Bireyselde ad soyad, kurumsalda firma ünvanı. */
    title: string | null;
    billing_type: BillingType;
    tax_office: string | null;
    tax_number: string | null;
    identity_number: string | null;
    invoice_email: string | null;
    address: Address | null;
};
