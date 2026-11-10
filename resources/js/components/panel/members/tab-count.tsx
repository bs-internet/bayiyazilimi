export function TabCount({ value }: { value: number }) {
    return value > 0 ? (
        <span className="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums">
            {value}
        </span>
    ) : null;
}
