import { useState, useCallback, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

export interface AddCategoryButtonProps {
    onCreate: (name: string) => void;
}

export function AddCategoryButton({ onCreate }: AddCategoryButtonProps) {
    const { t } = useTranslation();
    const [isEditing, setIsEditing] = useState(false);
    const [value, setValue] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (isEditing) {
            inputRef.current?.focus();
        }
    }, [isEditing]);

    const handleCreate = useCallback(() => {
        const trimmed = value.trim();
        if (trimmed.length > 0) {
            onCreate(trimmed);
        }
        setValue('');
        setIsEditing(false);
    }, [value, onCreate]);

    const handleCancel = useCallback(() => {
        setValue('');
        setIsEditing(false);
    }, []);

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLInputElement>) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleCreate();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                handleCancel();
            }
        },
        [handleCreate, handleCancel],
    );

    const handleBlur = useCallback(() => {
        if (value.trim().length === 0) {
            handleCancel();
        } else {
            handleCreate();
        }
    }, [value, handleCancel, handleCreate]);

    if (isEditing) {
        return (
            <div className="glass-card-enhanced rounded-[var(--radius-lg)] p-3">
                <input
                    ref={inputRef}
                    type="text"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onBlur={handleBlur}
                    placeholder={t('servers.list.category_placeholder')}
                    className="w-full bg-transparent border-none outline-none text-sm text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]"
                    autoFocus
                />
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => setIsEditing(true)}
            className="flex items-center gap-2 w-full glass-card-enhanced rounded-[var(--radius-lg)] px-4 py-3 cursor-pointer text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] hover:border-[var(--color-border-hover)] transition-all duration-200"
        >
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span className="text-sm">{t('servers.list.add_category')}</span>
        </button>
    );
}
