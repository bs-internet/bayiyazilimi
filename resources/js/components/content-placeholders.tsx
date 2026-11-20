import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

import { SectionCard } from '@/components/admin/page';
import { t } from '@/lib/i18n';
import type { ContentPlaceholder } from '@/types';

/**
 * Sözleşme / sayfa düzenleme ekranındaki yer tutucu listesi. Metne yazılan
 * `{unvan}` gibi kalıplar gösterilirken Kurumsal Bilgiler ve Banka
 * Hesapları'ndaki güncel değerlerle doldurulur. Tıklayınca panoya kopyalanır.
 */
export function ContentPlaceholders({
    placeholders,
}: {
    placeholders: ContentPlaceholder[];
}) {
    const [copied, setCopied] = useState<string | null>(null);

    if (placeholders.length === 0) {
        return null;
    }

    const copy = (token: string) => {
        void navigator.clipboard?.writeText(token).then(() => {
            setCopied(token);
            window.setTimeout(() => setCopied(null), 1500);
        });
    };

    return (
        <SectionCard
            title={t('Yer tutucular')}
            description={t(
                'Metne yazın; gösterilirken Kurumsal Bilgiler ve Banka Hesapları’ndaki güncel değerle doldurulur.',
            )}
        >
            <ul className="flex flex-col gap-1.5 text-sm">
                {placeholders.map((item) => (
                    <li
                        key={item.token}
                        className="flex items-center justify-between gap-2"
                    >
                        <button
                            type="button"
                            onClick={() => copy(item.token)}
                            className="inline-flex cursor-pointer items-center gap-1.5 rounded bg-muted px-1.5 py-0.5 font-mono text-xs hover:bg-muted/70"
                            title={t('Kopyala')}
                        >
                            {item.token}
                            {copied === item.token ? (
                                <Check className="size-3" />
                            ) : (
                                <Copy className="size-3 opacity-60" />
                            )}
                        </button>
                        <span className="text-right text-muted-foreground">
                            {item.label}
                        </span>
                    </li>
                ))}
            </ul>
        </SectionCard>
    );
}
