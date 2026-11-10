import { Form, Head, Link } from '@inertiajs/react';
import { UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import MembersController from '@/actions/App/Http/Controllers/Panel/Members/MembersController';
import {
    EntityLayout,
    EntityMain,
    EntitySidebar,
    Page,
    PageTitle,
    SectionCard,
} from '@/components/admin/page';
import InputError from '@/components/input-error';
import { DealerFields } from '@/components/shop/dealer-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { create, index } from '@/routes/panel/members';
import type { Option, UserStatus } from '@/types';

type Props = {
    statuses: Option<UserStatus>[];
    dealerGroups: Option<string>[];
    warehouses: Option<string>[];
};

export default function Create({ statuses, dealerGroups, warehouses }: Props) {
    const [status, setStatus] = useState<UserStatus>('active');
    const [emailVerified, setEmailVerified] = useState<boolean>(true);

    return (
        <>
            <Head title={t('Yeni Bayi')} />

            <Form {...MembersController.store.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <Page>
                        <input type="hidden" name="status" value={status} />
                        <input
                            type="hidden"
                            name="email_verified"
                            value={emailVerified ? '1' : '0'}
                        />

                        <PageTitle
                            trail={[
                                { title: t('Bayiler'), href: index() },
                                { title: t('Yeni bayi') },
                            ]}
                            title={t('Yeni bayi')}
                            description={t(
                                'Panelden eklenen hesaplar bayidir ve üye alanını (/app) kullanır. Bayiye şifresini belirlemesi için hoş geldin bağlantısı gönderilir.',
                            )}
                            actions={
                                <>
                                    <Button asChild variant="outline">
                                        <Link href={index()}>
                                            <X data-icon="inline-start" />
                                            {t('Vazgeç')}
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <UserPlus data-icon="inline-start" />
                                        )}
                                        {t('Bayi oluştur')}
                                    </Button>
                                </>
                            }
                        />

                        <EntityLayout>
                            <EntityMain>
                                <SectionCard title={t('Firma ve yetkili')}>
                                    <div className="grid gap-5">
                                        <div className="grid gap-2">
                                            <Label htmlFor="company">
                                                {t('Firma ünvanı')}
                                            </Label>
                                            <Input
                                                id="company"
                                                name="company"
                                                autoComplete="organization"
                                                placeholder={t(
                                                    'Örnek Ticaret Ltd. Şti.',
                                                )}
                                                aria-invalid={Boolean(
                                                    errors.company,
                                                )}
                                            />
                                            <InputError
                                                message={errors.company}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                {t('Yetkili ad soyad')}
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoComplete="name"
                                                placeholder={t('Ad soyad')}
                                                aria-invalid={Boolean(
                                                    errors.name,
                                                )}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                    </div>
                                </SectionCard>

                                <SectionCard
                                    title={t('Giriş')}
                                    description={t(
                                        'Bayi bu adresle giriş yapar; hoş geldin bağlantısı buraya gönderilir.',
                                    )}
                                >
                                    <div className="grid gap-5">
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                {t('E-posta adresi')}
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoComplete="email"
                                                placeholder={t(
                                                    'bayi@ornek.com',
                                                )}
                                                aria-invalid={Boolean(
                                                    errors.email,
                                                )}
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="flex items-start gap-3 rounded-lg border p-4">
                                            <Checkbox
                                                id="email_verified"
                                                checked={emailVerified}
                                                onCheckedChange={(checked) =>
                                                    setEmailVerified(
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="email_verified">
                                                    {t(
                                                        'E-posta adresini onayla',
                                                    )}
                                                </Label>
                                                <p className="text-sm text-muted-foreground">
                                                    {t(
                                                        'Seçiliyse bayinin e-posta adresi onaylanmış olarak oluşturulur. Seçili değilse hoş geldin bağlantısı önce e-postayı onaylar, sonra şifre belirleme ekranına yönlendirir.',
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </SectionCard>
                            </EntityMain>

                            <EntitySidebar>
                                <SectionCard title={t('Durum')}>
                                    <div className="grid gap-2">
                                        <Select
                                            value={status}
                                            onValueChange={(value) =>
                                                setStatus(value as UserStatus)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-label={t('Durum')}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {statuses.map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                </SectionCard>

                                <SectionCard title={t('Bayi grubu ve indirim')}>
                                    <div className="grid gap-5">
                                        <DealerFields
                                            groups={dealerGroups}
                                            warehouses={warehouses}
                                            errors={errors}
                                        />
                                    </div>
                                </SectionCard>
                            </EntitySidebar>
                        </EntityLayout>
                    </Page>
                )}
            </Form>
        </>
    );
}

Create.layout = {
    breadcrumbs: [
        {
            title: t('Yeni Bayi'),
            href: create(),
        },
    ],
};
