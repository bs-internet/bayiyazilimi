import { Badge } from '@/components/ui/badge';

/**
 * Üye alanı (bayi /app + müşteri /hesabim) durum → rozet varyantı eşlemeleri.
 * Sipariş, teklif, ödeme ve iade durumlarının tek kaynağı.
 */

export type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export function orderStatusVariant(status: string): BadgeVariant {
    switch (status) {
        case 'completed':
        case 'shipped':
            return 'default';
        case 'cancelled':
        case 'failed':
            return 'destructive';
        case 'processing':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function quoteStatusVariant(status: string): BadgeVariant {
    switch (status) {
        case 'approved':
        case 'ordered':
            return 'default';
        case 'sent':
            return 'secondary';
        case 'rejected':
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
}

export function paymentStatusVariant(status: string): BadgeVariant {
    switch (status) {
        case 'paid':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'refunded':
        case 'partial':
        case 'cancelled':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function returnStatusVariant(status: string): BadgeVariant {
    switch (status) {
        case 'approved':
        case 'received':
            return 'secondary';
        case 'refunded':
            return 'default';
        case 'rejected':
            return 'destructive';
        default:
            return 'outline';
    }
}

type StatusBadgeProps = { status: string; label: string };

export function OrderStatusBadge({ status, label }: StatusBadgeProps) {
    return <Badge variant={orderStatusVariant(status)}>{label}</Badge>;
}

export function QuoteStatusBadge({ status, label }: StatusBadgeProps) {
    return <Badge variant={quoteStatusVariant(status)}>{label}</Badge>;
}

export function PaymentStatusBadge({ status, label }: StatusBadgeProps) {
    return <Badge variant={paymentStatusVariant(status)}>{label}</Badge>;
}

export function ReturnStatusBadge({ status, label }: StatusBadgeProps) {
    return <Badge variant={returnStatusVariant(status)}>{label}</Badge>;
}
