import { CheckIcon, ChevronsUpDownIcon, XIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type SearchSelectOption = {
    value: string;
    label: string;
    /** Ağaç listelerde (kategori) girinti düzeyi. */
    depth?: number;
    disabled?: boolean;
};

/** Bu sayıdan fazla seçenekte liste üstünde arama kutusu gösterilir. */
const SEARCH_THRESHOLD = 7;

const fold = (s: string) => s.toLocaleLowerCase('tr-TR');

/**
 * Aramalı seçim kutusu (tom-select benzeri). Native <select> yerine geçer:
 * `name` verilirse değeri gizli bir input ile forma (Inertia <Form> dahil)
 * gönderir; `required` tarayıcı doğrulamasıyla çalışır. Kontrollü
 * (`value` + `onChange`) ya da kontrolsüz (`defaultValue`) kullanılabilir.
 */
export function SearchSelect({
    name,
    id,
    form,
    options,
    value,
    defaultValue,
    onChange,
    placeholder = t('Seçin'),
    searchPlaceholder = 'Ara…',
    emptyText = t('Sonuç yok.'),
    required = false,
    disabled = false,
    clearable = false,
    searchable,
    className,
    triggerClassName,
    contentClassName,
    'aria-invalid': ariaInvalid,
    'aria-label': ariaLabel,
}: {
    name?: string;
    id?: string;
    form?: string;
    options: SearchSelectOption[];
    value?: string | null;
    defaultValue?: string | null;
    onChange?: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    required?: boolean;
    disabled?: boolean;
    /** Seçimi temizleme düğmesi (boş değer = "hepsi" / "yok"). */
    clearable?: boolean;
    /** Arama kutusu: varsayılan olarak yalnız uzun listelerde; true ile her zaman. */
    searchable?: boolean;
    className?: string;
    /** Tetikleyici görünümü (ör. vitrin teması) için ek sınıflar. */
    triggerClassName?: string;
    contentClassName?: string;
    'aria-invalid'?: boolean;
    'aria-label'?: string;
}) {
    const [open, setOpen] = useState(false);
    const [inner, setInner] = useState(defaultValue ?? '');
    const current = value !== undefined ? (value ?? '') : inner;
    const selected = options.find((o) => o.value === current);
    const hiddenRef = useRef<HTMLInputElement>(null);
    const first = useRef(true);

    // Formun "değişti" takibi (isDirty vb.) native olay dinler.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        hiddenRef.current?.dispatchEvent(new Event('input', { bubbles: true }));
        hiddenRef.current?.dispatchEvent(
            new Event('change', { bubbles: true }),
        );
    }, [current]);

    const choose = (next: string) => {
        if (value === undefined) {
            setInner(next);
        }

        onChange?.(next);
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div className={cn('relative w-full', className)}>
                {name ? (
                    // Görünmez ama doğrulanabilir (required) input.
                    <input
                        ref={hiddenRef}
                        name={name}
                        form={form}
                        value={current}
                        required={required}
                        onChange={() => {}}
                        tabIndex={-1}
                        aria-hidden
                        className="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
                        onInvalid={() => setOpen(true)}
                    />
                ) : null}
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        id={id}
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={ariaInvalid}
                        aria-label={ariaLabel}
                        disabled={disabled}
                        className={cn(
                            'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-left text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30',
                            triggerClassName,
                        )}
                    >
                        <span
                            className={cn(
                                'truncate',
                                !selected && 'text-muted-foreground',
                            )}
                        >
                            {selected?.label ?? placeholder}
                        </span>
                        <span className="flex shrink-0 items-center gap-1">
                            {clearable && current !== '' && !disabled ? (
                                <span
                                    role="button"
                                    tabIndex={-1}
                                    aria-label={t('Seçimi temizle')}
                                    onPointerDown={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        choose('');
                                    }}
                                    className="rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                                >
                                    <XIcon className="size-3.5" />
                                </span>
                            ) : null}
                            <ChevronsUpDownIcon className="size-4 text-muted-foreground" />
                        </span>
                    </button>
                </PopoverTrigger>
            </div>
            <PopoverContent
                align="start"
                className={cn(
                    'w-(--radix-popover-trigger-width) min-w-56 p-0',
                    contentClassName,
                )}
            >
                <Command
                    filter={(itemValue, search, keywords) =>
                        fold((keywords ?? []).join(' ')).includes(fold(search))
                            ? 1
                            : 0
                    }
                >
                    {(searchable ?? options.length > SEARCH_THRESHOLD) ? (
                        <CommandInput placeholder={searchPlaceholder} />
                    ) : null}
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {options.map((o) => (
                                <CommandItem
                                    key={o.value}
                                    value={
                                        o.value === '' ? '__empty__' : o.value
                                    }
                                    keywords={[o.label]}
                                    disabled={o.disabled}
                                    onSelect={() => choose(o.value)}
                                    style={
                                        o.depth
                                            ? {
                                                  paddingLeft: `${0.5 + o.depth * 0.75}rem`,
                                              }
                                            : undefined
                                    }
                                >
                                    <span className="min-w-0 flex-1 truncate">
                                        {o.label}
                                    </span>
                                    <CheckIcon
                                        className={cn(
                                            'size-4',
                                            o.value === current
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
