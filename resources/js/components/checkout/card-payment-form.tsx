import { usePage } from '@inertiajs/react';
import { ArrowLeft, Lock, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { t } from '@/lib/i18n';
import type {
    CardPaymentAddress,
    CardPaymentPageProps,
} from '@/types/card-payment';
import { CardVisual, cardBrand, formatCardNumber } from './card-visual';
import type { CheckoutKit } from './kit';

type Props = CardPaymentPageProps & {
    kit: CheckoutKit;
    /** Kart resminin zemin sınıfları (tema rengi); yoksa varsayılan koyu zemin. */
    cardClassName?: string;
};

const digits = (value: string, max: number) =>
    value.replace(/\D/g, '').slice(0, max);

/** "1226" → "12/26" (yazarken). */
const formatExpiry = (value: string) => {
    const d = digits(value, 4);

    return d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d;
};

function AddressLines({ address }: { address: CardPaymentAddress }) {
    const place = [address.district, address.city].filter(Boolean).join(' / ');

    return (
        <>
            {address.name ? (
                <div className="font-semibold">{address.name}</div>
            ) : null}
            {address.company ? <div>{address.company}</div> : null}
            {address.tax_office || address.tax_number ? (
                <div className="opacity-80">
                    {[address.tax_office, address.tax_number]
                        .filter(Boolean)
                        .join(' · ')}
                </div>
            ) : null}
            {address.identity_number ? (
                <div className="opacity-80">
                    {t('T.C. :number', { number: address.identity_number })}
                </div>
            ) : null}
            {address.address ? <div>{address.address}</div> : null}
            {place || address.postal_code ? (
                <div>
                    {[place, address.postal_code].filter(Boolean).join(' ')}
                </div>
            ) : null}
            {address.phone ? (
                <div className="opacity-80">{address.phone}</div>
            ) : null}
        </>
    );
}

function InfoBlock({
    kit,
    title,
    children,
}: {
    kit: CheckoutKit;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="min-w-0">
            <div className="mb-1.5 text-xs font-bold tracking-wide uppercase opacity-70">
                {title}
            </div>
            <kit.AddressPreview>
                <div className="space-y-0.5 leading-relaxed break-words">
                    {children}
                </div>
            </kit.AddressPreview>
        </div>
    );
}

/**
 * Kart ekranı (ödeme formundan sonra): girilen bilgiler, siparişin son hali ve
 * kart formu. Form düz HTML gönderimidir (Inertia değil): sürücünün yanıtı
 * bankanın 3D doğrulama sayfası olabilir. Kart bilgisi sunucuda saklanmaz.
 */
export function CardPaymentForm({
    kit,
    cardClassName,
    payment,
    order,
    action,
    csrfToken,
    backUrl,
    notice,
    maxInstallment,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    const [number, setNumber] = useState('');
    const [holder, setHolder] = useState('');
    const [expiry, setExpiry] = useState('');
    const [cvv, setCvv] = useState('');
    const [flipped, setFlipped] = useState(false);
    const [installment, setInstallment] = useState(1);
    const [processing, setProcessing] = useState(false);

    const amex = cardBrand(number) === 'amex';
    const expiryError = errors.card_month ?? errors.card_year;

    return (
        <form
            method="post"
            action={action}
            className={kit.gridClass}
            autoComplete="on"
            onSubmit={() => setProcessing(true)}
        >
            <input type="hidden" name="_token" value={csrfToken} />
            <div className="flex min-w-0 flex-col gap-5">
                {notice ? (
                    <div
                        role="alert"
                        className="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        {notice}
                    </div>
                ) : null}

                {order ? (
                    <kit.Step title={t('Sipariş Bilgileriniz')} step={1}>
                        <div className="grid gap-4 text-[13px] sm:grid-cols-3">
                            <InfoBlock kit={kit} title={t('İletişim')}>
                                <div className="break-all">{order.email}</div>
                                {order.phone ? <div>{order.phone}</div> : null}
                            </InfoBlock>
                            <InfoBlock kit={kit} title={t('Teslimat Adresi')}>
                                <AddressLines address={order.shipping} />
                            </InfoBlock>
                            <InfoBlock kit={kit} title={t('Fatura Bilgileri')}>
                                <AddressLines address={order.billing} />
                            </InfoBlock>
                        </div>
                        {order.carrier || order.customer_note ? (
                            <div className="mt-3 space-y-1 text-[13px]">
                                {order.carrier ? (
                                    <div>
                                        <span className="opacity-70">
                                            {t('Kargo:')}
                                        </span>{' '}
                                        {order.carrier}
                                    </div>
                                ) : null}
                                {order.customer_note ? (
                                    <div>
                                        <span className="opacity-70">
                                            {t('Sipariş notu:')}
                                        </span>{' '}
                                        {order.customer_note}
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </kit.Step>
                ) : null}

                <kit.Step title={t('Kart Bilgileri')} step={order ? 2 : 1}>
                    <div className="grid items-start gap-6 md:grid-cols-2">
                        <CardVisual
                            number={number}
                            holder={holder}
                            expiry={expiry}
                            cvv={cvv}
                            flipped={flipped}
                            className={cardClassName}
                        />
                        <div className="grid gap-3">
                            <kit.TextField
                                label={t('Kart Üzerindeki Ad Soyad')}
                                name="card_holder"
                                value={holder}
                                onChange={(e) =>
                                    setHolder(
                                        e.target.value
                                            .toLocaleUpperCase('tr-TR')
                                            .slice(0, 60),
                                    )
                                }
                                onFocus={() => setFlipped(false)}
                                required
                                autoComplete="cc-name"
                                error={errors.card_holder}
                            />
                            <kit.TextField
                                label={t('Kart Numarası')}
                                name="card_number"
                                value={formatCardNumber(number)}
                                onChange={(e) =>
                                    setNumber(digits(e.target.value, 19))
                                }
                                onFocus={() => setFlipped(false)}
                                required
                                inputMode="numeric"
                                autoComplete="cc-number"
                                placeholder="0000 0000 0000 0000"
                                maxLength={23}
                                error={errors.card_number}
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <kit.TextField
                                    label={t('Son Kullanma (AA/YY)')}
                                    name="card_expiry"
                                    value={expiry}
                                    onChange={(e) =>
                                        setExpiry(formatExpiry(e.target.value))
                                    }
                                    onFocus={() => setFlipped(false)}
                                    required
                                    inputMode="numeric"
                                    autoComplete="cc-exp"
                                    placeholder={t('AA/YY')}
                                    maxLength={5}
                                    error={expiryError}
                                />
                                <kit.TextField
                                    label={t('Güvenlik Kodu (CVV)')}
                                    name="card_cvv"
                                    value={cvv}
                                    onChange={(e) =>
                                        setCvv(
                                            digits(
                                                e.target.value,
                                                amex ? 4 : 3,
                                            ),
                                        )
                                    }
                                    onFocus={() => setFlipped(true)}
                                    onBlur={() => setFlipped(false)}
                                    required
                                    inputMode="numeric"
                                    autoComplete="cc-csc"
                                    placeholder={amex ? '0000' : '000'}
                                    maxLength={4}
                                    error={errors.card_cvv}
                                />
                            </div>
                            {maxInstallment > 1 ? (
                                <div>
                                    <input
                                        type="hidden"
                                        name="installment"
                                        value={installment}
                                    />
                                    <div className="mb-2 text-[13px] font-semibold">
                                        {t('Taksit')}
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        {Array.from(
                                            { length: maxInstallment },
                                            (_, i) => i + 1,
                                        ).map((count) => (
                                            <kit.Choice
                                                key={count}
                                                name="_installment"
                                                checked={installment === count}
                                                onChange={() =>
                                                    setInstallment(count)
                                                }
                                                card
                                            >
                                                {count === 1
                                                    ? t('Tek çekim')
                                                    : t(':count taksit', {
                                                          count,
                                                      })}
                                            </kit.Choice>
                                        ))}
                                    </div>
                                    {errors.installment ? (
                                        <kit.Note tone="error">
                                            {errors.installment}
                                        </kit.Note>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    </div>
                    <kit.Note>
                        <ShieldCheck className="mr-1 inline size-3.5 align-[-2px]" />
                        {t(
                            'Kart bilgileriniz 3D Secure ile bankanızda doğrulanır; mağazada saklanmaz.',
                        )}
                    </kit.Note>
                </kit.Step>
            </div>

            <aside className={kit.asideClass}>
                <kit.Summary>
                    {order ? (
                        <>
                            <kit.SummaryLines lines={order.lines} />
                            <kit.Totals
                                totals={order.totals}
                                carrier={null}
                                shippingFormatted={
                                    order.totals.shipping_formatted
                                }
                                totalFormatted={order.totals.total_formatted}
                            />
                            {order.adjustment ? (
                                <kit.Note>
                                    {order.adjustment.label}:{' '}
                                    {order.adjustment.amount_formatted}
                                </kit.Note>
                            ) : null}
                        </>
                    ) : (
                        <p className="text-[13px]">{payment.description}</p>
                    )}
                    {/* Genel toplamdan farklıysa (ödeme farkı) ya da sipariş yoksa ayrıca gösterilir. */}
                    {!order || order.adjustment ? (
                        <div className="mt-4 flex items-baseline justify-between gap-3 border-t pt-3">
                            <span className="text-sm font-bold">
                                {t('Ödenecek Tutar')}
                            </span>
                            <span className="text-lg font-extrabold tabular-nums">
                                {payment.amount_formatted}
                            </span>
                        </div>
                    ) : null}
                    <div className="mt-5 flex flex-col gap-3">
                        <kit.SubmitButton
                            processing={processing}
                            className="w-full"
                        >
                            <Lock className="size-4" /> {t('Ödeme Yap')}
                        </kit.SubmitButton>
                        <a
                            href={backUrl}
                            className={`inline-flex items-center justify-center gap-1 text-[13px] ${kit.linkClass}`}
                        >
                            <ArrowLeft className="size-3.5" />
                            {order?.number
                                ? t('Sipariş sayfasına dön')
                                : order
                                  ? t('Ödeme formuna dön')
                                  : t('Geri dön')}
                        </a>
                    </div>
                </kit.Summary>
            </aside>
        </form>
    );
}
