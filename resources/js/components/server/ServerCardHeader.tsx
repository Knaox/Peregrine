import type { CardConfig } from '@/hooks/useCardConfig';
import type { Server } from '@/types/Server';

interface ServerCardHeaderProps {
    server: Server;
    headerStyle: CardConfig['card_header_style'];
    /** True when the egg banner is the active background — drives text colour
     *  in the parent. When this returns null the parent stays on the surface
     *  background and uses default text colours. */
    showBanner: boolean;
}

/**
 * Renders the card header background depending on the admin-chosen style:
 *
 * - `banner`   : full-bleed egg image + gradient overlay (current default).
 * - `gradient` : brand gradient (primary → secondary), no image.
 * - `solid`    : flat surface — header is invisible, parent surface shows.
 * - `minimal`  : nothing rendered. Parent uses surface colour and text stays
 *                on default theme colours.
 *
 * Returns null for `minimal` and `solid` (parent surface handles the rest).
 */
export function ServerCardHeader({
    server,
    headerStyle,
    showBanner,
}: ServerCardHeaderProps) {
    if (headerStyle === 'minimal' || headerStyle === 'solid') {
        return null;
    }

    if (headerStyle === 'gradient') {
        return (
            <>
                <div
                    className="absolute inset-0"
                    style={{
                        background:
                            'linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%)',
                        opacity: 0.85,
                    }}
                />
                <div
                    className="absolute inset-0"
                    style={{
                        background:
                            'linear-gradient(to right, var(--banner-overlay-soft) 0%, var(--banner-overlay) 75%, var(--banner-overlay) 100%)',
                    }}
                />
            </>
        );
    }

    // banner — only renders when we actually have a banner image to show.
    if (!showBanner) return null;

    return (
        <>
            <img
                src={server.egg?.banner_image ?? undefined}
                alt=""
                className="absolute inset-0 h-full w-full object-cover transition-transform duration-700"
            />
            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to right, var(--banner-overlay-soft) 0%, var(--banner-overlay) 65%, var(--banner-overlay) 100%)',
                }}
            />
        </>
    );
}
