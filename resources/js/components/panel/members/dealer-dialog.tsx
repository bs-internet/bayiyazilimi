import { Form } from '@inertiajs/react';
import { Handshake, RefreshCw, X } from 'lucide-react';
import { CreditTermsFields } from '@/components/ledger/credit-terms-fields';
import type { MemberDetail } from '@/components/panel/members/types';
import { DealerFields } from '@/components/shop/dealer-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { type as customerType } from '@/routes/panel/shop/customers';
import type { CreditTermOptions, Option } from '@/types';

/** Bayi ayarları / bayiye çevirme penceresi. */
export function DealerDialog({
    user,
    dealerGroups,
    warehouses,
    termOptions,
    customersEnabled,
    open,
    onOpenChange,
}: {
    user: MemberDetail;
    dealerGroups: Option<string>[];
    warehouses: Option<string>[];
    termOptions: CreditTermOptions;
    /** Müşteri hesapları var mı (vitrin)? Yoksa "Müşteriye çevir" gösterilmez. */
    customersEnabled: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isDealer = user.customer_type === 'dealer';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isDealer ? t('Bayi ayarları') : t('Bayiye çevir')}
                    </DialogTitle>
                    <DialogDescription>
                        {isDealer
                            ? t(
                                  'Bayi grubunu ve bayiye özel indirimi güncelleyin ya da hesabı yeniden müşteriye çevirin.',
                              )
                            : t(
                                  'Hesap bayiye çevrilince üye alanını (/app) kullanır ve bayi indirimi uygulanır.',
                              )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={`${user.customer_type}-${user.dealer_group?.id}-${user.discount_rate}-${user.warehouse?.id}`}
                    {...customerType.form(user.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <DealerFields
                                groups={dealerGroups}
                                defaultGroupId={
                                    isDealer
                                        ? (user.dealer_group?.id ?? '__none')
                                        : undefined
                                }
                                defaultRate={user.discount_rate}
                                warehouses={warehouses}
                                defaultWarehouseId={user.warehouse?.id ?? null}
                                errors={errors}
                            />

                            <CreditTermsFields
                                values={user.credit_terms}
                                options={termOptions}
                                override
                                errors={errors}
                            />

                            <DialogFooter className="sm:justify-between">
                                {isDealer && customersEnabled ? (
                                    <Button
                                        type="submit"
                                        name="type"
                                        value="customer"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <RefreshCw />
                                        {t('Müşteriye çevir')}
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <X />
                                            {t('Vazgeç')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        name="type"
                                        value="dealer"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Handshake />
                                        )}
                                        {isDealer
                                            ? t('Kaydet')
                                            : t('Bayiye çevir')}
                                    </Button>
                                </div>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
