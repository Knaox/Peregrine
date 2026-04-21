/**
 * 6-dot grip affordance rendered to the left of each server card when the
 * dashboard is in drag-reorder mode. Pure visual — no behavior; the drag
 * handlers come from usePointerDrag via the parent's getDragHandleProps().
 */
export function GripIcon(props: Record<string, unknown>) {
    return (
        <div {...props} className="flex flex-col gap-[3px] p-1 opacity-30 hover:opacity-80 transition-opacity">
            <div className="flex gap-[3px]">
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
            </div>
            <div className="flex gap-[3px]">
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
            </div>
            <div className="flex gap-[3px]">
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
            </div>
        </div>
    );
}
