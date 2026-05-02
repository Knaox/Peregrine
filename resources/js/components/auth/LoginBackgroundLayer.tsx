import clsx from 'clsx';
import type { LoginBackgroundPattern } from '@/components/ThemeProvider';

interface LoginBackgroundLayerProps {
    pattern: LoginBackgroundPattern;
    /** Adds a subtle scrim on top so foreground text stays legible. */
    scrim?: boolean;
}

/**
 * Pure-CSS decorative background for login templates. Each pattern is a
 * fixed-position layer driven by the `theme_login_background_pattern`
 * setting. All patterns derive their colours from theme tokens
 * (`--color-primary`, `--color-secondary`, `--color-background`) so they
 * adapt to brand presets automatically.
 */
export function LoginBackgroundLayer({ pattern, scrim }: LoginBackgroundLayerProps) {
    if (pattern === 'none') return null;

    return (
        <>
            <div
                aria-hidden
                className={clsx('absolute inset-0 pointer-events-none', `bg-pattern-${pattern}`)}
            />
            {scrim && (
                <div
                    aria-hidden
                    className="absolute inset-0 pointer-events-none"
                    style={{
                        background:
                            'linear-gradient(180deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.15) 100%)',
                    }}
                />
            )}
        </>
    );
}
