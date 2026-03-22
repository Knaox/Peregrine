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
                <h2 className="text-xl font-semibold text-white">
                    {t('setup.auth.title')}
                </h2>
                <p className="text-slate-400 text-sm mt-1">
                    {t('setup.auth.description')}
                </p>
            </div>

            <div className="space-y-3">
                <label
                    className={`flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all ${
                        data.auth.mode === 'local'
                            ? 'border-orange-500 bg-orange-500/10'
                            : 'border-slate-700 bg-slate-800 hover:border-slate-600'
                    }`}
                >
                    <input
                        type="radio"
                        name="auth_mode"
                        value="local"
                        checked={data.auth.mode === 'local'}
                        onChange={() => updateMode('local')}
                        className="mt-1 text-orange-500 focus:ring-orange-500 bg-slate-700 border-slate-600"
                    />
                    <div>
                        <span className="block text-sm font-medium text-white">
                            {t('setup.auth.mode_local')}
                        </span>
                        <span className="block text-xs text-slate-400 mt-1">
                            {t('setup.auth.mode_local_description')}
                        </span>
                    </div>
                </label>

                <label
                    className={`flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all ${
                        data.auth.mode === 'oauth'
                            ? 'border-orange-500 bg-orange-500/10'
                            : 'border-slate-700 bg-slate-800 hover:border-slate-600'
                    }`}
                >
                    <input
                        type="radio"
                        name="auth_mode"
                        value="oauth"
                        checked={data.auth.mode === 'oauth'}
                        onChange={() => updateMode('oauth')}
                        className="mt-1 text-orange-500 focus:ring-orange-500 bg-slate-700 border-slate-600"
                    />
                    <div>
                        <span className="block text-sm font-medium text-white">
                            {t('setup.auth.mode_oauth')}
                        </span>
                        <span className="block text-xs text-slate-400 mt-1">
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
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_client_secret')} required>
                        <input
                            type="password"
                            value={data.auth.oauth_client_secret}
                            onChange={(e) => updateField('oauth_client_secret', e.target.value)}
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_authorize_url')} required>
                        <input
                            type="url"
                            value={data.auth.oauth_authorize_url}
                            onChange={(e) => updateField('oauth_authorize_url', e.target.value)}
                            placeholder={t('setup.auth.oauth_authorize_url_placeholder')}
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_token_url')} required>
                        <input
                            type="url"
                            value={data.auth.oauth_token_url}
                            onChange={(e) => updateField('oauth_token_url', e.target.value)}
                            placeholder={t('setup.auth.oauth_token_url_placeholder')}
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        />
                    </FormField>

                    <FormField label={t('setup.auth.oauth_user_url')} required>
                        <input
                            type="url"
                            value={data.auth.oauth_user_url}
                            onChange={(e) => updateField('oauth_user_url', e.target.value)}
                            placeholder={t('setup.auth.oauth_user_url_placeholder')}
                            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        />
                    </FormField>
                </div>
            )}

            <div className="flex justify-between pt-4">
                <button
                    type="button"
                    onClick={onPrevious}
                    className="px-6 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors"
                >
                    {t('common.previous')}
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    className="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors"
                >
                    {t('common.next')}
                </button>
            </div>
        </div>
    );
}
