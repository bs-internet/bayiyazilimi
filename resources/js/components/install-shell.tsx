import type { ReactNode } from 'react';

import { PoweredBy } from '@/components/powered-by';
import { useBranding } from '@/hooks/use-branding';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Kurulum sihirbazı çerçevesi: logo, adım göstergesi, içerik kartı. */
export function InstallShell({
    step,
    children,
}: {
    step: number;
    children: ReactNode;
}) {
    const branding = useBranding();
    const steps = [t('Gereksinimler'), t('Veritabanı'), t('Site ve yönetici')];

    return (
        <div className="flex min-h-svh flex-col items-center bg-muted/40 px-4 py-10 md:py-16">
            <div className="flex w-full max-w-xl flex-col gap-8">
                <div className="flex flex-col items-center gap-3 text-center">
                    <img
                        src={branding.logo}
                        alt={branding.name}
                        className="h-10 w-auto object-contain dark:hidden"
                    />
                    <img
                        src={branding.logo_dark}
                        alt={branding.name}
                        className="hidden h-10 w-auto object-contain dark:block"
                    />
                    <h1 className="text-xl font-semibold">
                        {t('Bayi Yazılımı kurulumu')}
                    </h1>
                </div>

                <ol className="flex items-center justify-center gap-2 text-xs">
                    {steps.map((label, index) => (
                        <li key={label} className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'flex size-6 items-center justify-center rounded-full border font-medium tabular-nums',
                                    index + 1 < step &&
                                        'border-primary bg-primary text-primary-foreground',
                                    index + 1 === step &&
                                        'border-primary text-primary',
                                    index + 1 > step && 'text-muted-foreground',
                                )}
                            >
                                {index + 1}
                            </span>
                            <span
                                className={cn(
                                    index + 1 === step
                                        ? 'font-medium'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {label}
                            </span>
                            {index < steps.length - 1 && (
                                <span className="mx-1 h-px w-6 bg-border" />
                            )}
                        </li>
                    ))}
                </ol>

                <div className="rounded-xl border bg-card p-6 shadow-sm md:p-8">
                    {children}
                </div>

                <PoweredBy className="text-center" />
            </div>
        </div>
    );
}
