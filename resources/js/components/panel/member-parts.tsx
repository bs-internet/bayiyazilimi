import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import type { MemberCustomerInfo, PanelUser } from '@/types';

/** Panel → Müşteriler sayfalarının ortak küçük parçaları. */

export function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toLocaleUpperCase('tr-TR');
}

export function memberStatusLabel(status: PanelUser['status']): string {
    switch (status) {
        case 'active':
            return 'Aktif';
        case 'passive':
            return 'Pasif';
        case 'draft':
            return t('Kısıtlı');
        default:
            return String(status);
    }
}

const statusTone: Record<string, string> = {
    active: 'border-transparent bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    passive: 'border-transparent bg-destructive/10 text-destructive',
    draft: 'border-transparent bg-amber-500/10 text-amber-700 dark:text-amber-400',
};

export function MemberStatusBadge({ status }: { status: PanelUser['status'] }) {
    return (
        <Badge variant="outline" className={statusTone[String(status)]}>
            {memberStatusLabel(status)}
        </Badge>
    );
}

export function CustomerTypeBadge({ user }: { user: MemberCustomerInfo }) {
    if (user.customer_type === 'dealer') {
        return (
            <Badge className="border-transparent bg-primary/10 text-primary">
                {t('Bayi')}
            </Badge>
        );
    }

    return (
        <Badge variant="outline">
            {user.customer_type === 'customer'
                ? t('Müşteri')
                : t('Hesap açılmadı')}
        </Badge>
    );
}

/** Bayi grubu / indirim özeti: "Altın Bayi · %10 (özel)". */
export function dealerSummary(user: MemberCustomerInfo): string {
    return `${user.dealer_group?.name ?? t('Grupsuz')} · %${user.effective_discount_rate}${
        user.discount_rate !== null ? t(' (özel)') : ''
    }`;
}
