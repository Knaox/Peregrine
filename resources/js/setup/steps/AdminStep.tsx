import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import { FormField } from '../components/FormField';

interface ValidationErrors {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
}

export function AdminStep({ data, onChange, onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();
    const [errors, setErrors] = useState<ValidationErrors>({});

    const validate = (): boolean => {
        const newErrors: ValidationErrors = {};

        if (!data.admin.name.trim()) {
            newErrors.name = t('common.required');
        }

        if (!data.admin.email.trim()) {
            newErrors.email = t('common.required');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.admin.email)) {
            newErrors.email = t('common.error');
        }

        if (!data.admin.password) {
            newErrors.password = t('common.required');
        } else if (data.admin.password.length < 8) {
            newErrors.password = t('setup.admin.password_placeholder');
        }

        if (!data.admin.password_confirmation) {
            newErrors.password_confirmation = t('common.required');
        } else if (data.admin.password !== data.admin.password_confirmation) {
            newErrors.password_confirmation = t('common.error');
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleNext = () => {
        if (validate()) {
            onNext();
        }
    };

    const updateField = (field: string, value: string) => {
        setErrors((prev) => ({ ...prev, [field]: undefined }));
        onChange({
            admin: { ...data.admin, [field]: value },
        });
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                    {t('setup.admin.title')}
                </h2>
                <p className="text-[var(--color-text-secondary)] text-sm mt-1">
                    {t('setup.admin.description')}
                </p>
            </div>

            <div className="space-y-4">
                <FormField
                    label={t('setup.admin.name')}
                    required
                    error={errors.name}
                >
                    <input
                        type="text"
                        value={data.admin.name}
                        onChange={(e) => updateField('name', e.target.value)}
                        placeholder={t('setup.admin.name_placeholder')}
                        className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                    />
                </FormField>

                <FormField
                    label={t('setup.admin.email')}
                    required
                    error={errors.email}
                >
                    <input
                        type="email"
                        value={data.admin.email}
                        onChange={(e) => updateField('email', e.target.value)}
                        placeholder={t('setup.admin.email_placeholder')}
                        className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                    />
                </FormField>

                <FormField
                    label={t('setup.admin.password')}
                    required
                    error={errors.password}
                >
                    <input
                        type="password"
                        value={data.admin.password}
                        onChange={(e) => updateField('password', e.target.value)}
                        placeholder={t('setup.admin.password_placeholder')}
                        className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                    />
                </FormField>

                <FormField
                    label={t('setup.admin.password_confirmation')}
                    required
                    error={errors.password_confirmation}
                >
                    <input
                        type="password"
                        value={data.admin.password_confirmation}
                        onChange={(e) => updateField('password_confirmation', e.target.value)}
                        placeholder={t('setup.admin.password_confirmation_placeholder')}
                        className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                    />
                </FormField>
            </div>

            <div className="flex justify-between pt-4">
                <button
                    type="button"
                    onClick={onPrevious}
                    className="px-6 py-2 bg-[var(--color-surface-hover)] hover:bg-[var(--color-border)] text-[var(--color-text-primary)] rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)] ring-1 ring-[var(--color-border)]"
                >
                    {t('common.previous')}
                </button>
                <button
                    type="button"
                    onClick={handleNext}
                    className="px-6 py-2 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)]"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
