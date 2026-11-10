import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Müşteriye gösterilen banka hesabı (BankAccount::forDisplay). */
export type BankAccountInfo = {
    bank_name: string;
    branch: string | null;
    account_holder: string;
    iban: string;
    account_number: string | null;
    currency_code: string | null;
};

/**
 * Havale / EFT hesap listesi: vitrin sipariş ekranı ve üye "Ödemelerim".
 * Renkler dışarıdan gelen kapsayıcıdan devralınır.
 */
export function BankAccountList({
    accounts,
    className,
}: {
    accounts: BankAccountInfo[];
    className?: string;
}) {
    return (
        <ul className={cn('grid gap-3 sm:grid-cols-2', className)}>
            {accounts.map((account) => (
                <li
                    key={account.iban}
                    className="flex flex-col gap-0.5 rounded-md border border-current/15 p-3"
                >
                    <span className="font-semibold">
                        {account.bank_name}
                        {account.currency_code
                            ? ` (${account.currency_code})`
                            : ''}
                    </span>
                    {account.branch ? (
                        <span>
                            {t('Şube: :branch', { branch: account.branch })}
                        </span>
                    ) : null}
                    <span>
                        {t('Hesap sahibi: :account_holder', {
                            account_holder: account.account_holder,
                        })}
                    </span>
                    {account.account_number ? (
                        <span>
                            {t('Hesap no: :account_number', {
                                account_number: account.account_number,
                            })}
                        </span>
                    ) : null}
                    <span className="font-mono tracking-wide select-all">
                        {account.iban}
                    </span>
                </li>
            ))}
        </ul>
    );
}
