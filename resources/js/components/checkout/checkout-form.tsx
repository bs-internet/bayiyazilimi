import { Form } from '@inertiajs/react';
import { ChevronDown, CreditCard, Landmark, Lock, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ContractAgreementLabel } from '@/components/contract-agreement';
import { abs, add, isNegative, isZero, percent } from '@/lib/decimal';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { CheckoutPageProps, CheckoutSavedAddress } from '@/types/checkout';
import { InstallmentTable } from './installment-table';
import type { CheckoutKit } from './kit';

type Props = Omit<CheckoutPageProps, 'quote' | 'context'> & {
    kit: CheckoutKit;
};

const METHOD_ICONS = { landmark: Landmark, wallet: Wallet };

/** Özet kartı içindeki bölüm (kargo, ödeme yöntemi). */
function SummarySection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="mt-5 border-t pt-4">
            <h3 className="mb-2 text-sm font-bold">{title}</h3>
            {children}
        </div>
    );
}

const defaultOf = (
    list: CheckoutSavedAddress[],
    flag: 'is_default_shipping' | 'is_default_billing',
) => list.find((a) => a[flag])?.id ?? '';

/**
 * Ödeme formu gövdesi (vitrin ve bayi paneli ortak). Teslimat ve fatura
 * adresleri iki ayrı adımdır: teslimat adresi kayıtlı teslimat adreslerinden,
 * fatura adresi kayıtlı fatura adreslerinden seçilir. "Fatura adresim teslimat
 * adresimle aynı" kısayolu yalnızca adres alanlarını kopyalar (sunucuda);
 * sipariş yine iki ayrı adres bloğu saklar.
 */
