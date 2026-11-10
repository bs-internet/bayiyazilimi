import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/rich-text-editor';
import { SearchSelect } from '@/components/search-select';
import { Badge } from '@/components/ui/badge';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Option, Status } from '@/types';
import { selectClassName } from './select-class';

/**
 * Mağaza panel formlarında tekrar eden alan bileşenleri.
 * Native input'lar kullanılır; böylece Inertia <Form> değerleri doğrudan toplar.
 */

export { selectClassName };

type BaseProps = {
    name: string;
    label: string;
    error?: string;
    description?: ReactNode;
    className?: string;
};

export function TextField({
    name,
    label,
    error,
    description,
    className,
    defaultValue,
    type = 'text',
    required = false,
    placeholder,
    step,
    min,
}: BaseProps & {
    defaultValue?: string | number | null;
    type?: string;
    required?: boolean;
    placeholder?: string;
    step?: string;
    min?: number;
}) {
    return (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                name={name}
                type={type}
                step={step}
                min={min}
                placeholder={placeholder}
                defaultValue={defaultValue ?? ''}
                required={required}
                aria-invalid={Boolean(error)}
            />
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

export function TextareaField({
    name,
    label,
    error,
    description,
    className,
    defaultValue,
    rows = 4,
    mono = false,
}: BaseProps & {
    defaultValue?: string | null;
    rows?: number;
    mono?: boolean;
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
                className={cn(mono && 'font-mono text-xs')}
            />
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

/**
 * Zengin metin (HTML) alanı — gizli input ile Inertia <Form>'a değer verir.
 */
export function RichTextField({
    name,
    label,
    error,
    description,
    className,
    defaultValue,
    minHeight,
}: BaseProps & {
    defaultValue?: string | null;
    minHeight?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <RichTextEditor
                id={name}
                name={name}
                defaultValue={defaultValue}
                minHeight={minHeight}
                invalid={Boolean(error)}
            />
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

export function SelectField({
    name,
    label,
    error,
    description,
    className,
    options,
    defaultValue,
    placeholder,
    required = false,
}: BaseProps & {
    options: Option[];
    defaultValue?: string | null;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <Field data-invalid={Boolean(error)} className={className}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <SearchSelect
                id={name}
                name={name}
                options={options}
                // Native <select> gibi: boş seçenek yoksa ilk seçenek seçili gelir.
                defaultValue={
                    defaultValue ||
                    (placeholder === undefined ? options[0]?.value : '')
                }
                placeholder={placeholder}
                required={required}
                clearable={placeholder !== undefined && !required}
                aria-invalid={Boolean(error)}
            />
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <InputError message={error} />
        </Field>
    );
}

/**
 * Native checkbox + gizli "0" değeri: işaretsizken de alan gönderilir.
 */
export function CheckboxField({
    name,
    label,
    description,
    defaultChecked = false,
    className,
}: {
    name: string;
    label: string;
    description?: ReactNode;
    defaultChecked?: boolean;
    className?: string;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm',
                className,
            )}
        >
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                className="mt-0.5 size-4 accent-primary"
            />
            <span className="flex flex-col gap-0.5">
                <span className="font-medium">{label}</span>
                {description ? (
                    <span className="text-muted-foreground">{description}</span>
                ) : null}
            </span>
        </label>
    );
}

/**
 * Tablodan durum değiştirme anahtarı (PATCH /status).
 */
export function StatusSwitch({
    status,
    url,
    label = t('Durumu değiştir'),
}: {
    status: Status;
    url: string;
    label?: string;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Switch
            size="sm"
            checked={status === 'active'}
            disabled={processing}
            aria-label={label}
            onCheckedChange={(checked) => {
                setProcessing(true);
                // Hedef durum gönderilir: sunucu zaten o durumdaysa (çift tıklama,
                // başka yönetici) geri çevirmez.
                router.patch(
                    url,
                    { status: checked ? 'active' : 'passive' },
                    {
                        preserveScroll: true,
                        onFinish: () => setProcessing(false),
                    },
                );
            }}
        />
    );
}

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const ORDER_STATUS_VARIANT: Record<string, BadgeVariant> = {
    pending: 'outline',
    awaiting_approval: 'outline',
    processing: 'secondary',
    shipped: 'default',
    completed: 'default',
    cancelled: 'destructive',
    failed: 'destructive',
};

const PAYMENT_STATUS_VARIANT: Record<string, BadgeVariant> = {
    pending: 'outline',
    partial: 'secondary',
    paid: 'default',
    failed: 'destructive',
    refunded: 'secondary',
    cancelled: 'secondary',
};

const QUOTE_STATUS_VARIANT: Record<string, BadgeVariant> = {
    pending: 'outline',
    approved: 'default',
    rejected: 'destructive',
    cancelled: 'secondary',
};

export function StateBadge({
    kind,
    value,
    label,
}: {
    kind: 'order' | 'payment' | 'quote';
    value: string;
    label: string;
}) {
    const map =
        kind === 'order'
            ? ORDER_STATUS_VARIANT
            : kind === 'payment'
              ? PAYMENT_STATUS_VARIANT
              : QUOTE_STATUS_VARIANT;

    return <Badge variant={map[value] ?? 'outline'}>{label}</Badge>;
}

export function FilterSelect({
    name,
    options,
    defaultValue,
    placeholder,
    className,
}: {
    name: string;
    options: Option[];
    defaultValue?: string | null;
    placeholder: string;
    className?: string;
}) {
    return (
        <SearchSelect
            name={name}
            options={options}
            defaultValue={defaultValue}
            placeholder={placeholder}
            aria-label={placeholder}
            clearable
            className={cn('w-auto min-w-44', className)}
        />
    );
}

/**
 * Açık / kapalı ayar: anahtar + gizli input. Kapalıyken de "0" gönderilir
 * (sunucu BOOLEAN_KEYS'i yoksa false sayar).
 */
export function SwitchField({
    name,
    label,
    description,
    defaultChecked,
    onChange,
}: {
    name: string;
    label: string;
    description?: ReactNode;
    defaultChecked: boolean;
    onChange?: (checked: boolean) => void;
}) {
    const [checked, setChecked] = useState(defaultChecked);

    return (
        <label
            htmlFor={`switch-${name}`}
            className="flex cursor-pointer items-start justify-between gap-4 rounded-md border p-3"
        >
            <input type="hidden" name={name} value={checked ? '1' : '0'} />
            <span className="flex flex-col gap-0.5">
                <span className="text-sm font-medium">{label}</span>
                {description ? (
                    <span className="text-sm text-muted-foreground">
                        {description}
                    </span>
                ) : null}
            </span>
            <Switch
                id={`switch-${name}`}
                checked={checked}
                onCheckedChange={(value) => {
                    setChecked(value);
                    onChange?.(value);
                }}
            />
        </label>
    );
}
