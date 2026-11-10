import { Plus } from 'lucide-react';
import { Fragment } from 'react';

import { SectionCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { RuleRow } from './rule-row';
import type { SectionProps } from './types';
import { newRule } from './types';

/** "Kime / ne zaman" koşul grupları. */
export function AudienceSection(props: SectionProps) {
    const {
        data,
        errors,
        audienceDefs,
        products,
        rememberProducts,
        setGroups,
    } = props;

    return (
        <SectionCard
            title={t('Kime / ne zaman')}
            description={t(
                'Kampanyanın sepete uygulanma koşulları. Bir grubun içindeki koşulların hepsi (ve), grupların ise biri (veya) sağlanmalı. Boş bırakılırsa herkese, her sepette.',
            )}
        >
            <div className="flex flex-col gap-3">
                {data.audience_rules.map((group, g) => (
                    <Fragment key={g}>
                        {g > 0 ? (
                            <div className="flex items-center gap-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <span className="h-px flex-1 bg-border" />
                                {t('veya')}
                                <span className="h-px flex-1 bg-border" />
                            </div>
                        ) : null}
                        <div className="flex flex-col gap-2">
                            {group.map((rule, r) => (
                                <Fragment key={r}>
                                    {r > 0 ? (
                                        <span className="pl-3 text-xs font-medium text-muted-foreground">
                                            {t('ve')}
                                        </span>
                                    ) : null}
                                    <RuleRow
                                        rule={rule}
                                        defs={audienceDefs}
                                        errors={errors}
                                        errorKey={`audience_rules.${g}.${r}`}
                                        products={products}
                                        rememberProducts={rememberProducts}
                                        onChange={(next) =>
                                            setGroups(
                                                data.audience_rules.map(
                                                    (gr, gi) =>
                                                        gi === g
                                                            ? gr.map(
                                                                  (ru, ri) =>
                                                                      ri === r
                                                                          ? next
                                                                          : ru,
                                                              )
                                                            : gr,
                                                ),
                                            )
                                        }
                                        onRemove={() =>
                                            setGroups(
                                                data.audience_rules.map(
                                                    (gr, gi) =>
                                                        gi === g
                                                            ? gr.filter(
                                                                  (_, ri) =>
                                                                      ri !== r,
                                                              )
                                                            : gr,
                                                ),
                                            )
                                        }
                                    />
                                </Fragment>
                            ))}
                            <div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setGroups(
                                            data.audience_rules.map((gr, gi) =>
                                                gi === g
                                                    ? [
                                                          ...gr,
                                                          newRule(
                                                              audienceDefs[0],
                                                          ),
                                                      ]
                                                    : gr,
                                            ),
                                        )
                                    }
                                >
                                    <Plus data-icon="inline-start" />
                                    {t('Koşul ekle (ve)')}
                                </Button>
                            </div>
                        </div>
                    </Fragment>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setGroups([
                                ...data.audience_rules,
                                [newRule(audienceDefs[0])],
                            ])
                        }
                    >
                        <Plus data-icon="inline-start" />
                        {data.audience_rules.length
                            ? t('Koşul grubu ekle (veya)')
                            : t('Koşul ekle')}
                    </Button>
                </div>
            </div>
        </SectionCard>
    );
}
