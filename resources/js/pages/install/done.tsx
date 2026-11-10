import { Head } from '@inertiajs/react';
import { CircleCheck, LogIn } from 'lucide-react';

import { InstallShell } from '@/components/install-shell';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

type Props = {
    admin_email: string;
    login_url: string;
    sample_data: boolean;
};

export default function InstallDone({
    admin_email,
    login_url,
    sample_data,
}: Props) {
    return (
        <>
            <Head title={t('Kurulum tamamlandı')} />

            <InstallShell step={4}>
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-3 text-center">
                        <CircleCheck className="size-10 text-emerald-600" />
                        <h2 className="text-lg font-semibold">
                            {t('Kurulum tamamlandı')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                ':email adresi ve belirlediğiniz şifreyle panele giriş yapabilirsiniz.',
                                { email: admin_email },
                            )}
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 rounded-lg bg-muted/60 p-4 text-sm">
                        <p className="font-medium">{t('Sıradaki adımlar')}</p>
                        <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                            <li>
                                {t(
                                    'Logo, iletişim ve firma bilgilerini girin: Ayarlar → Genel Ayarlar.',
                                )}
                            </li>
                            <li>
                                {t(
                                    'Ürünleri ekleyin ya da Excel ile içe aktarın: Mağaza → Ürünler.',
                                )}
                            </li>
                            <li>
                                {t(
                                    'Sunucunuzda zamanlanmış görev (cron) tanımlayın: * * * * * php artisan schedule:run',
                                )}
                            </li>
                            {sample_data && (
                                <li>
                                    {t(
                                        'Örnek bayi ve müşteri hesapları rastgele şifreyle oluşturuldu; denemek için panelden şifrelerini belirleyin.',
                                    )}
                                </li>
                            )}
                        </ul>
                    </div>

                    <Button asChild className="self-center">
                        <a href={login_url}>
                            <LogIn />
                            {t('Giriş yap')}
                        </a>
                    </Button>
                </div>
            </InstallShell>
        </>
    );
}
