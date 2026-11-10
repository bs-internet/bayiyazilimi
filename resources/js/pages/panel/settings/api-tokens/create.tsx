import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { create, index, store } from '@/routes/panel/settings/api-tokens';
import type { Option } from '@/types';

type Props = {
    abilities: Option[];
};

export default function CreateApiToken({ abilities }: Props) {
    const form = useForm<{
        name: string;
        abilities: string[];
        expires_at: string;
    }>({
        name: '',
        abilities: [],
        expires_at: '',
    });

    const toggle = (value: string, checked: boolean) => {
        form.setData(
            'abilities',
            checked
                ? [...form.data.abilities, value]
                : form.data.abilities.filter((item) => item !== value),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url);
    };

    const abilityError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('abilities'),
    )?.[1];

    return (
        <>
            <Head title={t('Yeni API anahtarı')} />

            <div className="flex max-w-5xl flex-col gap-8">
                <div className="border-b pb-5">
                    <h1 className="text-xl font-semibold">
                        {t('Yeni API anahtarı')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Anahtar sizin adınıza çalışır; yalnız seçtiğiniz işlemleri yapabilir. Oluşturulan anahtar listede yalnız bir kez gösterilir.',
                        )}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="max-w-3xl space-y-5 rounded-lg border p-5"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="token-name">
                                {t('Anahtar adı')}
                            </Label>
                            <Input
                                id="token-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder={t('ör. Muhasebe entegrasyonu')}
                                maxLength={100}
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="token-expires">
                                {t('Geçerlilik sonu (isteğe bağlı)')}
                            </Label>
                            <Input
                                id="token-expires"
                                type="date"
                                value={form.data.expires_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expires_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.expires_at} />
                        </div>
                    </div>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">
                            {t('Yetkiler')}
                        </legend>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {abilities.map((ability) => (
                                <label
                                    key={ability.value}
                                    className="flex items-start gap-3 rounded-md border p-3 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.abilities.includes(
                                            ability.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                ability.value,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span className="space-y-0.5">
                                        <span className="block">
                                            {ability.label}
                                        </span>
                                        <code className="block text-xs text-muted-foreground">
                                            {ability.value}
                                        </code>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={abilityError} />
                    </fieldset>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Spinner /> : <Plus />}
                            {t('Anahtar oluştur')}
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={index()}>
                                <X />
                                {t('Vazgeç')}
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateApiToken.layout = {
    breadcrumbs: [
        { title: t('API Anahtarları'), href: index() },
        { title: t('Yeni anahtar'), href: create() },
    ],
};
