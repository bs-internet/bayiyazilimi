import { useState } from 'react';

import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';
import type { CreditTermOptions, CreditTermsValues } from '@/types';

const INHERIT = '__inherit';

function TermSelect({
    name,
    label,
    value,
    onChange,
    options,
    override,
    error,
}: {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    override: boolean;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <input
                type="hidden"
                name={name}
                value={value === INHERIT ? '' : value}
            />
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {override && (
                        <SelectItem value={INHERIT}>{t('Gruptan')}</SelectItem>
                    )}
                    {options.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

/**
 * Cari çalışma şekli alanları: peşin / vadeli, kredi limiti,
 * vade günü, limit aşımı ve vadesi geçmiş borçta davranış, izinli ödeme
 * yöntemleri. Bayi grubunda zorunlu; bayide (`override`) boş = gruptan.
 */
export function CreditTermsFields({
    values,
    options,
    override = false,
    errors,
}: {
    values?: CreditTermsValues | null;
    options: CreditTermOptions;
    override?: boolean;
    errors: Partial<Record<string, string>>;
}) {
    const initial = (value: string | null | undefined, fallback: string) =>
        value ?? (override ? INHERIT : fallback);

    const [terms, setTerms] = useState(
        initial(values?.payment_terms, 'prepaid'),
    );
    const [overLimit, setOverLimit] = useState(
        initial(values?.over_limit_action, 'approval'),
    );
    const [overdue, setOverdue] = useState(
        initial(values?.overdue_action, 'approval'),
    );
    const [methods, setMethods] = useState<string[]>(
        values?.allowed_payment_methods ?? [],
    );

    const toggle = (key: string, checked: boolean) =>
        setMethods((current) =>
            checked ? [...current, key] : current.filter((m) => m !== key),
        );

    return (
        <div className="flex flex-col gap-4 rounded-lg border p-4">
            <div>
                <div className="text-sm font-semibold">
                    {t('Cari çalışma şekli')}
                </div>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Peşin: sipariş ödeme gelince işlenir. Vadeli: limit dahilinde sipariş verir, vadesi gelince öder.',
                    )}
                    {override &&
                        t(' Boş bırakılan alanlar bayi grubundan gelir.')}
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <TermSelect
                    name="payment_terms"
                    label={t('Çalışma şekli')}
                    value={terms}
                    onChange={setTerms}
                    options={options.terms}
                    override={override}
                    error={errors.payment_terms}
                />
                <div className="grid gap-2">
                    <Label htmlFor="payment_term_days">{t('Vade (gün)')}</Label>
                    <Input
                        id="payment_term_days"
                        name="payment_term_days"
                        type="number"
                        min={0}
                        max={3650}
                        placeholder={override ? t('Gruptan') : '30'}
                        defaultValue={
                            values?.payment_term_days ?? (override ? '' : 30)
                        }
                        required={!override}
                    />
                    <InputError message={errors.payment_term_days} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="credit_limit">{t('Kredi limiti')}</Label>
                    <Input
                        id="credit_limit"
                        name="credit_limit"
                        type="number"
                        min={0}
                        step="0.01"
                        placeholder={
                            override ? t('Gruptan') : t('Boş: limitsiz')
                        }
                        defaultValue={values?.credit_limit ?? ''}
                    />
                    <InputError message={errors.credit_limit} />
                </div>
                <TermSelect
                    name="over_limit_action"
                    label={t('Limit aşılınca')}
                    value={overLimit}
                    onChange={setOverLimit}
                    options={options.over_limit}
                    override={override}
                    error={errors.over_limit_action}
                />
                <TermSelect
                    name="overdue_action"
                    label={t('Vadesi geçmiş borç varken')}
                    value={overdue}
                    onChange={setOverdue}
                    options={options.overdue}
                    override={override}
                    error={errors.overdue_action}
                />
            </div>

            {options.methods.length > 0 && (
                <div className="grid gap-2">
                    <Label>{t('İzinli ödeme yöntemleri')}</Label>
                    {methods.map((m) => (
                        <input
                            key={m}
                            type="hidden"
                            name="allowed_payment_methods[]"
                            value={m}
                        />
                    ))}
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        {options.methods.map((m) => (
                            <label
                                key={m.value}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={methods.includes(m.value)}
                                    onCheckedChange={(v) =>
                                        toggle(m.value, v === true)
                                    }
                                />
                                {m.label}
                            </label>
                        ))}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {t('Hiçbiri işaretlenmezse')}{' '}
                        {override
                            ? t('grubun ayarı geçerlidir')
                            : t('tümü sunulur')}
                        {t(
                            '. Vadeli seçeneği yalnız vadeli çalışan bayiye çıkar.',
                        )}
                    </p>
                    <InputError message={errors.allowed_payment_methods} />
                </div>
            )}
        </div>
    );
}
