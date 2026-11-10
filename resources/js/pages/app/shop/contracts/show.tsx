import { Head } from '@inertiajs/react';

import { RichContent } from '@/components/rich-content';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { index } from '@/routes/app/shop/contracts';

type Props = {
    contract: {
        title: string;
        content: string;
        updated_at: string | null;
    } | null;
    items: { title: string; href: string }[];
};

export default function ContractShow({ contract }: Props) {
    if (!contract) {
        return (
            <>
                <Head title={t('Sözleşmeler')} />
                <p className="py-10 text-center text-sm text-muted-foreground">
                    {t('Henüz sözleşme eklenmedi.')}
                </p>
            </>
        );
    }

    return (
        <>
            <Head title={contract.title} />

            <article className="flex flex-col gap-5">
                <header className="border-b pb-4">
                    <h2 className="text-lg font-semibold">{contract.title}</h2>
                    {contract.updated_at ? (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {t('Son güncelleme: :updated_at', {
                                updated_at: formatDateTime(contract.updated_at),
                            })}
                        </p>
                    ) : null}
                </header>
                <RichContent html={contract.content} />
            </article>
        </>
    );
}

ContractShow.layout = {
    breadcrumbs: [{ title: t('Sözleşmeler'), href: index() }],
};
