import { useT } from '../../lib/i18n';
import { Badge, Spinner } from '../../ui/surfaces';
import type { CopyLogRow } from './useCopy';

interface Props {
    targetNames: string[];
    paramCount: number;
    started: boolean;
    rows: CopyLogRow[];
    expected: number;
    done: boolean;
}

/** Step 3: preview the copy, then show live progress and the final recap. */
export function CopyReview({ targetNames, paramCount, started, rows, expected, done }: Props) {
    const { t } = useT();

    if (!started) {
        return (
            <div className="ec-stack">
                <p>{t('copy.preview_summary', { params: paramCount, servers: targetNames.length })}</p>
                <div className="ec-list">
                    {targetNames.map((name) => (
                        <div className="ec-server-row" key={name}>
                            <span className="ec-grow ec-truncate">{name}</span>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    const ok = rows.filter((row) => row.status === 'success').length;
    const fail = rows.length - ok;

    return (
        <div className="ec-stack">
            {done ? (
                fail > 0 ? (
                    <Badge variant="warning">{t('copy.recap_partial', { ok, fail })}</Badge>
                ) : (
                    <Badge variant="success">{t('copy.recap_success', { ok })}</Badge>
                )
            ) : (
                <div className="ec-row">
                    <Spinner /> <span>{t('copy.in_progress')} ({rows.length}/{expected})</span>
                </div>
            )}
        </div>
    );
}
