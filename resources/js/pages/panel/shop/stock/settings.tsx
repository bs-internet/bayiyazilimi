import { Form, Head } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';

import { Page, PageTitle, SectionCard } from '@/components/admin/page';
import {
    SelectField,
    SwitchField,
    TextField,
} from '@/components/shop/form-fields';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/panel';
import { index as stockIndex } from '@/routes/panel/shop/stock';
import { edit, update } from '@/routes/panel/shop/stock-settings';
import type { ShopOption } from '@/types';

type Props = {
    defaultWarehouseId: string | null;
    showOutOfStock: boolean;
    /** Vitrin kurulu mu? "Stokta olmayanları vitrinde göster" yalnız o zaman çıkar. */
    storefront: boolean;
    lowStockThreshold: number;
    lowStockNotify: boolean;
    traceability: boolean;
    expiryWarningDays: number;
    stockReservationDays: number;
    warehouses: ShopOption[];
};

export default function StockSettings({
    defaultWarehouseId,
    showOutOfStock,
    storefront,
    lowStockThreshold,
    lowStockNotify,
    traceability,
    expiryWarningDays,
    stockReservationDays,
    warehouses,
}: Props) {
    const [traceOn, setTraceOn] = useState(traceability);

    return (
        <>
            <Head title={t('Stok Ayarları')} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Mağaza') },
                        { title: t('Stok Durumu'), href: stockIndex() },
                        { title: t('Stok Ayarları') },
                    ]}
                    title={t('Stok Ayarları')}
                    description={t(
                        'Varsayılan satış deposu, kritik stok uyarısı ve izlenebilirlik.',
                    )}
                />

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="max-w-3xl"
                >
                    {({ processing, errors }) => (
                        <SectionCard title={t('Stok')}>
                            <FieldGroup>
                                <SelectField
                                    name="default_warehouse_id"
                                    label={t('Varsayılan satış deposu')}
                                    options={warehouses}
                                    required
                                    defaultValue={defaultWarehouseId}
                                    error={errors.default_warehouse_id}
                                    description={t(
                                        'Siparişlerde stok önce bu depodan, yetmezse diğer satılabilir depolardan düşülür. Varsayılan depo yalnız buradan değiştirilir.',
                                    )}
                                />
                                {storefront ? (
                                    <SwitchField
                                        name="show_out_of_stock"
                                        label={t(
                                            'Stokta olmayan ürünleri vitrinde göster',
                                        )}
                                        description={t(
                                            'Kapalıyken stoğu bitmiş ürünler listelerde ve aramada görünmez.',
                                        )}
                                        defaultChecked={showOutOfStock}
                                    />
                                ) : null}
                                <TextField
                                    name="low_stock_threshold"
                                    label={t('Kritik stok eşiği (genel)')}
                                    type="number"
                                    min={0}
                                    required
                                    defaultValue={lowStockThreshold}
                                    error={errors.low_stock_threshold}
                                    description={t(
                                        'Satılabilir stok bu değere ya da altına inince ürün kritik sayılır. Ürün formunda ürüne özel eşik girilirse o geçerlidir.',
                                    )}
                                />
                                <SwitchField
                                    name="low_stock_notify"
                                    label={t('Kritik stok bildirimi gönder')}
                                    description={t(
                                        'Stok eşiğe indiğinde, Stok Durumu yetkisi olan yöneticilere uygulama içi bildirim ve e-posta gider (eşiğin üstüne çıkıp yeniden inene kadar bir kez).',
                                    )}
                                    defaultChecked={lowStockNotify}
                                />
                                <TextField
                                    name="stock_reservation_days"
                                    label={t('Stok rezervasyon süresi (gün)')}
                                    type="number"
                                    min={0}
                                    required
                                    defaultValue={stockReservationDays}
                                    error={errors.stock_reservation_days}
                                    description={t(
                                        'Stok sipariş anında ayrılır. Havale / EFT ile verilip ödemesi bu süre içinde gelmeyen sipariş "Ödeme Başarısız", onay bekleyen vadeli sipariş "İptal Edildi" olur ve stok serbest kalır; müşteriye e-posta gider. İncelenmeyi bekleyen havale bildirimi olan siparişe dokunulmaz. 0 girilirse süre uygulanmaz.',
                                    )}
                                />
                                <SwitchField
                                    name="traceability_enabled"
                                    label={t('Lot / seri no ve SKT takibi')}
                                    description={t(
                                        "Açıkken ürün formunda izlenebilirlik (lot / seri no, son kullanma tarihi) seçilebilir, mal kabulde lot / seri girilir, siparişte SKT'si yakın olan önce çıkar. Kapatılırsa tüm ürünler takipsiz davranır; kayıtlı lotlar silinmez, yeniden açınca geri gelir.",
                                    )}
                                    defaultChecked={traceability}
                                    onChange={setTraceOn}
                                />
                                {traceOn && (
                                    <TextField
                                        name="expiry_warning_days"
                                        label={t('SKT uyarı süresi (gün)')}
                                        type="number"
                                        min={1}
                                        defaultValue={expiryWarningDays}
                                        error={errors.expiry_warning_days}
                                        description={t(
                                            "Son kullanma tarihine bu kadar gün kalan lotlar Stok Durumu ve Lot / Seri No ekranlarında 'SKT yaklaşan' olarak işaretlenir.",
                                        )}
                                    />
                                )}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        {t('Kaydet')}
                                    </Button>
                                </div>
                            </FieldGroup>
                        </SectionCard>
                    )}
                </Form>
            </Page>
        </>
    );
}

StockSettings.layout = {
    breadcrumbs: [
        { title: t('Panel'), href: dashboard() },
        { title: t('Stok Durumu'), href: stockIndex() },
        { title: t('Stok Ayarları'), href: edit() },
    ],
};
