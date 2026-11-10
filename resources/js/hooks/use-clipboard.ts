// Credit: https://usehooks-ts.com/
import { useState } from 'react';
import { t } from '@/lib/i18n';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);

    const copy: CopyFn = async (text) => {
        if (!navigator?.clipboard) {
            console.warn(t('Clipboard not supported'));

            return false;
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopiedText(text);

            return true;
        } catch (error) {
            console.warn(t('Copy failed'), error);
            setCopiedText(null);

            return false;
        }
    };

    return [copiedText, copy];
}
