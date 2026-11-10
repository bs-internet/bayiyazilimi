import { useState } from 'react';

import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { t } from '@/lib/i18n';
import type { Option } from '@/types';

const ALL = '__all__';

/**
 * Filtre panelinde tek seçimli liste (Tümü + seçenekler). Değeri gizli bir
 * input ile çevreleyen GET formuna verir; "Tümü" boş değer gönderir.
 */
export function RadioFilter({
    name,
    options,
    defaultValue,
    allLabel = t('Tümü'),
    form,
}: {
    name: string;
    options: Option[];
    defaultValue?: string | null;
    allLabel?: string;
    form?: string;
}) {
    const [value, setValue] = useState(defaultValue || ALL);
    const items = [{ value: ALL, label: allLabel }, ...options];

    return (
        <>
            <input
                type="hidden"
                name={name}
                value={value === ALL ? '' : value}
                form={form}
            />
            <RadioGroup
                value={value}
                onValueChange={setValue}
                className="gap-2"
            >
                {items.map((item) => {
                    const id = `${name}-${item.value}`;

                    return (
                        <div
                            key={item.value}
                            className="flex items-center gap-2"
                        >
                            <RadioGroupItem value={item.value} id={id} />
                            <Label
                                htmlFor={id}
                                className="cursor-pointer text-sm font-normal"
                            >
                                {item.label}
                            </Label>
                        </div>
                    );
                })}
            </RadioGroup>
        </>
    );
}
