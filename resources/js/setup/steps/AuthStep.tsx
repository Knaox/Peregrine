import { useTranslation } from 'react-i18next';
import type { AuthConfig, StepProps } from '../types';
import { FormField } from '../components/FormField';

export function AuthStep({ data, onChange, onNext, onPrevious }: StepProps) {
    const { t } = useTranslation();

    const updateMode = (mode: 'local' | 'oauth') => {
        onChange({
            auth: { ...data.auth, mode },
        });
    };

    const updateField = (field: keyof AuthConfig, value: string) => {
        onChange({
            auth: { ...data.auth, [field]: value },
        });
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                    {t('setup.auth.title')}
                </h2>
                <p className="text-[var(--color-text-secondary)] text-sm mt-1">
                    {t('setup.auth.description')}
                </p>
            </div>

            <div className="space-y-3">
                <label
                    className={`flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all ${
                        data.auth.mode === 'local'
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
                            : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-border-hover)]'
                    }`}
                >
                    <input
                        type="radio"
                        name="auth_mode"
                        value="local"
                        checked={data.auth.mode === 'local'}
                        onChange={() => updateMode('local')}
                        className="mt-1 text-[var(--color-primary)] focus:ring-[var(--color-primary)] bg-[var(--color-surface-hover)] border-[var(--color-border)]"
                    />
                    <div>
                        <span className="block text-sm font-medium text-[var(--color-text-primary)]">
                            {t('setup.auth.mode_local')}
                        </span>
                        <span className="block text-xs text-[var(--color-text-secondary)] mt-1">
                            {t('setup.auth.mode_local_description')}
                        </span>
                    </div>
                </label>

                <label
                    className={`flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all ${
                        data.auth.mode === 'oauth'
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
                            : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-border-hover)]'
                    }`}
                >
                    <input
                        type="radio"
                        name="auth_mode"
                        value="oauth"
                        checked={data.auth.mode === 'oauth'}
                        onChange={() => updateMode('oauth')}
                        className="mt-1 text-[var(--color-primary)] focus:ring-[var(--color-primary)] bg-[var(--color-surface-hover)] border-[var(--color-border)]"
                    />
                    <div>
                        <span className="block text-sm font-medium text-[var(--color-text-primary)]">
                            {t('setup.auth.mode_oauth')}
                        </span>
                        <span className="block text-xs text-[var(--color-text-secondary)] mt-1">
                            {t('setup.auth.mode_oauth_description')}
                        </span>
                    </div>
                </label>
            </div>

            {data.auth.mode === 'oauth' && (
                <div className="space-y-4 pt-2">
                    <FormField label={t('setup.auth.oauth_client_id')} required>
                        <input
                            type="text"
                            value={data.auth.oauth_client_id}
                            onChange={(e) => updateField('oauth_client_id', e.target.value)}
                            className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_client_secret')} required>
                        <input
                            type="password"
                            value={data.auth.oauth_client_secret}
                            onChange={(e) => updateField('oauth_client_secret', e.target.value)}
                            className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_authorize_url')} required>
                        <input
                            type="url"
                            value={data.auth.oauth_authorize_url}
                            onChange={(e) => updateField('oauth_authorize_url', e.target.value)}
                            placeholder={t('setup.auth.oauth_authorize_url_placeholder')}
                            className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_token_url')} required>
                        <input
                            type="url"
                            value={data.auth.oauth_token_url}
                            onChange={(e) => updateField('oauth_token_url', e.target.value)}
                            placeholder={t('setup.auth.oauth_token_url_placeholder')}
                            className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_user_url')} required>
                        <input
                            type="url"
                            value={data.auth.oauth_user_url}
                            onChange={(e) => updateField('oauth_user_url', e.target.value)}
                            placeholder={t('setup.auth.oauth_user_url_placeholder')}
                            className="w-full px-3 py-2 bg-[var(--color-surface-hover)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent"
                        />
                    </FormField>
                </div>
            )}

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
                    onClick={onNext}
                    className="px-6 py-2 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white rounded-[var(--radius)] text-sm font-medium transition-all duration-[var(--transition-fast)]"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
