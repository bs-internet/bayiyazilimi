import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import type { ShopOption } from '@/types';

/** NativeSelect sarmalayıcısı varsayılan `w-fit`; diyalogda tam genişlik. */
export function SelectInput({
    id,
    name,
    options,
    defaultValue,
    placeholder,
}: {
    id: string;
    name: string;
    options: ShopOption[];
    defaultValue?: string | null;
    placeholder?: string;
}) {
    return (
        <div className="*:data-[slot=native-select-wrapper]:w-full">
            <NativeSelect id={id} name={name} defaultValue={defaultValue ?? ''}>
                {placeholder !== undefined && (
                    <NativeSelectOption value="">
                        {placeholder}
                    </NativeSelectOption>
                )}
                {options.map((o) => (
                    <NativeSelectOption key={o.value} value={o.value}>
                        {o.label}
                    </NativeSelectOption>
                ))}
            </NativeSelect>
        </div>
    );
}
