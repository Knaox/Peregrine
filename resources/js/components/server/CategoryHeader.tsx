import { useState, useCallback, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';

export interface CategoryHeaderProps {
    categoryId: string;
    name: string;
    count: number;
    dragHandleProps: {
        onPointerDown: (e: React.PointerEvent) => void;
        style: React.CSSProperties;
        'data-drag-id': string;
        'data-drag-zone': string;
    };
    onRename: (newName: string) => void;
    onDelete: () => void;
}

export function CategoryHeader({
    categoryId,
    name,
    count,
    dragHandleProps,
    onRename,
    onDelete,
}: CategoryHeaderProps) {
    const { t } = useTranslation();
    const [isEditing, setIsEditing] = useState(false);
    const [editValue, setEditValue] = useState(name);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (isEditing) {
            setEditValue(name);
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [isEditing, name]);

    const confirmRename = useCallback(() => {
        const trimmed = editValue.trim();
        if (trimmed.length > 0 && trimmed !== name) {
            onRename(trimmed);
        }
        setIsEditing(false);
    }, [editValue, name, onRename]);

    const cancelEdit = useCallback(() => {
        setEditValue(name);
        setIsEditing(false);
    }, [name]);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLInputElement>) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmRename();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelEdit();
            }
        },
        [confirmRename, cancelEdit],
    );

    const handleDelete = useCallback(() => {
        const confirmed = window.confirm(t('servers.list.delete_category_confirm'));
        if (confirmed) onDelete();
    }, [t, onDelete]);

    return (
        <m.div
            initial={{ opacity: 0, scaleX: 0.5 }}
            animate={{ opacity: 1, scaleX: 1 }}
            transition={{ duration: 0.4 }}
            className="flex items-center gap-3 py-5"
            data-category-id={categoryId}
        >
            {/* Gradient line left */}
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />

            {/* Drag handle */}
            <div
                {...dragHandleProps}
                className="flex flex-col gap-[3px] p-1.5 rounded-[var(--radius-sm)] opacity-40 hover:opacity-100 transition-opacity"
                title="Drag to reorder"
            >
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

            {/* Category name / inline edit */}
            <div className="flex items-center gap-2.5">
                {isEditing ? (
                    <input
                        ref={inputRef}
                        type="text"
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        onKeyDown={handleKeyDown}
                        onBlur={confirmRename}
                        className="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-sm)] px-2 py-0.5 text-xs uppercase tracking-[0.2em] font-semibold text-[var(--color-text-primary)] outline-none focus:border-[var(--color-primary)]"
                        autoFocus
                    />
                ) : (
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-[var(--color-text-secondary)]">
                        {name}
                    </span>
                )}

                {/* Count badge */}
                <span
                    className="flex h-5 min-w-5 items-center justify-center rounded-[var(--radius-full)] px-1.5 text-[0.65rem] font-semibold"
                    style={{
                        background: 'rgba(var(--color-primary-rgb), 0.1)',
                        color: 'var(--color-primary)',
                        border: '1px solid rgba(var(--color-primary-rgb), 0.2)',
                    }}
                >
                    {count}
                </span>
            </div>

            {/* Action buttons */}
            <div className="flex items-center gap-1">
                {/* Edit button */}
                <button
                    type="button"
                    onClick={() => setIsEditing(true)}
                    className="flex h-6 w-6 items-center justify-center rounded-[var(--radius-sm)] cursor-pointer opacity-40 hover:opacity-100 hover:bg-[var(--color-surface-hover)] transition-all"
                    title={t('common.edit')}
                >
                    <svg className="h-3.5 w-3.5 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </button>

                {/* Delete button */}
                <button
                    type="button"
                    onClick={handleDelete}
                    className="flex h-6 w-6 items-center justify-center rounded-[var(--radius-sm)] cursor-pointer opacity-40 hover:opacity-100 hover:bg-[rgba(var(--color-danger-rgb),0.1)] transition-all"
                    title={t('common.delete')}
                >
                    <svg className="h-3.5 w-3.5 text-[var(--color-danger)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>

            {/* Gradient line right */}
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
        </m.div>
    );
}
