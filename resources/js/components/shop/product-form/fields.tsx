import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { SearchSelect } from '@/components/search-select';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney, formatNumber } from '@/lib/format';
import type { ShopOption } from '@/types';

/* ------------------------------------------------------------------ */
/* Alan yardımcıları (uncontrolled; Inertia <Form> değerleri toplar)   */
/* ------------------------------------------------------------------ */

type FieldBase = {
    name: string;
    label: string;
    error?: string;
    description?: ReactNode;
    className?: string;
};

export function TextInput({
    name,
    label,
    error,
    description,
    className,
    defaultValue,
    type = 'text',
    step,
    min,
    required,
    placeholder,
    prefix,
    suffix,
    onValueChange,
}: FieldBase & {
    defaultValue?: string | number | null;
    type?: string;
    step?: string;
    min?: number;
    required?: boolean;
    placeholder?: string;
    prefix?: ReactNode;
    suffix?: ReactNode;
    onValueChange?: (value: string) => void;
}) {
    const inputProps = {
        id: name,
        name,
        type,
        step,
        min,
        required,
        placeholder,
        defaultValue: defaultValue ?? '',
        'aria-invalid': Boolean(error),
        onChange: onValueChange
            ? (e: React.ChangeEvent<HTMLInputElement>) =>
                  onValueChange(e.target.value)
            : undefined,
    };

    return (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={name}>
                {label}
                {required && <span className="text-destructive">*</span>}
            </FieldLabel>
            {prefix || suffix ? (
                <InputGroup>
                    {prefix && (
                        <InputGroupAddon>
                            <InputGroupText>{prefix}</InputGroupText>
                        </InputGroupAddon>
                    )}
                    <InputGroupInput {...inputProps} />
                    {suffix && (
                        <InputGroupAddon align="inline-end">
                            <InputGroupText>{suffix}</InputGroupText>
                        </InputGroupAddon>
                    )}
                </InputGroup>
            ) : (
                <Input {...inputProps} />
            )}
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

export function TextAreaInput({
    name,
    label,
    error,
    description,
    className,
    defaultValue,
    rows = 3,
    onValueChange,
}: FieldBase & {
    defaultValue?: string | null;
    rows?: number;
    onValueChange?: (value: string) => void;
}) {
    return (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Textarea
                id={name}
                name={name}
                rows={rows}
                defaultValue={defaultValue ?? ''}
                aria-invalid={Boolean(error)}
                onChange={
                    onValueChange
                        ? (e) => onValueChange(e.target.value)
                        : undefined
                }
            />
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

export function SelectInput({
    name,
    label,
    error,
    description,
    className,
    options,
    defaultValue,
    placeholder,
    required,
    onValueChange,
}: FieldBase & {
    options: ShopOption[];
    defaultValue?: string | null;
    placeholder?: string;
    required?: boolean;
    onValueChange?: (value: string) => void;
}) {
    return (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={name}>
                {label}
                {required && <span className="text-destructive">*</span>}
            </FieldLabel>
            <SearchSelect
                id={name}
                name={name}
                options={options}
                defaultValue={
                    defaultValue ||
                    (placeholder === undefined ? options[0]?.value : '')
                }
                placeholder={placeholder}
                required={required}
                clearable={placeholder !== undefined && !required}
                aria-invalid={Boolean(error)}
                onChange={onValueChange}
            />
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

export function formatAmount(amount: string, currency: string): string {
    try {
        return formatMoney(amount, currency);
    } catch {
        return `${formatNumber(amount, 2, 2)} ${currency}`;
    }
}
