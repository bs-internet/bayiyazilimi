import { useEffect, useState } from 'react';
import { toFixed } from '@/lib/decimal';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';

type InstallmentData = {
    card: {
        bank: string | null;
        family: string | null;
        type: string | null;
    } | null;
    rows: {
        family: string;
        options: { count: number; total: string; monthly: string }[];
    }[];
    error?: string;
};

/**
 * Kart sağlayıcısının taksit seçenekleri (bilgi amaçlı). Kartın ilk 6 hanesi
 * girilirse yalnız o kartın ailesi gösterilir; taksit seçimi sağlayıcının
 * güvenli ödeme sayfasında yapılır.
 */
export function InstallmentTable({
    url,
    amount,
    currency,
}: {
    url: string;
    amount: string;
    currency: string;
}) {
    const [bin, setBin] = useState('');
    // Yanıt hangi sorguya aitse onunla saklanır; yükleniyor durumu buradan türetilir.
    const [result, setResult] = useState<{
        key: string;
        data: InstallmentData;
    } | null>(null);
    const activeBin = bin.length >= 6 ? bin : '';
    const key = `${url}|${toFixed(amount)}|${currency}|${activeBin}`;
    const data = result?.data ?? null;
    const loading = result?.key !== key;

    useEffect(() => {
        const controller = new AbortController();
        const query = new URLSearchParams({
            amount: toFixed(amount),
            currency,
            ...(activeBin !== '' ? { bin: activeBin } : {}),
        });

        fetch(`${url}?${query.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then((json: InstallmentData) => setResult({ key, data: json }))
            .catch(() => undefined);

        return () => controller.abort();
    }, [url, amount, currency, activeBin, key]);

    const money = (value: string) => formatMoney(value, currency || 'TRY');

    return (
        <div className="mt-3 flex flex-col gap-2 rounded-lg border p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-semibold">{t('Taksit seçenekleri')}</span>
                <input
                    inputMode="numeric"
                    maxLength={8}
                    value={bin}
                    onChange={(e) => setBin(e.target.value.replace(/\D/g, ''))}
                    placeholder={t('Kartın ilk 6 hanesi')}
                    className="h-8 w-40 rounded-md border bg-background px-2 text-sm"
                    aria-label={t('Kartın ilk 6 hanesi')}
                />
            </div>
            {data?.card && (
                <div className="text-xs text-muted-foreground">
                    {[data.card.bank, data.card.family, data.card.type]
                        .filter(Boolean)
                        .join(' · ')}
                </div>
            )}
            {loading && !data ? (
                <div className="text-xs text-muted-foreground">
                    {t('Yükleniyor…')}
                </div>
            ) : data?.error || !data || data.rows.length === 0 ? (
                <div className="text-xs text-muted-foreground">
                    {data?.error ??
                        t('Taksit seçenekleri ödeme sayfasında gösterilir.')}
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="text-left text-muted-foreground">
                                <th className="py-1 pr-2 font-medium">
                                    {t('Kart')}
                                </th>
                                <th className="py-1 pr-2 font-medium">
                                    {t('Taksit')}
                                </th>
                                <th className="py-1 pr-2 text-right font-medium">
                                    {t('Aylık')}
                                </th>
                                <th className="py-1 text-right font-medium">
                                    {t('Toplam')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.rows.map((row) =>
                                row.options.map((o, i) => (
                                    <tr
                                        key={`${row.family}-${o.count}`}
                                        className="border-t"
                                    >
                                        <td className="py-1 pr-2">
                                            {i === 0 ? row.family : ''}
                                        </td>
                                        <td className="py-1 pr-2">
                                            {o.count === 1
                                                ? t('Tek çekim')
                                                : t(':count taksit', {
                                                      count: o.count,
                                                  })}
                                        </td>
                                        <td className="py-1 pr-2 text-right tabular-nums">
                                            {money(o.monthly)}
                                        </td>
                                        <td className="py-1 text-right tabular-nums">
                                            {money(o.total)}
                                        </td>
                                    </tr>
                                )),
                            )}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
