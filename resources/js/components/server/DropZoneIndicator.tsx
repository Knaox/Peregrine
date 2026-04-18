export interface DropZoneIndicatorProps {
    isVisible: boolean;
}

export function DropZoneIndicator({ isVisible }: DropZoneIndicatorProps) {
    return (
        <div
            className="relative"
            style={{
                height: isVisible ? '4px' : '0px',
                opacity: isVisible ? 1 : 0,
                transform: isVisible ? 'scaleX(1)' : 'scaleX(0.3)',
                transition: 'opacity 200ms ease, transform 200ms ease, height 200ms ease',
                padding: isVisible ? '1px 0' : '0',
                overflow: 'hidden',
            }}
        >
            {/* Glowing dot */}
            <div
                className="absolute left-0 top-1/2 -translate-y-1/2 rounded-[var(--radius-full)]"
                style={{
                    width: '6px',
                    height: '6px',
                    background: 'var(--color-primary)',
                    boxShadow: '0 0 8px var(--color-primary), 0 0 16px var(--color-primary-glow)',
                }}
            />

            {/* Line */}
            <div
                className="absolute left-0 right-0 top-1/2 -translate-y-1/2"
                style={{
                    height: '2px',
                    background: `linear-gradient(to right, var(--color-primary), rgba(var(--color-primary-rgb), 0.3), transparent)`,
                }}
            />
        </div>
    );
}
