import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { EulaPromptModal } from '@/components/console/EulaPromptModal';
import { JavaVersionModal } from '@/components/console/JavaVersionModal';
import { useNamespace } from '@/i18n/useNamespace';

interface ServerBootFixPromptsProps {
    serverId: number;
    eulaRequired: boolean;
    javaIssue: { detected: boolean; requiredJava: number | null };
    canFixEula: boolean;
    canFixJava: boolean;
}

/**
 * Surfaces the Minecraft boot-failure quick-fixes (EULA / Java version) detected
 * from the live console stream: a banner + an auto-opening modal, gated by the
 * acting user's permissions. Shared by the console page and the server home page
 * — both subscribe to console output, so both can detect the failure.
 */
export function ServerBootFixPrompts({
    serverId,
    eulaRequired,
    javaIssue,
    canFixEula,
    canFixJava,
}: ServerBootFixPromptsProps) {
    useNamespace(['server-console'] as const);
    const { t } = useTranslation('server-console');

    const [eulaModalOpen, setEulaModalOpen] = useState(false);
    const [javaModalOpen, setJavaModalOpen] = useState(false);
    const [eulaSeen, setEulaSeen] = useState(false);
    const [javaSeen, setJavaSeen] = useState(false);
    const [fixSuccess, setFixSuccess] = useState<null | 'eula' | 'java'>(null);

    // Auto-open the prompt once per detection, and only for users who can act.
    useEffect(() => {
        if (eulaRequired && canFixEula && !eulaSeen) {
            setEulaModalOpen(true);
            setEulaSeen(true);
        }
    }, [eulaRequired, canFixEula, eulaSeen]);

    useEffect(() => {
        if (javaIssue.detected && canFixJava && !javaSeen) {
            setJavaModalOpen(true);
            setJavaSeen(true);
        }
    }, [javaIssue.detected, canFixJava, javaSeen]);

    // When the issue clears (server recovered), re-arm the auto-open for a
    // future recurrence and drop the matching success banner.
    useEffect(() => {
        if (!eulaRequired) {
            setEulaSeen(false);
            setFixSuccess((s) => (s === 'eula' ? null : s));
        }
    }, [eulaRequired]);

    useEffect(() => {
        if (!javaIssue.detected) {
            setJavaSeen(false);
            setFixSuccess((s) => (s === 'java' ? null : s));
        }
    }, [javaIssue.detected]);

    const showEulaBanner = eulaRequired && canFixEula && !eulaModalOpen && fixSuccess !== 'eula';
    const showJavaBanner = javaIssue.detected && canFixJava && !javaModalOpen && fixSuccess !== 'java';

    return (
        <>
            {fixSuccess === 'eula' ? <Alert variant="success">{t('fix.eula.success')}</Alert> : null}
            {fixSuccess === 'java' ? <Alert variant="success">{t('fix.java.success')}</Alert> : null}

            {showEulaBanner ? (
                <Alert variant="error">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-[var(--color-text-primary)]">{t('fix.eula.banner')}</span>
                        <Button size="sm" variant="primary" onClick={() => setEulaModalOpen(true)} className="flex-shrink-0">
                            {t('fix.eula.banner_action')}
                        </Button>
                    </div>
                </Alert>
            ) : null}

            {showJavaBanner ? (
                <Alert variant="error">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-[var(--color-text-primary)]">{t('fix.java.banner')}</span>
                        <Button size="sm" variant="primary" onClick={() => setJavaModalOpen(true)} className="flex-shrink-0">
                            {t('fix.java.banner_action')}
                        </Button>
                    </div>
                </Alert>
            ) : null}

            <EulaPromptModal
                open={eulaModalOpen}
                serverId={serverId}
                onClose={() => setEulaModalOpen(false)}
                onAccepted={() => setFixSuccess('eula')}
            />
            <JavaVersionModal
                open={javaModalOpen}
                serverId={serverId}
                requiredJava={javaIssue.requiredJava}
                onClose={() => setJavaModalOpen(false)}
                onApplied={() => setFixSuccess('java')}
            />
        </>
    );
}
