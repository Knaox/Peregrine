/**
 * Shimmer skeleton that mirrors the ServerCard shape.
 */
export function ServerCardSkeleton() {
    return (
        <div className="flex h-36 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)]">
            {/* Banner skeleton */}
            <div className="banner-clip w-1/2 flex-shrink-0 skeleton-shimmer" />

            {/* Content skeleton */}
            <div className="flex min-w-0 flex-1 flex-col justify-center gap-3 py-4 pl-3 pr-4">
                <div className="h-5 w-3/4 rounded-[var(--radius-sm)] skeleton-shimmer" />
                <div className="h-3 w-1/2 rounded-[var(--radius-sm)] skeleton-shimmer" />
            </div>

            {/* Stats skeleton */}
            <div className="hidden flex-shrink-0 items-center gap-4 px-5 sm:flex">
                <div className="flex gap-2">
                    <div className="h-10 w-10 rounded-full skeleton-shimmer" />
                    <div className="h-10 w-10 rounded-full skeleton-shimmer" />
                </div>
                <div className="flex flex-col gap-2">
                    <div className="h-3 w-16 rounded-[var(--radius-sm)] skeleton-shimmer" />
                    <div className="h-3 w-14 rounded-[var(--radius-sm)] skeleton-shimmer" />
                    <div className="h-3 w-12 rounded-[var(--radius-sm)] skeleton-shimmer" />
                </div>
            </div>
        </div>
    );
}
