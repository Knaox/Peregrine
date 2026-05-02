import { useId, useRef, useState } from 'react';
import clsx from 'clsx';
import { useTranslation } from 'react-i18next';
import { getCsrfHeaders } from '@/services/http';

interface ImageUploadFieldProps {
    label: string;
    value: string;
    /** Slot id sent server-side; routes the upload to a sub-folder. */
    slot: string;
    onChange: (path: string) => void;
    description?: string;
}

interface UploadResponse {
    path: string;
    url: string;
}

/**
 * Upload + path field. Posts the file to /api/admin/theme/upload-asset
 * (multipart, admin-only) and stores the returned public path. Empty
 * value renders an empty preview slot with an upload button.
 */
export function ImageUploadField({
    label,
    value,
    slot,
    onChange,
    description,
}: ImageUploadFieldProps) {
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
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = (await response.json()) as UploadResponse;
            onChange(data.path);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'upload_failed');
        } finally {
            setIsUploading(false);
            if (fileRef.current) fileRef.current.value = '';
        }
    };

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={id}
                className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]"
            >
                {label}
            </label>
            <div className="flex items-center gap-3">
                <div
                    className={clsx(
                        'h-16 w-24 shrink-0 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)]/40',
                        'flex items-center justify-center',
                    )}
                    style={
                        value
                            ? { background: `url("${value}") center/cover no-repeat` }
                            : undefined
                    }
                >
                    {!value && (
                        <span className="text-[10px] text-[var(--color-text-muted)]">
                            {t('theme_studio.image_empty', 'No image')}
                        </span>
                    )}
                </div>
                <div className="flex flex-col gap-1.5">
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        disabled={isUploading}
                        className={clsx(
                            'rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]/60',
                            'px-3 py-1.5 text-[11px] font-medium text-[var(--color-text-primary)]',
                            'transition-colors hover:border-[var(--color-border-hover)] hover:bg-[var(--color-surface-hover)]/60',
                            'disabled:cursor-not-allowed disabled:opacity-50',
                            'cursor-pointer',
                        )}
                    >
                        {isUploading
                            ? t('theme_studio.image_uploading', 'Uploading…')
                            : value
                                ? t('theme_studio.image_replace', 'Replace')
                                : t('theme_studio.image_upload', 'Upload image')}
                    </button>
                    {value && (
                        <button
                            type="button"
                            onClick={() => onChange('')}
                            className={clsx(
                                'rounded-lg px-3 py-1 text-[11px] font-medium text-[var(--color-text-muted)]',
                                'transition-colors hover:text-[var(--color-danger)]',
                                'cursor-pointer',
                            )}
                        >
                            {t('theme_studio.image_clear', 'Remove')}
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
            </div>
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