export function CheckoutForm({
    kit,
    lines: baseLines,
    totals: baseTotals,
    carriers: baseCarriers,
    methodTotals,
    methods,
    prefill,
    action,
    checkoutToken,
    contracts,
    cities,
    savedAddresses,
    canSaveAddress,
    guestAccount,
}: Props) {
    const shippingList = savedAddresses.filter((a) => a.type === 'shipping');
    const billingList = savedAddresses.filter((a) => a.type === 'billing');

    const [shippingAddressId, setShippingAddressId] = useState(() =>
        defaultOf(shippingList, 'is_default_shipping'),
    );
    const [billingAddressId, setBillingAddressId] = useState(() =>
        defaultOf(billingList, 'is_default_billing'),
    );
    const [billingSame, setBillingSame] = useState(billingList.length === 0);
    // Ziyaretçinin "bu bilgilerle üye ol" seçimi.
    const [createAccount, setCreateAccount] = useState(false);
    const [saveAddress, setSaveAddress] = useState(
        canSaveAddress && savedAddresses.length === 0,
    );

    const shippingSaved =
        shippingList.find((a) => a.id === shippingAddressId) ?? null;
    const billingSaved = billingSame
        ? null
        : (billingList.find((a) => a.id === billingAddressId) ?? null);

    const [billingType, setBillingType] = useState(() => {
        const initial = billingList.find(
            (a) => a.id === defaultOf(billingList, 'is_default_billing'),
        );

        return initial
            ? initial.company
                ? 'corporate'
                : 'individual'
            : prefill.billing.type || 'individual';
    });

    const shipping = shippingSaved
        ? {
              name: shippingSaved.name,
              phone: shippingSaved.phone ?? '',
              address: shippingSaved.address,
              city: shippingSaved.city ?? '',
              district: shippingSaved.district ?? '',
              postal_code: shippingSaved.postal_code ?? '',
          }
        : (prefill.shipping ?? {
              name: '',
              phone: '',
              address: '',
              city: '',
              district: '',
              postal_code: '',
          });

    // Seçili kayıtlı fatura adresi ya da önden doldurulmuş bilgiler.
    const billing = billingSaved
        ? {
              name: billingSaved.name,
              company: billingSaved.company ?? '',
              tax_office: billingSaved.tax_office ?? '',
              tax_number: billingSaved.tax_number ?? '',
              identity_number: billingSaved.identity_number ?? '',
              address: billingSaved.address,
              city: billingSaved.city ?? '',
              district: billingSaved.district ?? '',
              postal_code: billingSaved.postal_code ?? '',
          }
        : {
              ...prefill.billing,
              tax_office: '',
              tax_number: '',
              identity_number: '',
          };

    const [method, setMethod] = useState(methods[0]?.value ?? '');
    // Seçili ödeme yöntemine bağlı kampanya varsa o sepet gösterilir.
    const view = methodTotals?.[method];
    const lines = view?.lines ?? baseLines;
    const totals = view?.totals ?? baseTotals;
    const carriers = view?.carriers ?? baseCarriers;
    const [carriersOpen, setCarriersOpen] = useState(false);
    // Özet kartı ekrana sığıyor mu (üst menü payı ~180px)? Sığmıyorsa alttan yapışır.
    const asideRef = useRef<HTMLElement>(null);
    const [asideTall, setAsideTall] = useState(false);

    useEffect(() => {
        const el = asideRef.current;

        if (!el) {
            return;
        }

        const check = () =>
            setAsideTall(el.offsetHeight > window.innerHeight - 180);
        const observer = new ResizeObserver(check);
        observer.observe(el);
        window.addEventListener('resize', check);
        check();

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', check);
        };
    }, []);
    const [carrierId, setCarrierId] = useState(
        carriers.find((c) => c.is_default)?.id ?? carriers[0]?.id ?? '',
    );

    const carrier = carriers.find((c) => c.id === carrierId) ?? null;
    const shippingFormatted = carrier
        ? carrier.shipping_formatted
        : totals.shipping_formatted;
    const totalFormatted = carrier
        ? carrier.total_formatted
        : totals.total_formatted;

    const showSave = canSaveAddress && (!shippingSaved || !billingSaved);

    const selected = methods.find((m) => m.value === method) ?? null;
    // Yönteme özel fark (ör. havale indirimi): KDV dahil toplam (kargo dahil) üzerinden,
    // sunucudaki OrderService ile aynı kural (Money::percent, HalfUp 2 hane).
    const baseTotal = carrier ? carrier.total : totals.total;
    const adjustment =
        selected && !isZero(selected.adjustment_rate)
            ? percent(baseTotal, selected.adjustment_rate)
            : '0.00';
    const payable = add(baseTotal, adjustment);
    const money = (value: string) =>
        formatMoney(value, totals.currency || 'TRY');
    // Kartla ödeme gibi sağlayıcı sayfasına giden yöntemler "Ödemeye Geç" der.
    const paysNow = selected !== null && !(selected.icon in METHOD_ICONS);
    // Kargo ve ödeme seçimi varsayılan olarak özet kartında (seçim ile etkisi yan yana);
    // kit isterse sol sütunda adım olarak (eski düzen).
    const inSummary = kit.choicesInMain !== true;

    const carrierChoices = (
        <div className={inSummary ? 'grid gap-2' : 'grid gap-3 sm:grid-cols-2'}>
            {carriers.map((c) => (
                <kit.Choice
                    key={c.id}
                    name="_carrier"
                    checked={carrierId === c.id}
                    onChange={() => {
                        setCarrierId(c.id);
                        setCarriersOpen(false);
                    }}
                    card
                >
                    <kit.CarrierLogo carrier={c} />
                    <span className="min-w-0 flex-1">
                        <span className="block font-bold">{c.name}</span>
                        {c.delivery_days ? (
                            <span className="block text-[11px] opacity-70">
                                {t('Tahmini :days iş günü', {
                                    days: c.delivery_days,
                                })}
                            </span>
                        ) : null}
                    </span>
                    <span className="font-extrabold tabular-nums">
                        {c.shipping_formatted}
                    </span>
                </kit.Choice>
            ))}
        </div>
    );

    // Özette: seçili firma ve ücreti tek satır; başka firma varsa açılır listeden seçilir.
    // Eski düzende tüm firmalar kart olarak listelenir.
    const carrierPicker = (errors: Record<string, string>) => (
        <>
            <input type="hidden" name="carrier_id" value={carrierId} />
            {inSummary ? (
                <>
                    {carrier ? (
                        <div className="flex items-baseline justify-between gap-3 text-[13px]">
                            <span className="min-w-0 font-semibold">
                                {carrier.name}
                                {carrier.delivery_days ? (
                                    <span className="ml-1 text-[11px] font-normal opacity-70">
                                        {t('(tahmini :days iş günü)', {
                                            days: carrier.delivery_days,
                                        })}
                                    </span>
                                ) : null}
                            </span>
                            <span className="shrink-0 font-extrabold tabular-nums">
                                {carrier.shipping_formatted}
                            </span>
                        </div>
                    ) : null}
                    {carriers.length > 1 ? (
                        <>
                            <button
                                type="button"
                                aria-expanded={carriersOpen}
                                onClick={() => setCarriersOpen((o) => !o)}
                                className={`mt-1.5 inline-flex cursor-pointer items-center gap-1 text-[13px] ${kit.linkClass}`}
                            >
                                {carriersOpen
                                    ? t('Kargo seçeneklerini gizle')
                                    : t('Diğer kargo seçenekleri (:count)', {
                                          count: carriers.length - 1,
                                      })}
                                <ChevronDown
                                    className={`size-3.5 transition-transform ${carriersOpen ? 'rotate-180' : ''}`}
                                />
                            </button>
                            {carriersOpen ? (
                                <div className="mt-2">{carrierChoices}</div>
                            ) : null}
                        </>
                    ) : null}
                </>
            ) : (
                <>
                    {carrierChoices}
                    {totals.desi ? (
                        <kit.Note>
                            {t(
                                'Sepet toplamı :desi desi üzerinden hesaplanmıştır.',
                                { desi: totals.desi },
                            )}
                        </kit.Note>
                    ) : null}
                </>
            )}
            {errors.carrier_id ? (
                <kit.Note tone="error">{errors.carrier_id}</kit.Note>
            ) : null}
        </>
    );

    const methodPicker = (errors: Record<string, string>) => (
        <>
            {methods.length === 0 ? (
                <kit.Note tone="error">
                    {t(
                        'Şu anda kullanılabilir ödeme yöntemi yok. Lütfen bizimle iletişime geçin.',
                    )}
                </kit.Note>
            ) : (
                <div
                    className={
                        inSummary ? 'grid gap-2' : 'grid gap-3 sm:grid-cols-2'
                    }
                >
                    {methods.map((m) => {
                        const Icon =
                            METHOD_ICONS[m.icon as keyof typeof METHOD_ICONS] ??
                            CreditCard;

                        return (
                            <kit.Choice
                                key={m.value}
                                name="payment_method"
                                value={m.value}
                                checked={method === m.value}
                                onChange={() => setMethod(m.value)}
                                card
                            >
                                <Icon className="size-5 text-primary" />
                                <span className="font-semibold">{m.label}</span>
                            </kit.Choice>
                        );
                    })}
                </div>
            )}
            {errors.payment_method ? (
                <kit.Note tone="error">{errors.payment_method}</kit.Note>
            ) : null}
            {selected?.description ? (
                <kit.Note>{selected.description}</kit.Note>
            ) : null}
            {selected?.installments_url ? (
                <InstallmentTable
                    url={selected.installments_url}
                    amount={payable}
                    currency={totals.currency || 'TRY'}
                />
            ) : null}
        </>
    );

    const noteField = (errors: Record<string, string>) => (
        <kit.TextAreaField
            label={t('Sipariş Notu')}
            name="customer_note"
            rows={2}
            error={errors.customer_note}
        />
    );

    const lastStep = !inSummary && carriers.length > 0 ? 5 : 4;

    return (
        <Form action={action} method="post" className={kit.gridClass}>
            {({ errors, processing }) => (
                <>
                    {/* Çift gönderim koruması ve ekranda görülen tutar (sunucu değişmişse uyarır). */}
                    {checkoutToken ? (
                        <input
                            type="hidden"
                            name="checkout_token"
                            value={checkoutToken}
                        />
                    ) : null}
                    <input
                        type="hidden"
                        name="expected_total"
                        value={payable}
                    />
                    <div className="flex min-w-0 flex-col gap-5">
                        <kit.Step title={t('İletişim Bilgileri')} step={1}>
                            {guestAccount ? (
                                <p className="mb-4 text-[13px]">
                                    {t('Üye misiniz?')}{' '}
                                    <a
                                        href={guestAccount.login_url}
                                        className={kit.linkClass}
                                    >
                                        {t('Giriş yapın')}
                                    </a>
                                    <span className="opacity-70">
                                        {' · '}
                                        {t(
                                            'Sepetiniz korunur, kayıtlı adreslerinizi seçebilirsiniz.',
                                        )}
                                    </span>
                                </p>
                            ) : null}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <kit.TextField
                                    label={t('E-posta')}
                                    type="email"
                                    name="email"
                                    defaultValue={prefill.email}
                                    required
                                    error={errors.email}
                                    autoComplete="email"
                                />
                                <kit.TextField
                                    label={t('Cep Telefonu')}
                                    type="tel"
                                    name="phone"
                                    defaultValue={prefill.phone}
                                    required
                                    error={errors.phone}
                                    autoComplete="tel"
                                    placeholder={t('05xx xxx xx xx')}
                                />
                            </div>
                            {guestAccount ? (
                                <>
                                    <kit.Divider />
                                    <input
                                        type="hidden"
                                        name="create_account"
                                        value={createAccount ? '1' : '0'}
                                    />
                                    <kit.CheckField
                                        label={t(
                                            "Bu bilgilerle üye olmak istiyorum (siparişlerinizi Hesabım'dan izlersiniz)",
                                        )}
                                        checked={createAccount}
                                        onCheckedChange={setCreateAccount}
                                    />
                                    {createAccount ? (
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                            <kit.TextField
                                                label={t('Şifre')}
                                                type="password"
                                                name="password"
                                                required
                                                autoComplete="new-password"
                                                error={errors.password}
                                            />
                                            <kit.TextField
                                                label={t('Şifre (tekrar)')}
                                                type="password"
                                                name="password_confirmation"
                                                required
                                                autoComplete="new-password"
                                            />
                                            {guestAccount.contracts.map(
                                                (contract) => (
                                                    <div
                                                        key={contract.slug}
                                                        className="sm:col-span-2"
                                                    >
                                                        <kit.CheckField
                                                            name={`account_agreements[${contract.slug}]`}
                                                            value="1"
                                                            required
                                                            error={
                                                                errors[
                                                                    `account_agreements.${contract.slug}`
                                                                ]
                                                            }
                                                            label={
                                                                <ContractAgreementLabel
                                                                    contracts={[
                                                                        contract,
                                                                    ]}
                                                                    linkClass={
                                                                        kit.linkClass
                                                                    }
                                                                />
                                                            }
                                                        />
                                                    </div>
                                                ),
                                            )}
                                            <div className="sm:col-span-2">
                                                <kit.Note>
                                                    {t(
                                                        'Ad soyad fatura bilgilerinden alınır; adresleriniz Adreslerim’e kaydedilir. E-posta adresinize doğrulama bağlantısı gönderilir.',
                                                    )}
                                                </kit.Note>
                                            </div>
                                        </div>
                                    ) : null}
                                </>
                            ) : null}
                        </kit.Step>

                        <kit.Step title={t('Teslimat Adresi')} step={2}>
                            <input
                                type="hidden"
                                name="same_address"
                                value="0"
                            />
                            <kit.SavedAddressSelect
                                label={t('Kayıtlı teslimat adreslerim')}
                                addresses={shippingList}
                                value={shippingAddressId}
                                onChange={setShippingAddressId}
                            />
                            <div
                                key={`shipping-${shippingAddressId}`}
                                className="grid gap-3 sm:grid-cols-2"
                            >
                                <kit.TextField
                                    label={t('Teslim Alacak Kişi')}
                                    name="shipping[name]"
                                    defaultValue={shipping.name}
                                    required
                                    error={errors['shipping.name']}
                                    autoComplete="shipping name"
                                />
                                <kit.TextField
                                    label={t('Telefon')}
                                    type="tel"
                                    name="shipping[phone]"
                                    defaultValue={shipping.phone}
                                    error={errors['shipping.phone']}
                                />
                                <kit.TextAreaField
                                    label={t('Adres')}
                                    name="shipping[address]"
                                    defaultValue={shipping.address}
                                    required
                                    rows={2}
                                    error={errors['shipping.address']}
                                    className="sm:col-span-2"
                                    autoComplete="shipping street-address"
                                />
                                <kit.LocationSelects
                                    prefix="shipping"
                                    cities={cities}
                                    defaultCity={shipping.city}
                                    defaultDistrict={shipping.district}
                                    cityError={errors['shipping.city']}
                                    districtError={errors['shipping.district']}
                                />
                                <kit.TextField
                                    label={t('Posta Kodu')}
                                    name="shipping[postal_code]"
                                    defaultValue={shipping.postal_code}
                                    error={errors['shipping.postal_code']}
                                    autoComplete="shipping postal-code"
                                />
                            </div>
                        </kit.Step>

                        <kit.Step title={t('Fatura Bilgileri')} step={3}>
                            <input
                                type="hidden"
                                name="billing_same_as_shipping"
                                value={billingSame ? '1' : '0'}
                            />
                            <div className="mb-4">
                                <kit.CheckField
                                    label={t(
                                        'Fatura adresim teslimat adresimle aynı',
                                    )}
                                    checked={billingSame}
                                    onCheckedChange={setBillingSame}
                                />
                            </div>
                            {!billingSame ? (
                                <kit.SavedAddressSelect
                                    label={t('Kayıtlı fatura adreslerim')}
                                    addresses={billingList}
                                    value={billingAddressId}
                                    onChange={(id) => {
                                        setBillingAddressId(id);
                                        const picked = billingList.find(
                                            (a) => a.id === id,
                                        );

                                        if (picked) {
                                            setBillingType(
                                                picked.company
                                                    ? 'corporate'
                                                    : 'individual',
                                            );
                                        }
                                    }}
                                />
                            ) : null}
                            <div className="mb-4 flex gap-5 text-[13px]">
                                {[
                                    ['individual', 'Bireysel'],
                                    ['corporate', 'Kurumsal'],
                                ].map(([value, label]) => (
                                    <kit.Choice
                                        key={value}
                                        name="billing[type]"
                                        value={value}
                                        checked={billingType === value}
                                        onChange={() => setBillingType(value)}
                                    >
                                        {label}
                                    </kit.Choice>
                                ))}
                            </div>
                            <div
                                key={`billing-${billingSame ? 'same' : billingAddressId}`}
                                className="grid gap-3 sm:grid-cols-2"
                            >
                                <kit.TextField
                                    label={t('Ad Soyad')}
                                    name="billing[name]"
                                    defaultValue={billing.name}
                                    required
                                    error={errors['billing.name']}
                                    autoComplete="name"
                                />
                                {billingType === 'corporate' ? (
                                    <>
                                        <kit.TextField
                                            label={t('Firma Ünvanı')}
                                            name="billing[company]"
                                            defaultValue={billing.company}
                                            required
                                            error={errors['billing.company']}
                                        />
                                        <kit.TextField
                                            label={t('Vergi Dairesi')}
                                            name="billing[tax_office]"
                                            defaultValue={billing.tax_office}
                                            required
                                            error={errors['billing.tax_office']}
                                        />
                                        <kit.TextField
                                            label={t('Vergi Numarası')}
                                            name="billing[tax_number]"
                                            defaultValue={billing.tax_number}
                                            required
                                            error={errors['billing.tax_number']}
                                        />
                                    </>
                                ) : (
                                    <kit.TextField
                                        label={t(
                                            'T.C. Kimlik No (isteğe bağlı)',
                                        )}
                                        name="billing[identity_number]"
                                        defaultValue={billing.identity_number}
                                        inputMode="numeric"
                                        maxLength={11}
                                        error={
                                            errors['billing.identity_number']
                                        }
                                    />
                                )}
                                {billingSame ? (
                                    <div className="sm:col-span-2">
                                        <kit.AddressPreview>
                                            {t(
                                                'Fatura adresi olarak teslimat adresiniz kullanılacak. Sipariş teslimat ve fatura adresini ayrı ayrı saklar.',
                                            )}
                                        </kit.AddressPreview>
                                        {errors['billing.address'] ||
                                        errors['billing.city'] ? (
                                            <kit.Note tone="error">
                                                {errors['billing.address'] ??
                                                    errors['billing.city']}
                                            </kit.Note>
                                        ) : null}
                                    </div>
                                ) : (
                                    <>
                                        <kit.TextAreaField
                                            label={t('Fatura Adresi')}
                                            name="billing[address]"
                                            defaultValue={billing.address}
                                            required
                                            rows={2}
                                            error={errors['billing.address']}
                                            className="sm:col-span-2"
                                            autoComplete="billing street-address"
                                        />
                                        <kit.LocationSelects
                                            prefix="billing"
                                            cities={cities}
                                            defaultCity={billing.city}
                                            defaultDistrict={billing.district}
                                            cityError={errors['billing.city']}
                                            districtError={
                                                errors['billing.district']
                                            }
                                        />
                                        <kit.TextField
                                            label={t('Posta Kodu')}
                                            name="billing[postal_code]"
                                            defaultValue={billing.postal_code}
                                            error={
                                                errors['billing.postal_code']
                                            }
                                            autoComplete="billing postal-code"
                                        />
                                    </>
                                )}
                            </div>
                            {showSave ? (
                                <>
                                    <kit.Divider />
                                    <input
                                        type="hidden"
                                        name="save_address"
                                        value={saveAddress ? '1' : '0'}
                                    />
                                    <kit.CheckField
                                        label={t(
                                            "Yeni adresleri Adreslerim'e kaydet (teslimat ve fatura adresi ayrı ayrı)",
                                        )}
                                        checked={saveAddress}
                                        onCheckedChange={setSaveAddress}
                                    />
                                </>
                            ) : null}
                        </kit.Step>

                        {!inSummary && carriers.length > 0 ? (
                            <kit.Step title={t('Kargo Firması')} step={4}>
                                {carrierPicker(errors)}
                            </kit.Step>
                        ) : null}

                        {inSummary ? (
                            <kit.Step title={t('Sipariş Notu')} step={lastStep}>
                                {noteField(errors)}
                            </kit.Step>
                        ) : (
                            <kit.Step
                                title={t('Ödeme Yöntemi')}
                                step={lastStep}
                            >
                                {methodPicker(errors)}
                                <div className="mt-4">{noteField(errors)}</div>
                            </kit.Step>
                        )}
                    </div>

                    {/* Özet ekrana yapışıktır (kit.asideClass, üstten). Ekrandan uzunsa
                        alttan yapışır: sayfa kaydıkça onay düğmesi ekranın altında kalır,
                        özetin içinde ayrı kaydırma çubuğu olmaz. */}
                    <aside
                        ref={asideRef}
                        className={
                            inSummary && asideTall
                                ? 'min-w-0 lg:sticky lg:bottom-4 lg:self-end'
                                : `min-w-0 ${kit.asideClass}`
                        }
                    >
                        <kit.Summary>
                            <kit.SummaryLines lines={lines} />
                            {inSummary && carriers.length > 0 ? (
                                <div className="mb-4">
                                    <SummarySection title={t('Kargo Firması')}>
                                        {carrierPicker(errors)}
                                    </SummarySection>
                                </div>
                            ) : null}
                            <kit.Totals
                                totals={totals}
                                carrier={carrier}
                                shippingFormatted={shippingFormatted}
                                totalFormatted={totalFormatted}
                            />
                            {!isZero(adjustment) && selected ? (
                                <kit.Note>
                                    {selected.adjustment_label ??
                                        t('Ödeme farkı')}
                                    : {isNegative(adjustment) ? '−' : '+'}
                                    {money(abs(adjustment))} ·{' '}
                                    {t('Ödenecek tutar:')}{' '}
                                    <strong>{money(payable)}</strong>
                                </kit.Note>
                            ) : null}
                            {inSummary ? (
                                <SummarySection title={t('Ödeme Yöntemi')}>
                                    {methodPicker(errors)}
                                </SummarySection>
                            ) : null}
                            <div className="mt-5 flex flex-col gap-3">
                                {/* Her sözleşmenin ayrı onay kutusu vardır. */}
                                {contracts.map((contract) => (
                                    <kit.CheckField
                                        key={contract.slug}
                                        name={`agreements[${contract.slug}]`}
                                        value="1"
                                        required
                                        error={
                                            errors[
                                                `agreements.${contract.slug}`
                                            ]
                                        }
                                        label={
                                            <ContractAgreementLabel
                                                contracts={[contract]}
                                                linkClass={kit.linkClass}
                                            />
                                        }
                                    />
                                ))}
                                {/* Ticari elektronik ileti onayı: isteğe bağlı, işaretsiz gelir. */}
                                <kit.CheckField
                                    name="marketing_consent"
                                    value="1"
                                    error={errors.marketing_consent}
                                    label={t(
                                        'Kampanya ve yenilikler hakkında e-posta ve telefonla ticari ileti almak istiyorum.',
                                    )}
                                />
                                <kit.SubmitButton
                                    processing={processing}
                                    className="w-full"
                                >
                                    <Lock className="size-4" />{' '}
                                    {paysNow
                                        ? t('Ödemeye Geç')
                                        : t('Siparişi Onayla')}
                                </kit.SubmitButton>
                            </div>
                        </kit.Summary>
                    </aside>
                </>
            )}
        </Form>
    );
}
