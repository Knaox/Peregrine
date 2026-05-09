{{--
    Scoped styles for /admin/plugins. Single source of truth for the
    page's visual language. Inspired by Vercel Marketplace + Linear +
    Stripe dashboard : ambient primary accents, generous whitespace,
    big numbers on stats, halo glows on hover instead of harsh drops.
--}}
<style>
    /* ─────────────────────────────────────────────────────────────
       Design tokens — calibrés pour le thème admin Peregrine.

       Le `--primary-*` est injecté dynamiquement par Filament
       (ThemeService → AdminPanelProvider) — toute carte/halo/CTA
       qui dérive de ce token suit automatiquement la couleur du
       panel choisie par l'admin.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins {
        /* Surfaces */
        --pg-card-bg:        rgba(255,255,255,0.035);
        --pg-card-border:    rgba(255,255,255,0.09);
        --pg-card-bg-hover:  rgba(255,255,255,0.06);
        /* Texte */
        --pg-text-primary:   rgba(255,255,255,0.92);
        --pg-text-strong:    rgba(255,255,255,0.98);
        --pg-text-muted:     rgba(255,255,255,0.62);
        --pg-text-dim:       rgba(255,255,255,0.45);
        /* Status — Tailwind -400, vraies couleurs (pas des pastels) */
        --pg-success:        74,222,128;
        --pg-warning:        250,204,21;
        --pg-danger:         248,113,113;
        --pg-info:           129,140,248;
        /* Halo primary — visible, pas timide */
        --pg-halo: 0 0 0 1px rgba(var(--primary-500), 0.5),
                   0 12px 40px rgba(var(--primary-500), 0.28),
                   0 4px 12px rgba(0,0,0,0.25);
    }

    /* ─────────────────────────────────────────────────────────────
       HERO HEADER — un peu de matière + mesh gradient subtle behind
       the title to wake the page up without screaming.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-hero { position: relative; padding: 1.75rem 2rem 1.875rem; margin-bottom: 1.75rem; border-radius: 1.125rem; border: 1px solid rgba(var(--primary-500), 0.22); background: linear-gradient(135deg, rgba(var(--primary-500), 0.18), rgba(var(--primary-500), 0.04) 60%, transparent); overflow: hidden; }
    .pg-plugins .pg-hero::before { content: ''; position: absolute; top: -40%; right: -20%; width: 60%; height: 200%; background: radial-gradient(closest-side, rgba(var(--primary-500), 0.32), transparent 70%); pointer-events: none; opacity: 0.85; }
    .pg-plugins .pg-hero::after { content: ''; position: absolute; bottom: -60%; left: -20%; width: 50%; height: 200%; background: radial-gradient(closest-side, rgba(var(--primary-500), 0.18), transparent 70%); pointer-events: none; opacity: 0.7; }
    .pg-plugins .pg-hero-inner { position: relative; display: flex; flex-direction: column; gap: 1.5rem; }
    .pg-plugins .pg-hero-title { font-size: 1.5rem; font-weight: 700; color: var(--pg-text-strong); margin: 0; letter-spacing: -0.015em; line-height: 1.1; }
    .pg-plugins .pg-hero-sub { font-size: 0.9375rem; color: var(--pg-text-muted); margin: 0.375rem 0 0; line-height: 1.5; max-width: 64ch; }

    /* ─────────────────────────────────────────────────────────────
       STATS — big numbers, accent color per stat, hover lift+glow.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; }
    .pg-plugins .pg-stat { position: relative; padding: 1.125rem 1.25rem; border-radius: 0.875rem; border: 1px solid var(--pg-card-border); background: rgba(255,255,255,0.04); cursor: pointer; transition: border-color 200ms, background 200ms, transform 200ms, box-shadow 200ms; overflow: hidden; }
    .pg-plugins .pg-stat::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, var(--pg-stat-accent, rgba(var(--primary-500), 0.18)), transparent 60%); opacity: 0; transition: opacity 200ms; pointer-events: none; }
    .pg-plugins .pg-stat:hover { border-color: rgba(var(--primary-500), 0.55); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(var(--primary-500), 0.18), 0 4px 12px rgba(0,0,0,0.22); }
    .pg-plugins .pg-stat:hover::before { opacity: 1; }
    .pg-plugins .pg-stat.is-active { border-color: rgba(var(--primary-500), 0.7); background: rgba(var(--primary-500), 0.18); box-shadow: inset 0 0 0 1px rgba(var(--primary-500), 0.35); }
    .pg-plugins .pg-stat.is-active::before { opacity: 1; }
    .pg-plugins .pg-stat-row { position: relative; display: flex; justify-content: space-between; align-items: flex-start; gap: 0.625rem; }
    .pg-plugins .pg-stat-icon { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; background: var(--pg-stat-icon-bg, rgba(var(--primary-500), 0.25)); color: var(--pg-stat-icon-fg, rgb(var(--primary-300))); box-shadow: inset 0 0 0 1px rgba(var(--primary-500), 0.2); }
    .pg-plugins .pg-stat-value { position: relative; font-size: 2rem; font-weight: 700; line-height: 1; color: var(--pg-text-strong); margin-top: 0.875rem; font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
    .pg-plugins .pg-stat-label { position: relative; font-size: 0.6875rem; color: var(--pg-text-muted); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; margin-top: 0.375rem; }

    /* Stat-specific accents (set via inline custom-prop on the element) */
    .pg-plugins .pg-stat-success { --pg-stat-accent: rgba(var(--pg-success), 0.18); --pg-stat-icon-bg: rgba(var(--pg-success), 0.25); --pg-stat-icon-fg: rgb(var(--pg-success)); }
    .pg-plugins .pg-stat-warning { --pg-stat-accent: rgba(var(--pg-warning), 0.18); --pg-stat-icon-bg: rgba(var(--pg-warning), 0.25); --pg-stat-icon-fg: rgb(var(--pg-warning)); }
    .pg-plugins .pg-stat-info    { --pg-stat-accent: rgba(var(--pg-info), 0.18); --pg-stat-icon-bg: rgba(var(--pg-info), 0.25); --pg-stat-icon-fg: rgb(var(--pg-info)); }
    .pg-plugins .pg-stat-success .pg-stat-icon { box-shadow: inset 0 0 0 1px rgba(var(--pg-success), 0.3); }
    .pg-plugins .pg-stat-warning .pg-stat-icon { box-shadow: inset 0 0 0 1px rgba(var(--pg-warning), 0.3); }
    .pg-plugins .pg-stat-info .pg-stat-icon    { box-shadow: inset 0 0 0 1px rgba(var(--pg-info), 0.3); }

    /* ─────────────────────────────────────────────────────────────
       UPDATES BANNER — single tint, distinct from cards.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-banner { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 0.875rem; background: linear-gradient(135deg, rgba(var(--pg-warning),0.18), rgba(var(--pg-warning),0.04)); border: 1px solid rgba(var(--pg-warning),0.4); margin-bottom: 1.5rem; }
    .pg-plugins .pg-banner-icon { flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: rgba(var(--pg-warning),0.28); color: rgb(var(--pg-warning)); display: flex; align-items: center; justify-content: center; box-shadow: inset 0 0 0 1px rgba(var(--pg-warning), 0.35); }
    .pg-plugins .pg-banner-text { flex: 1; min-width: 0; }
    .pg-plugins .pg-banner-title { font-size: 0.9375rem; font-weight: 600; color: rgb(var(--pg-warning)); margin: 0; }
    .pg-plugins .pg-banner-sub { font-size: 0.8125rem; color: var(--pg-text-muted); margin: 0.25rem 0 0; }

    /* ─────────────────────────────────────────────────────────────
       TABS — underline avec petit glow primary sur l'actif.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--pg-card-border); margin-bottom: 1.5rem; }
    .pg-plugins .pg-tab { background: none; border: 0; padding: 0.875rem 1.125rem; font-size: 0.9375rem; font-weight: 500; color: var(--pg-text-muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color 200ms, border-color 200ms; display: inline-flex; align-items: center; gap: 0.5rem; }
    .pg-plugins .pg-tab:hover { color: var(--pg-text-primary); }
    .pg-plugins .pg-tab.is-active { color: rgb(var(--primary-300)); border-bottom-color: rgb(var(--primary-500)); }
    .pg-plugins .pg-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.5rem; height: 1.5rem; padding: 0 0.5rem; border-radius: 9999px; background: rgba(255,255,255,0.06); font-size: 0.75rem; font-weight: 600; color: var(--pg-text-muted); }
    .pg-plugins .pg-tab.is-active .pg-tab-count { background: rgba(var(--primary-500), 0.3); color: rgb(var(--primary-200)); }

    /* ─────────────────────────────────────────────────────────────
       SEARCH + CHIPS — chips font partie du langage Vercel Marketplace.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1.25rem; }
    .pg-plugins .pg-search { flex: 1 1 280px; position: relative; min-width: 240px; }
    .pg-plugins .pg-search input { width: 100%; padding: 0.6875rem 0.875rem 0.6875rem 2.5rem; border-radius: 0.625rem; background: rgba(255,255,255,0.04); border: 1px solid var(--pg-card-border); color: var(--pg-text-primary); font-size: 0.9375rem; outline: none; transition: border-color 200ms, background 200ms, box-shadow 200ms; }
    .pg-plugins .pg-search input::placeholder { color: var(--pg-text-dim); }
    .pg-plugins .pg-search input:focus { border-color: rgba(var(--primary-500), 0.55); background: rgba(255,255,255,0.06); box-shadow: 0 0 0 4px rgba(var(--primary-500), 0.1); }
    .pg-plugins .pg-search-icon { position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--pg-text-dim); pointer-events: none; }
    .pg-plugins .pg-search-clear { position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.06); border: 0; border-radius: 9999px; width: 1.375rem; height: 1.375rem; padding: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6); cursor: pointer; }
    .pg-plugins .pg-search-clear:hover { background: rgba(255,255,255,0.12); color: var(--pg-text-primary); }

    .pg-plugins .pg-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .pg-plugins .pg-chip { padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 500; border-radius: 0.5rem; border: 1px solid var(--pg-card-border); background: rgba(255,255,255,0.04); color: var(--pg-text-muted); cursor: pointer; transition: all 150ms; display: inline-flex; align-items: center; gap: 0.4rem; }
    .pg-plugins .pg-chip:hover { color: var(--pg-text-primary); border-color: rgba(255,255,255,0.22); background: rgba(255,255,255,0.07); }
    .pg-plugins .pg-chip.is-active { color: rgb(var(--primary-200)); border-color: rgba(var(--primary-500), 0.7); background: rgba(var(--primary-500), 0.28); box-shadow: inset 0 0 0 1px rgba(var(--primary-500), 0.3); }
    .pg-plugins .pg-chip-count { font-size: 0.6875rem; opacity: 0.75; font-feature-settings: 'tnum'; }
    .pg-plugins .pg-chip-reset { color: rgba(var(--pg-danger), 0.85); border-color: rgba(var(--pg-danger), 0.22); background: transparent; }
    .pg-plugins .pg-chip-reset:hover { color: rgb(var(--pg-danger)); border-color: rgba(var(--pg-danger), 0.45); }

    /* ─────────────────────────────────────────────────────────────
       SECTION HEADERS — un header par bloc avec compteur + hint.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-section { margin-top: 2.5rem; }
    .pg-plugins .pg-section:first-of-type { margin-top: 0; }
    .pg-plugins .pg-section-head { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
    .pg-plugins .pg-section-title { font-size: 0.9375rem; font-weight: 600; color: var(--pg-text-primary); margin: 0; display: inline-flex; align-items: center; gap: 0.5rem; }
    .pg-plugins .pg-section-count { font-size: 0.8125rem; color: var(--pg-text-dim); font-feature-settings: 'tnum'; padding: 0.125rem 0.5rem; border-radius: 9999px; background: rgba(255,255,255,0.05); }
    .pg-plugins .pg-section-hint { font-size: 0.8125rem; color: var(--pg-text-dim); margin: 0 0 0 auto; }

    /* ─────────────────────────────────────────────────────────────
       CARD GRID — generous spacing, halo glow on hover.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.125rem; }
    .pg-plugins .pg-card { position: relative; display: flex; flex-direction: column; gap: 1rem; padding: 1.375rem 1.5rem; border-radius: 1rem; border: 1px solid var(--pg-card-border); background: var(--pg-card-bg); transition: border-color 220ms, background 220ms, transform 220ms, box-shadow 220ms; }
    .pg-plugins .pg-card:hover { border-color: rgba(var(--primary-500), 0.45); background: var(--pg-card-bg-hover); transform: translateY(-2px); box-shadow: 0 0 0 1px rgba(var(--primary-500), 0.35), 0 12px 32px rgba(var(--primary-500), 0.15), 0 4px 12px rgba(0,0,0,0.25); }
    .pg-plugins .pg-card.is-featured { background: linear-gradient(135deg, rgba(var(--primary-500),0.18), rgba(var(--primary-500),0.05) 70%); border-color: rgba(var(--primary-500), 0.45); }
    .pg-plugins .pg-card.is-featured:hover { border-color: rgba(var(--primary-500), 0.65); box-shadow: 0 0 0 1px rgba(var(--primary-500), 0.55), 0 12px 40px rgba(var(--primary-500), 0.32), 0 4px 12px rgba(0,0,0,0.25); }

    .pg-plugins .pg-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
    .pg-plugins .pg-card-id { display: flex; align-items: flex-start; gap: 1rem; min-width: 0; flex: 1; }
    .pg-plugins .pg-card-title-wrap { min-width: 0; flex: 1; padding-top: 0.0625rem; }
    .pg-plugins .pg-card-title-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .pg-plugins .pg-card-title { font-size: 1.0625rem; font-weight: 600; color: var(--pg-text-strong); margin: 0; line-height: 1.25; letter-spacing: -0.005em; }
    .pg-plugins .pg-card-version { font-size: 0.75rem; font-family: ui-monospace, SFMono-Regular, monospace; padding: 0.1875rem 0.4375rem; border-radius: 0.3125rem; background: rgba(255,255,255,0.06); color: var(--pg-text-muted); }
    .pg-plugins .pg-card-author { font-size: 0.8125rem; color: var(--pg-text-dim); margin: 0.3125rem 0 0; }

    /* ─────────────────────────────────────────────────────────────
       PILLS — Active gets a subtle ambient glow + slow pulse.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-pill { flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 9999px; padding: 0.3125rem 0.6875rem; font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; line-height: 1.4; }
    .pg-plugins .pg-pill-active { background: rgba(var(--pg-success), 0.22); color: rgb(var(--pg-success)); box-shadow: inset 0 0 0 1px rgba(var(--pg-success), 0.4); }
    .pg-plugins .pg-pill-active .pg-dot { background: rgb(var(--pg-success)); box-shadow: 0 0 0 4px rgba(var(--pg-success), 0.25); animation: pg-pulse 2.4s ease-in-out infinite; }
    .pg-plugins .pg-pill-inactive { background: rgba(var(--pg-warning), 0.2); color: rgb(var(--pg-warning)); box-shadow: inset 0 0 0 1px rgba(var(--pg-warning), 0.35); }
    .pg-plugins .pg-pill-installed { background: rgba(var(--pg-success), 0.22); color: rgb(var(--pg-success)); box-shadow: inset 0 0 0 1px rgba(var(--pg-success), 0.4); }
    .pg-plugins .pg-pill-external { background: rgba(var(--pg-info), 0.24); color: rgb(165,180,252); border: 1px solid rgba(var(--pg-info), 0.5); }
    .pg-plugins .pg-dot { width: 0.4375rem; height: 0.4375rem; border-radius: 9999px; }

    @keyframes pg-pulse { 0%, 100% { box-shadow: 0 0 0 4px rgba(var(--pg-success), 0.2); } 50% { box-shadow: 0 0 0 7px rgba(var(--pg-success), 0.32); } }
    @media (prefers-reduced-motion: reduce) { .pg-plugins .pg-pill-active .pg-dot { animation: none; } }

    /* ─────────────────────────────────────────────────────────────
       DESCRIPTION + TAGS + UPDATE ALERT
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-card-desc { font-size: 0.875rem; color: var(--pg-text-muted); line-height: 1.6; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.8em; }

    .pg-plugins .pg-tags { display: flex; flex-wrap: wrap; gap: 0.3125rem; }
    .pg-plugins .pg-tag { font-size: 0.75rem; padding: 0.1875rem 0.5625rem; border-radius: 9999px; background: rgba(255,255,255,0.045); color: var(--pg-text-dim); border: 1px solid rgba(255,255,255,0.06); cursor: pointer; transition: all 150ms; }
    .pg-plugins .pg-tag:hover, .pg-plugins .pg-tag.is-active { background: rgba(var(--primary-500), 0.22); color: rgb(var(--primary-200)); border-color: rgba(var(--primary-500), 0.5); }

    .pg-plugins .pg-update-alert { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5625rem 0.8125rem; border-radius: 0.5625rem; background: rgba(var(--pg-warning),0.16); border: 1px solid rgba(var(--pg-warning),0.4); }
    .pg-plugins .pg-update-text { font-size: 0.75rem; color: rgb(var(--pg-warning)); display: inline-flex; align-items: center; gap: 0.375rem; min-width: 0; }
    .pg-plugins .pg-update-text strong { font-weight: 700; }

    /* ─────────────────────────────────────────────────────────────
       BUTTONS — slightly bigger touch targets, primary CTA punchy.
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-top: auto; padding-top: 0.25rem; }
    .pg-plugins .pg-btn { padding: 0.5625rem 0.9375rem; font-size: 0.8125rem; font-weight: 500; border-radius: 0.5625rem; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 0.4375rem; transition: background 150ms, border-color 150ms, color 150ms, box-shadow 150ms; text-decoration: none; line-height: 1; }
    .pg-plugins .pg-btn:disabled { opacity: 0.5; cursor: wait; }
    .pg-plugins .pg-btn svg { width: 0.9375rem; height: 0.9375rem; flex-shrink: 0; }
    .pg-plugins .pg-btn-primary { background: rgba(var(--primary-500), 0.32); color: rgb(var(--primary-200)); border-color: rgba(var(--primary-500), 0.55); }
    .pg-plugins .pg-btn-primary:hover { background: rgba(var(--primary-500), 0.45); border-color: rgba(var(--primary-500), 0.75); box-shadow: 0 0 0 4px rgba(var(--primary-500), 0.18), 0 4px 12px rgba(var(--primary-500), 0.2); }
    .pg-plugins .pg-btn-success { background: rgba(var(--pg-success), 0.22); color: rgb(var(--pg-success)); border-color: rgba(var(--pg-success), 0.45); }
    .pg-plugins .pg-btn-success:hover { background: rgba(var(--pg-success), 0.32); border-color: rgba(var(--pg-success), 0.65); box-shadow: 0 0 0 4px rgba(var(--pg-success), 0.15); }
    .pg-plugins .pg-btn-danger { background: rgba(var(--pg-danger), 0.2); color: rgb(var(--pg-danger)); border-color: rgba(var(--pg-danger), 0.42); }
    .pg-plugins .pg-btn-danger:hover { background: rgba(var(--pg-danger), 0.32); border-color: rgba(var(--pg-danger), 0.6); box-shadow: 0 0 0 4px rgba(var(--pg-danger), 0.15); }
    .pg-plugins .pg-btn-warning { background: rgba(var(--pg-warning),0.22); color: rgb(var(--pg-warning)); border-color: rgba(var(--pg-warning),0.5); }
    .pg-plugins .pg-btn-warning:hover { background: rgba(var(--pg-warning),0.32); border-color: rgba(var(--pg-warning),0.7); box-shadow: 0 0 0 4px rgba(var(--pg-warning), 0.15); }
    .pg-plugins .pg-btn-default { background: rgba(255,255,255,0.05); color: var(--pg-text-primary); border-color: var(--pg-card-border); }
    .pg-plugins .pg-btn-default:hover { background: rgba(255,255,255,0.09); border-color: rgba(255,255,255,0.22); }
    .pg-plugins .pg-btn-ghost { background: transparent; color: var(--pg-text-muted); border-color: transparent; padding: 0.5625rem 0.5625rem; }
    .pg-plugins .pg-btn-ghost:hover { background: rgba(255,255,255,0.05); color: var(--pg-text-primary); }

    /* ─────────────────────────────────────────────────────────────
       EMPTY STATES + UTILS
       ───────────────────────────────────────────────────────────── */
    .pg-plugins .pg-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 1.5rem; text-align: center; border: 1px dashed rgba(255,255,255,0.1); border-radius: 1rem; background: rgba(255,255,255,0.02); }
    .pg-plugins .pg-empty-icon { width: 3.5rem; height: 3.5rem; margin-bottom: 1rem; color: rgba(255,255,255,0.22); }
    .pg-plugins .pg-empty-title { font-size: 1rem; font-weight: 600; color: var(--pg-text-primary); margin: 0 0 0.375rem; }
    .pg-plugins .pg-empty-hint { font-size: 0.875rem; color: var(--pg-text-muted); margin: 0; max-width: 32rem; line-height: 1.55; }
    .pg-plugins .pg-empty-cta { margin-top: 1rem; }
    .pg-plugins .pg-empty code { padding: 0.1875rem 0.4375rem; background: rgba(255,255,255,0.07); border-radius: 0.3125rem; font-size: 0.8125rem; }

    .pg-plugins [x-cloak] { display: none !important; }
    .pg-spin { animation: pg-spin 1s linear infinite; }
    @keyframes pg-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    /* Stagger fade-in for cards on initial load (reduced-motion safe) */
    @media (prefers-reduced-motion: no-preference) {
        .pg-plugins .pg-grid > .pg-card { animation: pg-fade-up 380ms cubic-bezier(0.2, 0.6, 0.2, 1) backwards; }
        .pg-plugins .pg-grid > .pg-card:nth-child(1) { animation-delay: 30ms; }
        .pg-plugins .pg-grid > .pg-card:nth-child(2) { animation-delay: 60ms; }
        .pg-plugins .pg-grid > .pg-card:nth-child(3) { animation-delay: 90ms; }
        .pg-plugins .pg-grid > .pg-card:nth-child(4) { animation-delay: 120ms; }
        .pg-plugins .pg-grid > .pg-card:nth-child(5) { animation-delay: 150ms; }
        .pg-plugins .pg-grid > .pg-card:nth-child(6) { animation-delay: 180ms; }
        .pg-plugins .pg-grid > .pg-card:nth-child(n+7) { animation-delay: 210ms; }
    }
    @keyframes pg-fade-up { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 640px) {
        .pg-plugins .pg-hero { padding: 1.25rem 1.25rem 1.5rem; }
        .pg-plugins .pg-hero-title { font-size: 1.25rem; }
        .pg-plugins .pg-stat-value { font-size: 1.625rem; }
        .pg-plugins .pg-grid { grid-template-columns: 1fr; }
        .pg-plugins .pg-card { padding: 1.125rem; }
        .pg-plugins .pg-banner { flex-direction: column; align-items: flex-start; }
    }
</style>
