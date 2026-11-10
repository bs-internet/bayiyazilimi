import { Head } from '@inertiajs/react';
import { Handshake, Mail, MailCheck, RefreshCw, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import MembersController from '@/actions/App/Http/Controllers/Panel/Members/MembersController';
import { Page, PageTitle } from '@/components/admin/page';
import {
    CustomerTypeBadge,
    MemberStatusBadge,
} from '@/components/panel/member-parts';
import { DealerDialog } from '@/components/panel/members/dealer-dialog';
import { EmailChangeDialog } from '@/components/panel/members/email-change-dialog';
import { MemberActivitiesTab } from '@/components/panel/members/member-activities-tab';
import { MemberAddressesTab } from '@/components/panel/members/member-addresses-tab';
import { MemberLedgerTab } from '@/components/panel/members/member-ledger-tab';
import { MemberOrdersTab } from '@/components/panel/members/member-orders-tab';
import { MemberQuotesTab } from '@/components/panel/members/member-quotes-tab';
import { MemberReturnsTab } from '@/components/panel/members/member-returns-tab';
import { MemberSessionsTab } from '@/components/panel/members/member-sessions-tab';
import { MemberSummaryCard } from '@/components/panel/members/member-summary-card';
import { StatusDialog } from '@/components/panel/members/status-dialog';
import { TabCount } from '@/components/panel/members/tab-count';
import type { MemberDetail } from '@/components/panel/members/types';
import { VerifyEmailDialog } from '@/components/panel/members/verify-email-dialog';
import { TwoFactorResetDialog } from '@/components/panel/two-factor-reset-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { t } from '@/lib/i18n';
import { index } from '@/routes/panel/members';
import type {
    Activity as ActivityItem,
    CreditTermOptions,
    EditableUserStatus,
    LedgerData,
    MemberBilling,
    MemberOverview,
    Option,
    UserSessionLog,
} from '@/types';

type Props = {
    user: {
        data: MemberDetail;
    };
    dealerGroups: Option<string>[];
    warehouses: Option<string>[];
    activities: {
        data: ActivityItem[];
    };
    sessions: {
        data: UserSessionLog[];
    };
    overview: MemberOverview;
    billing: MemberBilling | null;
    /** Cari (yetki varsa). */
    ledger: LedgerData | null;
    collectionTypes: Option<string>[];
    termOptions: CreditTermOptions;
    /** Müşteri hesapları var mı (vitrin modülü)? */
    customersEnabled: boolean;
};

export default function Show({
    user,
    activities,
    sessions,
    dealerGroups,
    warehouses,
    overview,
    billing,
    ledger,
    collectionTypes,
    termOptions,
    customersEnabled,
}: Props) {
    const currentUser = user.data;
    const activityItems = activities.data;
    const sessionItems = sessions.data;
    const [verifyEmailDialogOpen, setVerifyEmailDialogOpen] =
        useState<boolean>(false);
    const [emailChangeDialogOpen, setEmailChangeDialogOpen] =
        useState<boolean>(false);
    const [statusDialogOpen, setStatusDialogOpen] = useState<boolean>(false);
    const [twoFactorDialogOpen, setTwoFactorDialogOpen] =
        useState<boolean>(false);
    const [dealerDialogOpen, setDealerDialogOpen] = useState<boolean>(false);
    const isDealer = currentUser.customer_type === 'dealer';
    const [statusValue, setStatusValue] = useState<EditableUserStatus>(
        currentUser.status === 'active' ? 'passive' : 'active',
    );
    const accountId = currentUser.account_id ?? null;
    const accountQuery = accountId ? { query: { account: accountId } } : null;

    const { orders, quotes, returns, addresses } = overview;

    const openStatusDialog = (): void => {
        setStatusValue(currentUser.status === 'active' ? 'passive' : 'active');
        setStatusDialogOpen(true);
    };

    return (
        <>
            <Head title={currentUser.name} />

            <Page>
                <PageTitle
                    trail={[
                        { title: t('Bayiler'), href: index() },
                        { title: currentUser.name },
                    ]}
                    title={currentUser.name}
                    badges={
                        <>
                            <CustomerTypeBadge user={currentUser} />
                            <MemberStatusBadge status={currentUser.status} />
                            {!currentUser.email_verified_at && (
                                <Badge variant="outline">
                                    {t('E-posta doğrulanmadı')}
                                </Badge>
                            )}
                        </>
                    }
                    actions={
                        <>
                            {!currentUser.email_verified_at && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setVerifyEmailDialogOpen(true)
                                    }
                                >
                                    <MailCheck data-icon="inline-start" />
                                    {t('E-postayı onayla')}
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => setEmailChangeDialogOpen(true)}
                            >
                                <Mail data-icon="inline-start" />
                                {t('E-posta değiştir')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={openStatusDialog}
                            >
                                <RefreshCw data-icon="inline-start" />
                                {t('Durum değiştir')}
                            </Button>
                            {currentUser.two_factor_enabled && (
                                <Button
                                    variant="outline"
                                    onClick={() => setTwoFactorDialogOpen(true)}
                                >
                                    <ShieldOff data-icon="inline-start" />
                                    {t('2FA sıfırla')}
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => setDealerDialogOpen(true)}
                            >
                                <Handshake data-icon="inline-start" />
                                {isDealer
                                    ? t('Bayi ayarları')
                                    : t('Bayiye çevir')}
                            </Button>
                        </>
                    }
                />

                <MemberSummaryCard
                    user={currentUser}
                    billing={billing}
                    overview={overview}
                />

                <Tabs
                    defaultValue={
                        new URLSearchParams(window.location.search).has(
                            'ledger_from',
                        ) ||
                        new URLSearchParams(window.location.search).has(
                            'ledger_to',
                        )
                            ? 'ledger'
                            : 'orders'
                    }
                    className="min-w-0 gap-4"
                >
                    <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                        <TabsList>
                            <TabsTrigger value="orders">
                                {t('Siparişler')}
                                <TabCount value={orders.total} />
                            </TabsTrigger>
                            {ledger && (
                                <TabsTrigger value="ledger">
                                    {t('Cari')}
                                </TabsTrigger>
                            )}
                            <TabsTrigger value="quotes">
                                {t('Teklifler')}
                                <TabCount value={quotes.total} />
                            </TabsTrigger>
                            <TabsTrigger value="returns">
                                {t('İadeler')}
                                <TabCount value={returns.total} />
                            </TabsTrigger>
                            <TabsTrigger value="addresses">
                                {t('Adresler')}
                                <TabCount value={addresses.length} />
                            </TabsTrigger>
                            <TabsTrigger value="activities">
                                {t('Etkinlik')}
                            </TabsTrigger>
                            <TabsTrigger value="sessions">
                                {t('Oturumlar')}
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="orders">
                        <MemberOrdersTab
                            orders={orders}
                            accountQuery={accountQuery}
                        />
                    </TabsContent>

                    {ledger && (
                        <TabsContent
                            value="ledger"
                            className="flex flex-col gap-4"
                        >
                            <MemberLedgerTab
                                ledger={ledger}
                                collectionTypes={collectionTypes}
                            />
                        </TabsContent>
                    )}

                    <TabsContent value="quotes">
                        <MemberQuotesTab
                            quotes={quotes}
                            accountQuery={accountQuery}
                        />
                    </TabsContent>

                    <TabsContent value="returns">
                        <MemberReturnsTab
                            returns={returns}
                            accountQuery={accountQuery}
                        />
                    </TabsContent>

                    <TabsContent
                        value="addresses"
                        className="flex flex-col gap-6"
                    >
                        <MemberAddressesTab addresses={addresses} />
                    </TabsContent>

                    <TabsContent value="activities">
                        <MemberActivitiesTab activities={activityItems} />
                    </TabsContent>

                    <TabsContent value="sessions">
                        <MemberSessionsTab sessions={sessionItems} />
                    </TabsContent>
                </Tabs>
            </Page>

            <VerifyEmailDialog
                user={currentUser}
                open={verifyEmailDialogOpen}
                onOpenChange={setVerifyEmailDialogOpen}
            />

            <EmailChangeDialog
                user={currentUser}
                open={emailChangeDialogOpen}
                onOpenChange={setEmailChangeDialogOpen}
            />

            <DealerDialog
                user={currentUser}
                dealerGroups={dealerGroups}
                warehouses={warehouses}
                termOptions={termOptions}
                customersEnabled={customersEnabled}
                open={dealerDialogOpen}
                onOpenChange={setDealerDialogOpen}
            />

            <TwoFactorResetDialog
                name={currentUser.name}
                form={MembersController.resetTwoFactor.form(currentUser)}
                open={twoFactorDialogOpen}
                onOpenChange={setTwoFactorDialogOpen}
            />

            <StatusDialog
                user={currentUser}
                open={statusDialogOpen}
                onOpenChange={setStatusDialogOpen}
                value={statusValue}
                onValueChange={setStatusValue}
            />
        </>
    );
}

Show.layout = {
    breadcrumbs: [
        {
            title: t('Bayiler'),
            href: index(),
        },
    ],
};
