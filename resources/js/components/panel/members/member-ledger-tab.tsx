import { Link } from '@inertiajs/react';
import {
    LedgerAdminActions,
    LedgerReverseButton,
} from '@/components/ledger/ledger-admin-actions';
import { LedgerStatement } from '@/components/ledger/ledger-statement';
import {
    LedgerDues,
    LedgerSummaryCards,
} from '@/components/ledger/ledger-summary';
import { show as orderShow } from '@/routes/panel/shop/orders';
import type { LedgerData, Option } from '@/types';

/** Cari sekmesi: özet kartları, vadeler ve hesap ekstresi. */
export function MemberLedgerTab({
    ledger,
    collectionTypes,
}: {
    ledger: LedgerData;
    collectionTypes: Option<string>[];
}) {
    const orderLink = (order: { id: string; number: string }) => (
        <Link href={orderShow(order.id)} className="hover:underline">
            #{order.number}
        </Link>
    );

    return (
        <>
            <LedgerSummaryCards ledger={ledger} />
            <LedgerDues ledger={ledger} orderLink={orderLink} />
            <LedgerStatement
                ledger={ledger}
                paramPrefix="ledger_"
                orderLink={orderLink}
                rowAction={(row) => <LedgerReverseButton row={row} />}
                toolbar={
                    <LedgerAdminActions
                        ledger={ledger}
                        collectionTypes={collectionTypes}
                    />
                }
            />
        </>
    );
}
