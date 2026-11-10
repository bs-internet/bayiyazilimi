import { useId, useState } from 'react';
import type { ReactNode } from 'react';

import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldLabel,
} from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';

/**
 * shadcn Checkbox / Switch + gizli "1"/"0" input: Inertia <Form> değeri
 * işaretsizken de gönderir (sunucu `boolean` kuralıyla okur).
 */
export function CheckField({
    name,
    label,
    description,
    defaultChecked = false,
    variant = 'checkbox',
    onCheckedChange,
}: {
    name: string;
    label: ReactNode;
    description?: ReactNode;
    defaultChecked?: boolean;
    variant?: 'checkbox' | 'switch';
    onCheckedChange?: (checked: boolean) => void;
}) {
    const id = useId();
    const [checked, setChecked] = useState(defaultChecked);

    const change = (value: boolean) => {
        setChecked(value);
        onCheckedChange?.(value);
    };

    return (
        <Field orientation="horizontal">
            <input type="hidden" name={name} value={checked ? '1' : '0'} />
            {variant === 'checkbox' && (
                <Checkbox
                    id={id}
                    checked={checked}
                    onCheckedChange={(v) => change(v === true)}
                />
            )}
            <FieldContent>
                <FieldLabel htmlFor={id} className="font-medium">
                    {label}
                </FieldLabel>
                {description ? (
                    <FieldDescription>{description}</FieldDescription>
                ) : null}
            </FieldContent>
            {variant === 'switch' && (
                <Switch id={id} checked={checked} onCheckedChange={change} />
            )}
        </Field>
    );
}
