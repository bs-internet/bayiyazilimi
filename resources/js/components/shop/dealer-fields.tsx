import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';
import type { Option } from '@/types';

const NO_GROUP = '__none';

/**
 * Bayi grubu + bayiye özel indirim alanları (Bayi ekle formu ve
 * Müşteri detayındaki "Bayi ayarları" penceresi ortak kullanır).
 */
export function DealerFields({
    groups,
    defaultGroupId,
    defaultRate,
    warehouses,
    defaultWarehouseId,
    errors,
}: {
    groups: Option<string>[];
    defaultGroupId?: string | null;
    defaultRate?: string | null;
    /** Satılabilir depolar; verilirse "Bölge deposu" seçimi gösterilir. */
    warehouses?: Option<string>[];
    defaultWarehouseId?: string | null;
    errors: Partial<Record<string, string>>;
}) {
    const [groupId, setGroupId] = useState<string>(
        defaultGroupId ?? groups[0]?.value ?? NO_GROUP,
    );
    const [warehouseId, setWarehouseId] = useState<string>(
        defaultWarehouseId ?? NO_GROUP,
    );

    return (
        <>
            <input
                type="hidden"
                name="dealer_group_id"
                value={groupId === NO_GROUP ? '' : groupId}
            />

            <div className="grid gap-2">
                <Label>{t('Bayi grubu')}</Label>
                <Select value={groupId} onValueChange={setGroupId}>
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={NO_GROUP}>
                                {t('Grupsuz')}
                            </SelectItem>
                            {groups.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <InputError message={errors.dealer_group_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="discount_rate">
                    {t('Bayiye özel indirim (%)')}
                </Label>
                <Input
                    id="discount_rate"
                    name="discount_rate"
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    placeholder={t('Boş bırakılırsa grubun oranı')}
                    defaultValue={defaultRate ?? ''}
                    aria-invalid={Boolean(errors.discount_rate)}
                />
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Girilirse grubun oranının yerine geçer. İndirimli ürünlerde ve indirimli kategorilerde bayi indirimi uygulanmaz.',
                    )}
                </p>
                <InputError message={errors.discount_rate} />
            </div>

            {warehouses && warehouses.length > 0 && (
                <div className="grid gap-2">
                    <input
                        type="hidden"
                        name="warehouse_id"
                        value={warehouseId === NO_GROUP ? '' : warehouseId}
                    />
                    <Label>{t('Bölge deposu')}</Label>
                    <Select value={warehouseId} onValueChange={setWarehouseId}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value={NO_GROUP}>
                                    {t('Atanmamış (tüm depolar)')}
                                </SelectItem>
                                {warehouses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Bayi bu deponun stoğunu ayrıca görür; siparişi önce bu depodan, yetmezse diğer satılabilir depolardan düşülür.',
                        )}
                    </p>
                    <InputError message={errors.warehouse_id} />
                </div>
            )}
        </>
    );
}
