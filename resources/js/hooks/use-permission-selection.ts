import { useMemo, useState } from 'react';

import type { PermissionRow } from '@/types';

/**
 * Görev ekranındaki izin seçimi; yetki bağımlılıklarını uygular
 * (sunucudaki PermissionDependencies ile aynı harita):
 *  - bir izin işaretlenince gerektirdiği izinler de işaretlenir,
 *  - bir iznin işareti kaldırılınca ona bağlı izinlerin işareti de kalkar.
 */
export function usePermissionSelection(
    groups: Record<string, PermissionRow[]>,
    initial: string[] = [],
) {
    const { requires, dependents, labels } = useMemo(() => {
        const requires = new Map<string, string[]>();
        const dependents = new Map<string, string[]>();
        const labels = new Map<string, string>();

        for (const row of Object.values(groups).flat()) {
            labels.set(row.name, row.label);
            requires.set(row.name, row.requires ?? []);

            for (const required of row.requires ?? []) {
                dependents.set(required, [
                    ...(dependents.get(required) ?? []),
                    row.name,
                ]);
            }
        }

        return { requires, dependents, labels };
    }, [groups]);

    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(initial),
    );

    /** İzin + dolaylı ilişkilileri (graph üzerinde tek yönlü gezinti). */
    const walk = (names: string[], edges: Map<string, string[]>): string[] => {
        const seen = new Set<string>();
        const stack = [...names];

        while (stack.length > 0) {
            const name = stack.pop()!;

            if (seen.has(name)) {
                continue;
            }

            seen.add(name);
            stack.push(...(edges.get(name) ?? []));
        }

        return [...seen];
    };

    const add = (names: string[]) =>
        setSelected((prev) => {
            const next = new Set(prev);
            walk(names, requires).forEach((n) => next.add(n));

            return next;
        });

    const remove = (names: string[]) =>
        setSelected((prev) => {
            const next = new Set(prev);
            walk(names, dependents).forEach((n) => next.delete(n));

            return next;
        });

    const toggle = (name: string) =>
        selected.has(name) ? remove([name]) : add([name]);

    const toggleGroup = (rows: PermissionRow[]) => {
        const names = rows.map((r) => r.name);

        if (names.every((n) => selected.has(n))) {
            remove(names);
        } else {
            add(names);
        }
    };

    /** "Sipariş detayını görüntüle gerektirir" notu için ön koşul adları. */
    const requiredLabels = (name: string): string[] =>
        (requires.get(name) ?? []).map((n) => labels.get(n) ?? n);

    return { selected, toggle, toggleGroup, requiredLabels };
}
