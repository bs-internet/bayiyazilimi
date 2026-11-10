import { Plus, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';

type Tier = { min: string; rate: string };

function parse(value: string): Tier[] {
    try {
        const list: unknown = JSON.parse(value || '[]');

        return Array.isArray(list)
            ? list.map((row: Record<string, unknown>) => ({
                  min: String(row?.min ?? ''),
                  rate: String(row?.rate ?? ''),
              }))
            : [];
    } catch {
        return [];
    }
}

/**
 * Kademe listesi (en az değer → indirim oranı). Değer formda JSON metni olarak
 * tutulur; sunucu diziye çevirir.
 */
export function TiersInput({
    value,
    onChange,
    unit,
    invalid,
}: {
    value: string;
    onChange: (next: string) => void;
    /** En az değerin birimi (adet ya da para birimi simgesi). */
    unit: string;
    invalid?: boolean;
}) {
    const tiers = parse(value);
    const rows = tiers.length > 0 ? tiers : [{ min: '', rate: '' }];
    const save = (next: Tier[]) => onChange(JSON.stringify(next));
    const update = (index: number, patch: Partial<Tier>) =>
        save(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    return (
        <div className="flex flex-col gap-2">
            {rows.map((row, index) => (
                <div key={index} className="flex items-center gap-2 text-sm">
                    <span className="w-10 shrink-0 text-muted-foreground">
                        {t('En az')}
                    </span>
                    <Input
                        type="number"
                        min={0}
                        step="any"
                        className="w-28"
                        value={row.min}
                        onChange={(e) => update(index, { min: e.target.value })}
                        aria-invalid={invalid}
                        aria-label={t('En az')}
                    />
                    <span className="w-10 shrink-0 text-muted-foreground">
                        {unit}
                    </span>
                    <span className="text-muted-foreground">→ %</span>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        step="any"
                        className="w-24"
                        value={row.rate}
                        onChange={(e) =>
                            update(index, { rate: e.target.value })
                        }
                        aria-invalid={invalid}
                        aria-label={t('İndirim oranı')}
                    />
                    <span className="text-muted-foreground">
                        {t('indirim')}
                    </span>
                    {rows.length > 1 ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                                save(rows.filter((_, i) => i !== index))
                            }
                            aria-label={t('Kademeyi kaldır')}
                        >
                            <X />
                        </Button>
                    ) : null}
                </div>
            ))}
            {rows.length < 10 ? (
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => save([...rows, { min: '', rate: '' }])}
                    >
                        <Plus data-icon="inline-start" />
                        {t('Kademe ekle')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
