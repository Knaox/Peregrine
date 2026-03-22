import clsx from 'clsx';
import { type StatBarProps } from '@/components/ui/StatBar.props';

function resolveColor(percent: number): NonNullable<StatBarProps['color']> {
    if (percent > 85) return 'red';
    if (percent >= 60) return 'yellow';
    return 'green';
}

const barColorClasses: Record<NonNullable<StatBarProps['color']>, string> = {
    green: 'bg-green-500',
    yellow: 'bg-yellow-500',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
};

export function StatBar({ label, value, max, formatted, color }: StatBarProps) {
    const percent = max > 0 ? (value / max) * 100 : 0;
    const clampedPercent = Math.min(100, Math.max(0, percent));
    const resolvedColor = color ?? resolveColor(clampedPercent);

    return (
        <div className='flex flex-col gap-1.5'>
            <div className='flex items-center justify-between'>
                <span className='text-sm text-slate-400'>{label}</span>
                <span className='text-sm font-medium text-white'>{formatted}</span>
            </div>
            <div className='h-2 w-full rounded-full bg-slate-700'>
                <div
                    className={clsx(
                        'h-2 rounded-full transition-all duration-300',
                        barColorClasses[resolvedColor],
                    )}
                    style={{ width: `${clampedPercent}%` }}
                />
            </div>
        </div>
    );
}
