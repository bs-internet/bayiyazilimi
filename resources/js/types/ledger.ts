/**
 * Cari hesap — backend: App\Support\Ledger\LedgerPresenter.
 */

/** Bakiye görünümü: tutar (mutlak, biçimli) + yön. */
export type LedgerBalance = {
    amount: string;
    formatted: string;
    side: 'debit' | 'credit' | 'zero';
    side_label: string;
};

export type LedgerRow = {
    id: string;
    date: string;
    due_date: string | null;
    type: string;
    type_label: string;
    description: string | null;
    debit: string | null;
    credit: string | null;
    /** Belgenin kendi para birimindeki tutarı (ana birimden farklıysa). */
    original: string | null;
    balance: LedgerBalance;
    order: { id: string; number: string } | null;
    reversed: boolean;
    is_reversal: boolean;
    reversible: boolean;
    user: string | null;
    origin: string | null;
};

/** Vadesi geçmiş / yaklaşan açık borç (sipariş). */
export type LedgerDue = {
    id: string;
    order: { id: string; number: string } | null;
    due_date: string;
    days: number;
    open: string;
    total: string;
};

export type LedgerSummary = LedgerBalance & {
    balance: string;
    /** Vadeli çalışma (Aşama 3): limit, kullanılabilir limit, risk, vadesi geçen. */
    terms?: 'prepaid' | 'credit';
    terms_label?: string;
    credit_limit?: string | null;
    available?: string | null;
    over_limit?: boolean;
    risk?: string;
    overdue?: string | null;
    overdue_count?: number;
    term_days?: number | null;
    pending_approval?: string | null;
};

/** Cari çalışma şekli alanları (bayi grubu / bayi; bayide boş = gruptan). */
export type CreditTermsValues = {
    payment_terms: string | null;
    credit_limit: string | null;
    payment_term_days: number | null;
    over_limit_action: string | null;
    overdue_action: string | null;
    allowed_payment_methods: string[] | null;
};

export type CreditTermOptions = {
    terms: { value: string; label: string }[];
    over_limit: { value: string; label: string }[];
    overdue: { value: string; label: string }[];
    methods: { value: string; label: string }[];
};

/** Borcu kapanmamış sipariş (tahsilatta seçim listesi). */
export type LedgerOpenOrder = {
    id: string;
    number: string;
    open: string;
    due_date: string | null;
};

export type LedgerData = {
    account_id: string;
    currency: string;
    summary: LedgerSummary;
    filters: { from: string | null; to: string | null };
    open_orders: LedgerOpenOrder[];
    statement: {
        opening: LedgerBalance;
        closing: LedgerBalance;
        debit_total: string;
        credit_total: string;
        rows: LedgerRow[];
    };
    /** Vadesi geçmiş ve yaklaşan açık borçlar (Aşama 3). */
    dues?: { overdue: LedgerDue[]; upcoming: LedgerDue[] };
};
