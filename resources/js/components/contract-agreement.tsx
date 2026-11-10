import { Fragment, useState } from 'react';

import { RichContent } from '@/components/rich-content';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';
import type { ContractInfo } from '@/types';

/**
 * Onay kutusunun metni: "A, B ve C'yi okudum, onaylıyorum." Her sözleşme
 * adı, metnini pencerede açan bir bağlantıdır (ödeme ve üyelik formu ortak;
 * misafir de görebilsin diye ayrı bir adres gerekmez).
 */
export function ContractAgreementLabel({
    contracts,
    linkClass = 'font-medium text-primary underline underline-offset-2',
}: {
    contracts: ContractInfo[];
    linkClass?: string;
}) {
    const [open, setOpen] = useState<ContractInfo | null>(null);

    return (
        <>
            {contracts.map((contract, i) => (
                <Fragment key={contract.slug}>
                    {i > 0
                        ? i === contracts.length - 1
                            ? ` ${t('ve')} `
                            : ', '
                        : null}
                    <button
                        type="button"
                        className={`cursor-pointer ${linkClass}`}
                        onClick={(e) => {
                            // Etiketin içindeki bağlantı kutuyu işaretlemesin.
                            e.preventDefault();
                            setOpen(contract);
                        }}
                    >
                        {contract.title}
                    </button>
                </Fragment>
            ))}{' '}
            {t('metinlerini okudum, onaylıyorum.')}
            <Dialog
                open={open !== null}
                onOpenChange={(o) => (o ? null : setOpen(null))}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{open?.title}</DialogTitle>
                    </DialogHeader>
                    {open ? <RichContent html={open.content} /> : null}
                </DialogContent>
            </Dialog>
        </>
    );
}
