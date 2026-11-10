import type { InertiaFormProps } from '@inertiajs/react';

import type { Line } from './lines';

export type QuoteHistory = {
    id: string;
    status: string;
    label: string;
    note: string | null;
    by_customer: boolean;
    user: string | null;
    created_at: string | null;
};

export type QuoteData = {
    id: string;
    number: string;
    source: 'request' | 'admin';
    status: string;
    status_label: string;
    editable: boolean;
    can_cancel: boolean;
    can_delete: boolean;
    expired: boolean;
    user_id: string | null;
    customer: {
        name: string;
        company: string | null;
        email: string;
        phone: string | null;
        city: string | null;
        member: boolean;
    };
    customer_note: string | null;
    admin_note: string | null;
    currency_code: string;
    valid_from: string | null;
    valid_until: string | null;
    shipping_total: string;
    sent_at: string | null;
    created_at: string | null;
    creator: string | null;
    public_url: string | null;
    order: { id: string; number: string } | null;
    items: Omit<Line, 'gross_price' | 'total_price'>[];
    histories: QuoteHistory[];
};

export type QuoteFormData = {
    user_id: string;
    currency_code: string;
    admin_note: string;
    shipping_total: number | string;
    valid_from: string;
    valid_until: string;
    items: Line[];
    send: boolean;
};

export type QuoteForm = InertiaFormProps<QuoteFormData>;
