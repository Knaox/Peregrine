import { useId, useRef, useState } from 'react';
import clsx from 'clsx';
import { useTranslation } from 'react-i18next';
import { getCsrfHeaders } from '@/services/http';

interface MultiImageUploadFieldProps {
    label: string;
    value: string[];
    /** Slot id sent server-side (routes the upload). Reused across items. */
    slot: string;
    /** Hard cap — defaults to 8 (server-side validation also enforces 8). */
    max?: number;
    onChange: (next: string[]) => void;
    description?: string;
}

interface UploadResponse {
    path: string;
    url: string;
}

/**
 * Multi-image upload + ordered list. Each upload posts to the same
 * /api/admin/theme/upload-asset endpoint as the single ImageUploadField,
 * and the returned path is appended to the array. Items can be removed,
 * promoted (←), or demoted (→). Drag-to-reorder is left for a follow-up;
 * arrow buttons cover the same need with simpler a11y.
 */
export function MultiImageUploadField({
    label,
    value,
    slot,
    max = 8,
    onChange,
    description,
}: MultiImageUploadFieldProps) {
    const { t } = useTranslation();
    const id = useId();
    const fileRef = useRef<HTMLInputElement>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async (file: File): Promise<void> => {
        setIsUploading(true);
        setError(null);
        try {
            const form = new FormData();
            form.append('slot', slot);
            form.append('file', file);
            const response = await fetch('/api/admin/theme/upload-asset', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', ...getCsrfHeaders() },
                body: form,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = (await response.json()) as UploadResponse;
            onChange([...value, data.path]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'upload_failed');
        } finally {
            setIsUploading(false);
            if (fileRef.current) fileRef.current.value = '';
        }
    };

    const remove = (idx: number) => onChange(value.filter((_, i) => i !== idx));
    const swap = (a: number, b: number) => {
        if (a < 0 || b < 0 || a >= value.length || b >= value.length) return;
        const next = [...value];
        const tmp = next[a]!;
        next[a] = next[b]!;
        next[b] = tmp;
        onChange(next);
    };

    const canAdd = value.length < max;

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={id}
                className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]"
            >
                {label}{' '}
                <span className="text-[var(--color-text-muted)]">
                    ({value.length}/{max})
                </span>
            </label>
            <div className="flex flex-wrap gap-2">
                {value.map((path, idx) => (
                    <div
                        key={`${path}-${idx}`}
                        className="relative h-16 w-24 overflow-hidden rounded-lg border border-[var(--color-border)]"
                        style={{ background: `url("${path}") center/cover no-repeat` }}
                    >
                        <div className="absolute inset-x-0 bottom-0 flex items-center justify-between bg-black/55 px-1 py-0.5">
                            <button
                                type="button"
                                onClick={() => swap(idx, idx - 1)}
                                disabled={idx === 0}
                                aria-label={t('theme_studio.image_move_left', 'Move left')}
                                className="text-white/80 px-1 disabled:opacity-30 cursor-pointer hover:text-white"
                            >
                                ‹
                            </button>
                            <button
                                type="button"
                                onClick={() => remove(idx)}
                                aria-label={t('theme_studio.image_clear', 'Remove')}
                                className="text-white/80 px-1 cursor-pointer hover:text-[var(--color-danger)]"
                            >
                                ×
                            </button>
                            <button
                                type="button"
                                onClick={() => swap(idx, idx + 1)}
                                disabled={idx === value.length - 1}
                                aria-label={t('theme_studio.image_move_right', 'Move right')}
                                className="text-white/80 px-1 disabled:opacity-30 cursor-pointer hover:text-white"
                            >
                                ›
                            </button>
                        </div>
                    </div>
                ))}
                {canAdd && (
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        disabled={isUploading}
                        className={clsx(
                            'flex h-16 w-24 items-center justify-center rounded-lg cursor-pointer',
                            'border border-dashed border-[var(--color-border)] bg-[var(--color-surface-hover)]/30',
                            'text-[11px] font-medium text-[var(--color-text-secondary)]',
                            'hover:border-[var(--color-border-hover)] hover:bg-[var(--color-surface-hover)]/60 transition-colors',
                            'disabled:cursor-not-allowed disabled:opacity-50',
                        )}
                    >
                        {isUploading
                            ? t('theme_studio.image_uploading', 'Uploading…')
                            : `+ ${t('theme_studio.image_add', 'Add')}`}
                    </button>
                )}
            </div>
            <input
                ref={fileRef}
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) void upload(file);
                }}
                className="hidden"
            />
            {error && (
                <p className="text-[11px] text-[var(--color-danger)]">
                    {t('theme_studio.image_error', 'Upload failed:')} {error}
                </p>
            )}
            {!error && description && (
                <p className="text-[11px] text-[var(--color-text-muted)]">{description}</p>
            )}
        </div>
    );
}
