(function () {
    "use strict";

    var S = window.__PEREGRINE_SHARED__;
    var P = window.__PEREGRINE_PLUGINS__;
    var h = S.React.createElement;
    var useState = S.React.useState;
    var useMemo = S.React.useMemo;
    var useQuery = S.ReactQuery.useQuery;
    var useMutation = S.ReactQuery.useMutation;
    var useQueryClient = S.ReactQuery.useQueryClient;

    var PLUGIN_ID = "minecraft-modpack-installer";
    var BASE = "/api/plugins/" + PLUGIN_ID;
    var PAGE_SIZES = [6, 12, 24];
    var DEFAULT_PAGE_SIZE = 12;

    function csrf() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return (el && el.getAttribute("content")) || "";
    }

    function api(url, opts) {
        opts = opts || {};
        return fetch(url, Object.assign({}, opts, {
            credentials: "same-origin",
            headers: Object.assign({
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrf(),
            }, opts.headers || {}),
        })).then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return {}; }).then(function (b) {
                    var err = Object.assign({ status: r.status }, b);
                    throw err;
                });
            }
            return r.json();
        });
    }

    function svg(d, size, color) {
        size = size || 20;
        color = color || "currentColor";
        return h("svg", {
            width: size, height: size, viewBox: "0 0 24 24",
            fill: "none", stroke: color, strokeWidth: 2,
            strokeLinecap: "round", strokeLinejoin: "round",
        }, h("path", { d: d }));
    }

    function providerLabelKey(id) { return "modpacks.providers." + id + ".label"; }

    var C = {
        page: { display: "flex", flexDirection: "column", gap: "1.25rem" },
        header: { display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "0.75rem" },
        headerLeft: { display: "flex", alignItems: "center", gap: "0.75rem" },
        iconBox: { width: 40, height: 40, borderRadius: "var(--radius-lg)", background: "rgba(var(--color-primary-rgb),0.1)", color: "var(--color-primary)", display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 },
        title: { fontSize: "1.125rem", fontWeight: 700, color: "var(--color-text-primary)", margin: 0, lineHeight: 1.3 },
        subtitle: { fontSize: "0.75rem", color: "var(--color-text-muted)", margin: 0 },
        sectionLabel: { fontSize: "0.6875rem", fontWeight: 600, textTransform: "uppercase", letterSpacing: "0.08em", color: "var(--color-text-muted)", margin: 0 },
        card: { borderRadius: "var(--radius-lg)", border: "1px solid var(--color-border)", background: "var(--color-surface)", padding: "1rem", transition: "border-color 200ms, transform 200ms" },
        glassCard: { borderRadius: "var(--radius-lg)", border: "1px solid var(--color-border)", background: "var(--color-glass)", backdropFilter: "var(--glass-blur)", padding: "1.25rem" },
        grid: { display: "grid", gap: "0.875rem", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))" },
        cardThumb: { width: "100%", aspectRatio: "16/9", borderRadius: "var(--radius)", background: "var(--color-surface-elevated, var(--color-surface))", objectFit: "cover", display: "block" },
        cardName: { fontSize: "0.9375rem", fontWeight: 600, color: "var(--color-text-primary)", margin: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" },
        cardDesc: { fontSize: "0.8125rem", color: "var(--color-text-secondary)", margin: 0, display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden" },
        btnPrimary: { padding: "0.5rem 0.875rem", fontSize: "0.8125rem", fontWeight: 600, borderRadius: "var(--radius)", cursor: "pointer", background: "var(--color-primary)", color: "#fff", border: "none", display: "inline-flex", alignItems: "center", gap: "0.375rem", transition: "opacity 150ms, transform 150ms" },
        btnGhost: { padding: "0.5rem 0.875rem", fontSize: "0.8125rem", fontWeight: 500, borderRadius: "var(--radius)", cursor: "pointer", background: "transparent", color: "var(--color-text-secondary)", border: "1px solid var(--color-border)", display: "inline-flex", alignItems: "center", gap: "0.375rem" },
        btnDanger: { padding: "0.5rem 0.875rem", fontSize: "0.8125rem", fontWeight: 500, borderRadius: "var(--radius)", cursor: "pointer", background: "rgba(var(--color-danger-rgb),0.1)", color: "var(--color-danger)", border: "1px solid rgba(var(--color-danger-rgb),0.15)", display: "inline-flex", alignItems: "center", gap: "0.375rem" },
        input: { width: "100%", padding: "0.5rem 0.75rem", fontSize: "0.8125rem", borderRadius: "var(--radius)", border: "1px solid var(--color-border)", background: "var(--color-background)", color: "var(--color-text-primary)", outline: "none", boxSizing: "border-box" },
        select: { padding: "0.4375rem 1.875rem 0.4375rem 0.75rem", fontSize: "0.8125rem", borderRadius: "var(--radius)", border: "1px solid var(--color-border)", background: "var(--color-background)", color: "var(--color-text-primary)", outline: "none", cursor: "pointer", appearance: "none", backgroundImage: "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b849e' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>\")", backgroundRepeat: "no-repeat", backgroundPosition: "right 0.625rem center" },
        badge: function (bg, fg) { return { display: "inline-flex", alignItems: "center", gap: "0.25rem", borderRadius: "var(--radius-full)", padding: "0.125rem 0.625rem", fontSize: "0.6875rem", fontWeight: 500, background: bg, color: fg }; },
        bannerInfo: { display: "flex", alignItems: "center", gap: "0.75rem", padding: "0.875rem 1rem", borderRadius: "var(--radius-lg)", border: "1px solid rgba(var(--color-info-rgb,59 130 246),0.25)", background: "rgba(var(--color-info-rgb,59 130 246),0.08)", color: "var(--color-text-primary)" },
        bannerWarn: { display: "flex", alignItems: "center", gap: "0.75rem", padding: "0.875rem 1rem", borderRadius: "var(--radius-lg)", border: "1px solid rgba(var(--color-warning-rgb,245 158 11),0.25)", background: "rgba(var(--color-warning-rgb,245 158 11),0.08)", color: "var(--color-text-primary)" },
        bannerError: { display: "flex", alignItems: "center", gap: "0.75rem", padding: "0.875rem 1rem", borderRadius: "var(--radius-lg)", border: "1px solid rgba(var(--color-danger-rgb),0.25)", background: "rgba(var(--color-danger-rgb),0.08)", color: "var(--color-text-primary)" },
        modalScrim: { position: "fixed", inset: 0, zIndex: 60, background: "rgba(0,0,0,0.7)", backdropFilter: "blur(4px)", display: "flex", alignItems: "center", justifyContent: "center", padding: "1rem" },
        modalCard: { width: "100%", maxWidth: 480, borderRadius: "var(--radius-lg)", border: "1px solid var(--color-border)", background: "var(--color-surface)", padding: "1.25rem", display: "flex", flexDirection: "column", gap: "1rem", boxShadow: "var(--shadow-lg)" },
        skeleton: { borderRadius: "var(--radius-lg)", minHeight: 220 },
        pagination: { display: "flex", alignItems: "center", justifyContent: "space-between", gap: "0.5rem", padding: "0.5rem 0" },
    };

    // -----------------------------------------------------------------------
    // Install / uninstall modals
    // -----------------------------------------------------------------------

    function InstallModalInner(p) {
        var t = p.t;
        var versionState = useState("");
        var versionId = versionState[0];
        var setVersionId = versionState[1];
        var purgeState = useState(false);
        var purge = purgeState[0];
        var setPurge = purgeState[1];

        var versions = p.versions || [];
        var versionsForRender = p.minecraftVersionFilter
            ? versions.filter(function (v) {
                return v.minecraft_versions.length === 0
                    || v.minecraft_versions.indexOf(p.minecraftVersionFilter) >= 0;
            })
            : versions;

        function labelOption(v) {
            var mc = v.minecraft_versions.length > 0 ? " — MC " + v.minecraft_versions.join(", ") : "";
            var ld = v.loaders.length > 0 ? " [" + v.loaders.join(", ") + "]" : "";
            return v.label + mc + ld;
        }

        return h("div", { style: C.modalScrim, onClick: p.onCancel, className: "mp-modal-scrim" },
            h("div", {
                style: C.modalCard,
                onClick: function (e) { e.stopPropagation(); },
                className: "mp-modal-card",
            }, [
                h("h3", { key: "title", style: { margin: 0, fontSize: "1.0625rem", fontWeight: 700, color: "var(--color-text-primary)" } },
                    t("modpacks.install_modal.title", { name: p.modpackName, defaultValue: "Install " + p.modpackName })),

                h("div", { key: "warning", style: C.bannerWarn }, [
                    h("span", { key: "i", style: { color: "var(--color-warning, #f59e0b)" } },
                        svg("M12 9v2m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z")),
                    h("span", { key: "t", style: { fontSize: "0.8125rem" } }, t("modpacks.install_modal.warning_world")),
                ]),

                p.isLoadingVersions
                    ? h("p", { key: "loading", style: { fontSize: "0.8125rem", color: "var(--color-text-muted)" } }, t("modpacks.install_modal.loading_versions"))
                    : h("div", { key: "vrow", style: { display: "flex", flexDirection: "column", gap: "0.375rem" } }, [
                        h("label", { key: "l", style: { fontSize: "0.75rem", fontWeight: 500, color: "var(--color-text-secondary)" } }, t("modpacks.install_modal.version_label")),
                        h("select", {
                            key: "s",
                            value: versionId,
                            onChange: function (e) { setVersionId(e.target.value); },
                            style: Object.assign({}, C.select, { width: "100%" }),
                            disabled: versionsForRender.length === 0,
                        }, [h("option", { key: "_", value: "" }, t("modpacks.install_modal.version_placeholder"))]
                            .concat(versionsForRender.map(function (v) {
                                return h("option", { key: v.version_id, value: v.version_id }, labelOption(v));
                            }))),
                        versionsForRender.length === 0
                            ? h("p", { key: "empty", style: { fontSize: "0.75rem", color: "var(--color-text-muted)", margin: 0 } }, t("modpacks.install_modal.no_versions"))
                            : null,
                    ]),

                h("label", { key: "purge-row", style: { display: "flex", alignItems: "flex-start", gap: "0.5rem", cursor: "pointer" } }, [
                    h("input", { key: "cb", type: "checkbox", checked: purge, onChange: function () { setPurge(!purge); }, style: { marginTop: 4 } }),
                    h("div", { key: "txt", style: { display: "flex", flexDirection: "column", gap: "0.125rem" } }, [
                        h("span", { key: "l", style: { fontSize: "0.8125rem", fontWeight: 500, color: "var(--color-text-primary)" } }, t("modpacks.install_modal.purge.label")),
                        h("span", { key: "h", style: { fontSize: "0.75rem", color: "var(--color-text-muted)" } }, t("modpacks.install_modal.purge.help")),
                    ]),
                ]),

                p.error ? h("p", { key: "err", style: { fontSize: "0.75rem", color: "var(--color-danger)", margin: 0 } }, p.error) : null,

                h("div", { key: "actions", style: { display: "flex", justifyContent: "flex-end", gap: "0.5rem", marginTop: "0.25rem" } }, [
                    h("button", { key: "cancel", type: "button", onClick: p.onCancel, style: C.btnGhost, disabled: p.isSubmitting },
                        t("modpacks.install_modal.cancel")),
                    h("button", {
                        key: "confirm", type: "button",
                        onClick: function () { p.onConfirm(versionId, purge); },
                        style: Object.assign({}, C.btnPrimary, { opacity: !versionId || p.isSubmitting ? 0.5 : 1 }),
                        disabled: !versionId || p.isSubmitting,
                    }, p.isSubmitting ? t("modpacks.install_modal.submitting") : t("modpacks.install_modal.confirm")),
                ]),
            ]));
    }

    function renderInstallModal(p) {
        if (!p.open) return null;
        var t = S.useTranslation("minecraft-modpack-installer").t;
        return h(InstallModalInner, Object.assign({}, p, { t: t }));
    }

    function renderUninstallModal(p) {
        if (!p.open) return null;
        var t = S.useTranslation("minecraft-modpack-installer").t;

        return h("div", { style: C.modalScrim, onClick: p.onCancel, className: "mp-modal-scrim" },
            h("div", { style: C.modalCard, onClick: function (e) { e.stopPropagation(); }, className: "mp-modal-card" }, [
                h("h3", { key: "title", style: { margin: 0, fontSize: "1.0625rem", fontWeight: 700, color: "var(--color-text-primary)" } },
                    t("modpacks.uninstall_modal.title", { name: p.modpackName, defaultValue: "Uninstall " + p.modpackName + "?" })),

                h("div", { key: "w", style: C.bannerError }, [
                    h("span", { key: "i", style: { color: "var(--color-danger)" } },
                        svg("M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2")),
                    h("span", { key: "t", style: { fontSize: "0.8125rem" } }, t("modpacks.uninstall_modal.warning")),
                ]),

                p.error ? h("p", { key: "err", style: { fontSize: "0.75rem", color: "var(--color-danger)", margin: 0 } }, p.error) : null,

                h("div", { key: "actions", style: { display: "flex", justifyContent: "flex-end", gap: "0.5rem" } }, [
                    h("button", { key: "cancel", type: "button", onClick: p.onCancel, style: C.btnGhost, disabled: p.isSubmitting },
                        t("modpacks.uninstall_modal.cancel")),
                    h("button", {
                        key: "confirm", type: "button",
                        onClick: p.onConfirm,
                        style: Object.assign({}, C.btnDanger, { opacity: p.isSubmitting ? 0.5 : 1 }),
                        disabled: p.isSubmitting,
                    }, p.isSubmitting ? t("modpacks.uninstall_modal.submitting") : t("modpacks.uninstall_modal.confirm")),
                ]),
            ]));
    }

    // -----------------------------------------------------------------------
    // Renderers
    // -----------------------------------------------------------------------

    function renderHeader(t) {
        return h("div", { key: "hdr", style: C.header },
            h("div", { key: "l", style: C.headerLeft }, [
                h("div", { key: "ic", style: C.iconBox },
                    svg("M16.5 9.4 7.55 4.24 M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z")),
                h("div", { key: "t" }, [
                    h("h2", { key: "tt", style: C.title }, t("modpacks.tab.label")),
                    h("p", { key: "ss", style: C.subtitle }, t("modpacks.subtitle")),
                ]),
            ]));
    }

    function renderCurrent(inst, canUninstall, openUninstall, t) {
        var providerName = t(providerLabelKey(inst.provider));
        return h("div", { key: "cur", style: Object.assign({}, C.card, { display: "flex", gap: "1rem", alignItems: "center", flexWrap: "wrap" }) }, [
            h("div", { key: "thumb", style: { width: 96, height: 96, borderRadius: "var(--radius)", background: "var(--color-surface-elevated, var(--color-surface))", overflow: "hidden", flexShrink: 0 } },
                inst.icon_url
                    ? h("img", { src: inst.icon_url, alt: "", style: { width: "100%", height: "100%", objectFit: "cover" } })
                    : h("div", { style: { width: "100%", height: "100%", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--color-text-muted)" } },
                        svg("M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z", 32))),
            h("div", { key: "meta", style: { flex: 1, minWidth: 200, display: "flex", flexDirection: "column", gap: "0.25rem" } }, [
                h("div", { key: "top", style: { display: "flex", alignItems: "center", gap: "0.375rem", flexWrap: "wrap" } }, [
                    h("p", { key: "name", style: Object.assign({}, C.cardName, { margin: 0 }) }, inst.modpack_name),
                    inst.is_active
                        ? h("span", { key: "ip", style: C.badge("rgba(var(--color-info-rgb,59 130 246),0.12)", "var(--color-info, #3b82f6)") }, t("modpacks.current.installation_in_progress_badge"))
                        : null,
                ]),
                h("p", { key: "p", style: { fontSize: "0.75rem", color: "var(--color-text-muted)", margin: 0 } },
                    providerName + (inst.version_label ? " — " + inst.version_label : "")),
            ]),
            h("div", { key: "actions", style: { display: "flex", gap: "0.5rem", flexShrink: 0 } }, [
                inst.external_url
                    ? h("a", { key: "view", href: inst.external_url, target: "_blank", rel: "noopener noreferrer", style: Object.assign({}, C.btnGhost, { textDecoration: "none" }) },
                        t("modpacks.current.cta_view_external", { provider: providerName }))
                    : null,
                canUninstall && !inst.is_active
                    ? h("button", { key: "rm", type: "button", onClick: openUninstall, style: C.btnDanger }, t("modpacks.current.cta_uninstall"))
                    : null,
            ]),
        ]);
    }

    function renderFilters(p) {
        var t = p.t;
        return h("div", { key: "filters", style: { display: "flex", flexWrap: "wrap", gap: "0.5rem", alignItems: "center" } }, [
            h("select", {
                key: "provider", value: p.providerId,
                onChange: function (e) { p.setProviderId(e.target.value); },
                style: C.select, "aria-label": t("modpacks.filters.provider.label"),
            }, p.providers.map(function (prov) {
                return h("option", { key: prov.id, value: prov.id }, t(providerLabelKey(prov.id)));
            })),

            p.caps && p.caps.minecraft_version_filter
                ? h("select", {
                    key: "mc", value: p.mcVersion,
                    onChange: function (e) { p.setMcVersion(e.target.value); },
                    style: C.select, "aria-label": t("modpacks.filters.minecraft_version.label"),
                }, [h("option", { key: "_", value: "" }, t("modpacks.filters.minecraft_version.all"))]
                    .concat(p.mcVersions.map(function (v) { return h("option", { key: v, value: v }, v); })))
                : null,

            p.caps && p.caps.loader_filter
                ? h("select", {
                    key: "ld", value: p.loader,
                    onChange: function (e) { p.setLoader(e.target.value); },
                    style: C.select, "aria-label": t("modpacks.filters.loader.label"),
                }, [
                    h("option", { key: "_", value: "" }, t("modpacks.filters.loader.all")),
                    h("option", { key: "forge", value: "forge" }, t("modpacks.filters.loader.forge")),
                    h("option", { key: "fabric", value: "fabric" }, t("modpacks.filters.loader.fabric")),
                    h("option", { key: "quilt", value: "quilt" }, t("modpacks.filters.loader.quilt")),
                    h("option", { key: "neoforge", value: "neoforge" }, t("modpacks.filters.loader.neoforge")),
                ])
                : null,

            h("div", { key: "search", style: { display: "flex", flex: 1, minWidth: 220, gap: "0.375rem" } }, [
                h("input", {
                    key: "i", type: "text", value: p.searchTerm,
                    placeholder: t("modpacks.filters.search_placeholder"),
                    onChange: function (e) { p.setSearchTerm(e.target.value); },
                    onKeyDown: function (e) { if (e.key === "Enter") p.commitSearch(); },
                    style: C.input,
                }),
                h("button", { key: "b", type: "button", onClick: p.commitSearch, style: C.btnGhost },
                    svg("M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z", 16)),
            ]),

            h("select", {
                key: "size", value: String(p.pageSize),
                onChange: function (e) { p.setPageSize(Number(e.target.value)); },
                style: C.select, "aria-label": t("modpacks.filters.page_size.label"),
            }, PAGE_SIZES.map(function (n) { return h("option", { key: n, value: String(n) }, String(n)); })),
        ]);
    }

    function renderMissingApiKey(provider, t) {
        return h("div", { key: "noapi", style: C.bannerWarn }, [
            h("span", { key: "i", style: { color: "var(--color-warning, #f59e0b)" } },
                svg("M12 9v2m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z")),
            h("div", { key: "t", style: { display: "flex", flexDirection: "column", gap: "0.25rem" } }, [
                h("p", { key: "h", style: { margin: 0, fontSize: "0.875rem", fontWeight: 600 } }, t("modpacks.errors.provider_not_configured")),
                h("p", { key: "b", style: { margin: 0, fontSize: "0.75rem", color: "var(--color-text-muted)" } },
                    t("modpacks.missing_api_key.hint", { provider: provider.name })),
                provider.external_register_url
                    ? h("a", { key: "l", href: provider.external_register_url, target: "_blank", rel: "noopener noreferrer", style: { fontSize: "0.75rem", color: "var(--color-primary)" } },
                        t("modpacks.missing_api_key.go_register"))
                    : null,
            ]),
        ]);
    }

    function renderCard(hit, canInstall, installLocked, onInstall, serverMarkerSupported, t) {
        return h("div", {
            key: hit.provider + ":" + hit.modpack_id,
            style: Object.assign({}, C.card, { display: "flex", flexDirection: "column", gap: "0.5rem" }),
            className: "mp-card",
        }, [
            hit.icon_url
                ? h("img", { key: "thumb", src: hit.icon_url, alt: "", loading: "lazy", style: C.cardThumb })
                : h("div", { key: "thumb", style: Object.assign({}, C.cardThumb, { display: "flex", alignItems: "center", justifyContent: "center", color: "var(--color-text-muted)" }) },
                    svg("M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z", 36)),

            h("div", { key: "top", style: { display: "flex", alignItems: "center", gap: "0.375rem", flexWrap: "wrap" } }, [
                h("p", { key: "n", style: C.cardName }, hit.name),
                serverMarkerSupported && hit.is_server_compatible
                    ? h("span", { key: "srv", style: C.badge("rgba(var(--color-success-rgb,16 185 129),0.12)", "var(--color-success, #10b981)") },
                        t("modpacks.cards.server_compatible_badge"))
                    : null,
            ]),

            hit.description ? h("p", { key: "d", style: C.cardDesc }, hit.description) : null,

            h("div", { key: "actions", style: { display: "flex", justifyContent: "space-between", gap: "0.5rem", marginTop: "auto" } }, [
                hit.external_url
                    ? h("a", { key: "view", href: hit.external_url, target: "_blank", rel: "noopener noreferrer", style: Object.assign({}, C.btnGhost, { textDecoration: "none", fontSize: "0.75rem", padding: "0.375rem 0.625rem" }) },
                        t("modpacks.cards.cta_view"))
                    : h("span", { key: "spacer" }),
                canInstall
                    ? h("button", {
                        key: "install", type: "button",
                        onClick: function () { onInstall(hit); },
                        disabled: installLocked,
                        style: Object.assign({}, C.btnPrimary, { opacity: installLocked ? 0.5 : 1, fontSize: "0.75rem", padding: "0.375rem 0.75rem" }),
                    }, t("modpacks.cards.cta_install"))
                    : null,
            ]),
        ]);
    }

    function renderResults(p) {
        var t = p.t;
        if (p.isError) {
            return h("div", { key: "err", style: C.bannerError }, [h("span", { key: "t" }, t("modpacks.errors.search_failed"))]);
        }
        if (p.isLoading && p.hits.length === 0) {
            var skeletons = [];
            for (var i = 0; i < p.pageSize; i++) {
                skeletons.push(h("div", { key: i, style: Object.assign({}, C.skeleton, { background: "var(--color-surface)", border: "1px solid var(--color-border)" }), className: "skeleton-shimmer" }));
            }
            return h("div", { key: "sk", style: C.grid }, skeletons);
        }
        if (p.hits.length === 0) {
            return h("div", { key: "empty", style: Object.assign({}, C.glassCard, { textAlign: "center", padding: "4rem 1rem" }) }, [
                h("div", { key: "i", style: Object.assign({}, C.iconBox, { margin: "0 auto 1rem", width: 56, height: 56 }) },
                    svg("M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z", 28)),
                h("p", { key: "t", style: { fontSize: "0.875rem", color: "var(--color-text-secondary)", margin: 0 } }, t("modpacks.empty.title")),
                h("p", { key: "h", style: { fontSize: "0.75rem", color: "var(--color-text-muted)", marginTop: "0.5rem" } }, t("modpacks.empty.description")),
            ]);
        }
        return h("div", { key: "res", style: { display: "flex", flexDirection: "column", gap: "0.75rem" } }, [
            h("div", { key: "grid", style: C.grid }, p.hits.map(function (hit) {
                return renderCard(hit, p.canInstall, p.hasInstallActive, p.onInstall, p.serverMarkerSupported, t);
            })),
            p.meta && p.meta.last_page > 1
                ? h("div", { key: "pag", style: C.pagination }, [
                    h("p", { key: "i", style: { fontSize: "0.75rem", color: "var(--color-text-muted)", margin: 0 } },
                        t("modpacks.pagination.indicator", { current: p.meta.current_page, total: p.meta.last_page })),
                    h("div", { key: "b", style: { display: "flex", gap: "0.375rem" } }, [
                        h("button", { key: "prev", type: "button", disabled: p.page <= 1, onClick: function () { p.setPage(p.page - 1); }, style: Object.assign({}, C.btnGhost, { opacity: p.page <= 1 ? 0.5 : 1 }) },
                            t("modpacks.pagination.previous")),
                        h("button", { key: "next", type: "button", disabled: p.page >= (p.meta ? p.meta.last_page : 1), onClick: function () { p.setPage(p.page + 1); }, style: Object.assign({}, C.btnGhost, { opacity: p.page >= (p.meta ? p.meta.last_page : 1) ? 0.5 : 1 }) },
                            t("modpacks.pagination.next")),
                    ]),
                ])
                : null,
        ]);
    }

    // -----------------------------------------------------------------------
    // Main page
    // -----------------------------------------------------------------------

    function ModpacksPage() {
        var t = S.useTranslation("minecraft-modpack-installer").t;
        var params = S.ReactRouterDom.useParams();
        var qc = useQueryClient();
        var serverId = Number((params && params.id) || "0");

        var serverDataQ = useQuery({
            queryKey: ["server-id", serverId],
            queryFn: function () { return api("/api/servers/" + serverId).then(function (r) { return r.data; }); },
            enabled: serverId > 0,
            staleTime: 5 * 60 * 1000,
        });
        var typed = serverDataQ.data;
        var identifier = (typed && typed.identifier) || "";
        var role = typed ? typed.role : null;
        var permissions = typed ? typed.permissions : null;
        var isOwner = !typed || !role || role === "owner" || permissions === null;
        var myPerms = permissions || [];
        function can(p) { return isOwner || (Array.isArray(myPerms) && myPerms.indexOf(p) >= 0); }

        var eligibilityQ = useQuery({
            queryKey: ["mp", identifier, "eligibility"],
            queryFn: function () { return api(BASE + "/servers/" + identifier + "/modpacks/eligibility"); },
            enabled: !!identifier,
            staleTime: 60 * 1000,
        });
        var eligible = eligibilityQ.data && eligibilityQ.data.data ? !!eligibilityQ.data.data.eligible : false;

        var providersQ = useQuery({
            queryKey: ["mp", identifier, "providers"],
            queryFn: function () { return api(BASE + "/servers/" + identifier + "/modpacks/providers"); },
            enabled: !!identifier && eligible,
            staleTime: 5 * 60 * 1000,
        });
        var providers = (providersQ.data && providersQ.data.data) || [];

        var providerIdState = useState("");
        var providerId = providerIdState[0];
        var setProviderId = providerIdState[1];

        if (providerId === "" && providers.length > 0) {
            var firstConfigured = null;
            for (var k = 0; k < providers.length; k++) {
                if (providers[k].configured) { firstConfigured = providers[k]; break; }
            }
            if (!firstConfigured) firstConfigured = providers[0];
            if (firstConfigured) {
                queueMicrotask(function () { setProviderId(firstConfigured.id); });
            }
        }

        var currentProvider = useMemo(function () {
            for (var i = 0; i < providers.length; i++) {
                if (providers[i].id === providerId) return providers[i];
            }
            return null;
        }, [providers, providerId]);
        var caps = currentProvider ? currentProvider.capabilities : null;

        var mcVersionState = useState("");
        var mcVersion = mcVersionState[0];
        var setMcVersion = mcVersionState[1];
        var loaderState = useState("");
        var loader = loaderState[0];
        var setLoader = loaderState[1];
        var searchTermState = useState("");
        var searchTerm = searchTermState[0];
        var setSearchTerm = searchTermState[1];
        var committedSearchState = useState("");
        var committedSearch = committedSearchState[0];
        var setCommittedSearch = committedSearchState[1];
        var pageSizeState = useState(DEFAULT_PAGE_SIZE);
        var pageSize = pageSizeState[0];
        var setPageSize = pageSizeState[1];
        var pageState = useState(1);
        var page = pageState[0];
        var setPage = pageState[1];

        function resetForProvider(newId) {
            setProviderId(newId);
            setMcVersion("");
            setLoader("");
            setPage(1);
        }

        var mcVersionsQ = useQuery({
            queryKey: ["mp", identifier, "mc-versions", providerId],
            queryFn: function () {
                return api(BASE + "/servers/" + identifier + "/modpacks/providers/" + providerId + "/minecraft-versions");
            },
            enabled: !!identifier && !!providerId && !!(caps && caps.minecraft_version_filter),
            staleTime: 6 * 60 * 60 * 1000,
        });
        var mcVersions = (mcVersionsQ.data && mcVersionsQ.data.data) || [];

        var searchEnabled = !!identifier && !!providerId && !!(currentProvider && currentProvider.configured);
        var searchQ = useQuery({
            queryKey: ["mp", identifier, "search", providerId, committedSearch, mcVersion, loader, page, pageSize],
            queryFn: function () {
                var url = new URL(BASE + "/servers/" + identifier + "/modpacks/search", window.location.origin);
                url.searchParams.set("provider", providerId);
                if (committedSearch) url.searchParams.set("q", committedSearch);
                if (mcVersion) url.searchParams.set("mc", mcVersion);
                if (loader) url.searchParams.set("loader", loader);
                url.searchParams.set("page", String(page));
                url.searchParams.set("size", String(pageSize));
                return api(url.pathname + url.search);
            },
            enabled: searchEnabled,
            staleTime: 30 * 1000,
            placeholderData: function (prev) { return prev; },
        });
        var searchResp = searchQ.data;
        var hits = (searchResp && searchResp.data) || [];
        var meta = (searchResp && searchResp.meta) || null;

        var installationQ = useQuery({
            queryKey: ["mp", identifier, "installation"],
            queryFn: function () { return api(BASE + "/servers/" + identifier + "/modpacks/installation"); },
            enabled: !!identifier,
            staleTime: 5 * 1000,
            refetchInterval: function (query) {
                var data = query.state.data && query.state.data.data;
                return data && data.is_active ? 4000 : false;
            },
        });
        var installation = (installationQ.data && installationQ.data.data) || null;

        var installTargetState = useState(null);
        var installTarget = installTargetState[0];
        var setInstallTarget = installTargetState[1];
        var installErrorState = useState(null);
        var installError = installErrorState[0];
        var setInstallError = installErrorState[1];
        var uninstallOpenState = useState(false);
        var uninstallOpen = uninstallOpenState[0];
        var setUninstallOpen = uninstallOpenState[1];
        var uninstallErrorState = useState(null);
        var uninstallError = uninstallErrorState[0];
        var setUninstallError = uninstallErrorState[1];

        var installVersionsQ = useQuery({
            queryKey: ["mp", identifier, "versions", installTarget && installTarget.provider, installTarget && installTarget.modpack_id, mcVersion],
            queryFn: function () {
                if (!installTarget) return Promise.resolve({ data: [] });
                var u = BASE + "/servers/" + identifier + "/modpacks/" + installTarget.provider + "/" + encodeURIComponent(installTarget.modpack_id) + "/versions";
                if (mcVersion) u += "?mc=" + encodeURIComponent(mcVersion);
                return api(u);
            },
            enabled: !!installTarget,
            staleTime: 30 * 60 * 1000,
        });
        var versionList = (installVersionsQ.data && installVersionsQ.data.data) || [];

        var installMut = useMutation({
            mutationFn: function (d) {
                return api(BASE + "/servers/" + identifier + "/modpacks/installation", {
                    method: "POST", body: JSON.stringify(d),
                });
            },
            onSuccess: function () {
                setInstallTarget(null);
                setInstallError(null);
                qc.invalidateQueries({ queryKey: ["mp", identifier, "installation"] });
            },
            onError: function (e) {
                var errKey = e && e.error ? String(e.error) : "";
                setInstallError(errKey ? t(errKey) : t("modpacks.errors.unknown"));
            },
        });

        var uninstallMut = useMutation({
            mutationFn: function () {
                return api(BASE + "/servers/" + identifier + "/modpacks/installation", { method: "DELETE" });
            },
            onSuccess: function () {
                setUninstallOpen(false);
                setUninstallError(null);
                qc.invalidateQueries({ queryKey: ["mp", identifier, "installation"] });
            },
            onError: function (e) {
                var errKey = e && e.error ? String(e.error) : "";
                setUninstallError(errKey ? t(errKey) : t("modpacks.errors.unknown"));
            },
        });

        if (!eligibilityQ.isLoading && !eligible) {
            return h("div", { style: C.page }, [
                renderHeader(t),
                h("div", { key: "na", style: Object.assign({}, C.glassCard, { textAlign: "center", padding: "4rem 1rem" }) }, [
                    h("div", { key: "i", style: Object.assign({}, C.iconBox, { margin: "0 auto 1rem", width: 56, height: 56 }) },
                        svg("M16.5 9.4 7.55 4.24 M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z", 28)),
                    h("p", { key: "l", style: { fontSize: "0.875rem", color: "var(--color-text-secondary)", margin: 0 } }, t("modpacks.errors.server_not_eligible")),
                    h("p", { key: "h", style: { fontSize: "0.75rem", color: "var(--color-text-muted)", marginTop: "0.5rem" } }, t("modpacks.eligibility.help")),
                ]),
            ]);
        }

        return h("div", { style: C.page }, [
            renderHeader(t),
            installation
                ? renderCurrent(installation, can("modpack.uninstall"), function () { setUninstallOpen(true); }, t)
                : null,
            renderFilters({
                providers: providers, providerId: providerId, setProviderId: resetForProvider,
                mcVersions: mcVersions, mcVersion: mcVersion,
                setMcVersion: function (v) { setMcVersion(v); setPage(1); },
                loader: loader,
                setLoader: function (v) { setLoader(v); setPage(1); },
                searchTerm: searchTerm, setSearchTerm: setSearchTerm,
                commitSearch: function () { setCommittedSearch(searchTerm); setPage(1); },
                pageSize: pageSize,
                setPageSize: function (v) { setPageSize(v); setPage(1); },
                caps: caps,
                t: t,
            }),
            currentProvider && !currentProvider.configured ? renderMissingApiKey(currentProvider, t) : null,
            searchEnabled
                ? renderResults({
                    hits: hits, meta: meta,
                    isLoading: searchQ.isLoading || searchQ.isFetching,
                    isError: searchQ.isError,
                    page: page, pageSize: pageSize, setPage: setPage,
                    canInstall: can("modpack.install"),
                    hasInstallActive: !!(installation && installation.is_active),
                    onInstall: function (hit) { setInstallTarget(hit); setInstallError(null); },
                    serverMarkerSupported: !!(caps && caps.server_marker),
                    t: t,
                })
                : null,

            installTarget ? renderInstallModal({
                open: true,
                modpackName: installTarget.name,
                versions: versionList,
                isLoadingVersions: installVersionsQ.isLoading || installVersionsQ.isFetching,
                isSubmitting: installMut.isPending,
                error: installError,
                minecraftVersionFilter: mcVersion || null,
                onCancel: function () { setInstallTarget(null); setInstallError(null); },
                onConfirm: function (versionId, purge) {
                    if (!installTarget) return;
                    installMut.mutate({
                        provider: installTarget.provider,
                        modpack_id: installTarget.modpack_id,
                        version_id: versionId,
                        purge_files: purge,
                    });
                },
            }) : null,

            renderUninstallModal({
                open: uninstallOpen,
                modpackName: (installation && installation.modpack_name) || "",
                isSubmitting: uninstallMut.isPending,
                error: uninstallError,
                onCancel: function () { setUninstallOpen(false); setUninstallError(null); },
                onConfirm: function () { uninstallMut.mutate(); },
            }),
        ]);
    }

    P.registerServerPage("modpacks", ModpacksPage);
    P.register("minecraft-modpack-installer", function () { return null; });
})();
