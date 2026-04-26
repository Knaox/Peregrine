/**
 * Egg Config Editor — runtime IIFE bundle (v0.3 — multi-file as sub-sections).
 *
 * Visual contract :
 *   - Outer card matches the `ServerVariables` core card (GlassCard look,
 *     rounded-lg, 1.5rem padding, header with title + chevron)
 *   - Each detected file becomes a COLLAPSIBLE sub-section nested inside
 *     the outer card — replaces the previous dropdown picker. The first
 *     file opens by default; the rest stay collapsed so the home stays
 *     scannable when many files are exposed.
 *   - Inside each sub-section : 2-column grid of parameter cards. Toggle
 *     switches for booleans, text/number inputs, select dropdowns. Per-
 *     field "Save" button revealed only when the value changed.
 *
 * Behaviour :
 *   - Lists every detected key in the file
 *   - Looks up `params.<key>.{label,type,min,max,step,options,hidden}` in
 *     the plugin's i18n bundle to render the right input
 *   - Unknown keys still render — they show the raw key as label and use
 *     the inferred type from the controller (boolean / number / text)
 *   - Hidden keys (dict says `hidden: true`) are filtered out of the form
 *     but kept in the file by the parser's preserve-unknown-lines behaviour
 */
(function () {
    "use strict";
    var S = window.__PEREGRINE_SHARED__;
    var P = window.__PEREGRINE_PLUGINS__;
    var React = S.React;
    var ReactQuery = S.ReactQuery;
    var h = React.createElement;
    var BASE = "/api/plugins/egg-config-editor";
    var NS = "egg-config-editor";
    /**
     * Section/key separator used by the parser for INI files (ASCII Unit
     * Separator, 0x1F). Invisible to end users, never appears in real INI
     * names — lets us safely round-trip section names that themselves
     * contain dots, like Unreal Engine's `[/script/shootergame.shootergamemode]`.
     * Kept identical to ConfigParserService::SECTION_KEY_SEPARATOR.
     */
    var SECTION_KEY_SEPARATOR = "\x1F";

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") || "" : "";
    }

    /**
     * Hook : load this plugin's admin-configured settings.
     *
     * Re-uses the same React Query key as the host's PluginLoader so we hit
     * the /api/plugins cache instead of doing a second network round-trip.
     * Falls back to the schema defaults declared in plugin.json before any
     * admin save, and to hardcoded defaults if even the manifest is missing
     * (offline / first paint).
     */
    function usePluginSettings() {
        var query = ReactQuery.useQuery({
            queryKey: ["plugins"],
            queryFn: function () {
                return fetch("/api/plugins", { credentials: "same-origin" }).then(function (r) { return r.json(); });
            },
            staleTime: 60 * 60 * 1000
        });
        var data = query.data;
        var manifest = null;
        if (data && data.data) {
            for (var i = 0; i < data.data.length; i++) {
                if (data.data[i].id === NS) { manifest = data.data[i]; break; }
            }
        }
        var out = { show_raw_key: false, show_description: false };
        if (manifest) {
            var schema = manifest.settings_schema || [];
            for (var s = 0; s < schema.length; s++) {
                if (typeof schema[s]["default"] !== "undefined") {
                    out[schema[s].key] = schema[s]["default"];
                }
            }
            var settings = manifest.settings || {};
            for (var k in settings) {
                if (Object.prototype.hasOwnProperty.call(settings, k)) {
                    out[k] = settings[k];
                }
            }
        }
        return out;
    }

    function api(url, init) {
        init = init || {};
        var headers = Object.assign(
            { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf() },
            init.headers || {}
        );
        return fetch(url, Object.assign({}, init, { credentials: "same-origin", headers: headers })).then(function (res) {
            if (!res.ok) {
                return res.json().catch(function () { return {}; }).then(function (body) { throw body; });
            }
            return res.json();
        });
    }

    /**
     * INI keys arrive from the parser using `\x1F` (Unit Separator) between
     * section and key. Older fallback : `.` (kept for backwards compat).
     * The UI already groups params by section, so showing the full
     * `Section…Key` prefix in the label / raw-key column is noise. Strip it
     * for cleaner fallback labels when the dict has no translation.
     */
    function leafKey(configKey) {
        var sepIdx = configKey.lastIndexOf(SECTION_KEY_SEPARATOR);
        if (sepIdx >= 0) return configKey.substring(sepIdx + 1);
        var dot = configKey.lastIndexOf(".");
        return dot >= 0 ? configKey.substring(dot + 1) : configKey;
    }

    /**
     * Best-effort label humanizer for keys not yet in the i18n dict.
     *
     * Pipeline :
     *   1. Strip Unreal Engine boolean prefix `b` (bAllowX → AllowX)
     *   2. Convert _ and - separators to spaces (max_players → max players)
     *   3. Split acronym→word boundary  (RCONServer → RCON Server)
     *   4. Split lowercase→uppercase boundary (camelCase → camel Case)
     *   5. Sentence-case the result, but PRESERVE all-caps acronyms
     *      (RCON, AI, ID, PvE — kept verbatim)
     *
     * Examples :
     *   ShowMapPlayerLocation              → Show map player location
     *   bAllowFlyerCarryPvE                → Allow flyer carry PvE
     *   RCONServerGameLogBuffer            → RCON server game log buffer
     *   MaxTamedDinos_SoftTameLimit        → Max tamed dinos soft tame limit
     *   BloodforgeReinforceExtraDurability → Bloodforge reinforce extra durability
     */
    function humanizeKey(rawKey) {
        var key = leafKey(rawKey);
        if (!key) return rawKey;

        // 1) Strip Unreal Engine `b` boolean prefix when followed by an
        //    uppercase letter (bAllowFlyer → AllowFlyer). Single letter `b`
        //    or words starting with lowercase are left alone.
        if (key.length >= 2 && key[0] === "b" && key[1] >= "A" && key[1] <= "Z") {
            key = key.substring(1);
        }

        // 2) Snake / kebab → spaces.
        key = key.replace(/[_-]+/g, " ");

        // 3) Acronym → word boundary : "RCONServer" → "RCON Server".
        key = key.replace(/([A-Z]+)([A-Z][a-z])/g, "$1 $2");

        // 4) camelCase → "camel Case".
        key = key.replace(/([a-z0-9])([A-Z])/g, "$1 $2");

        // Tighten any double spaces introduced by combinations of the above.
        key = key.replace(/\s+/g, " ").trim();

        // 5) Sentence case with acronym preservation.
        var words = key.split(" ");
        var out = [];
        for (var i = 0; i < words.length; i++) {
            var w = words[i];
            if (!w) continue;
            // All-uppercase 2+ chars → kept verbatim (RCON, AI, ID, PVP-like
            // patterns where every letter is upper).
            if (w.length >= 2 && w === w.toUpperCase() && /[A-Z]/.test(w)) {
                out.push(w);
            } else if (i === 0) {
                // First word → capitalize first letter, lowercase rest.
                out.push(w.charAt(0).toUpperCase() + w.substring(1).toLowerCase());
            } else {
                // Inner words → all lowercase (sentence case).
                out.push(w.toLowerCase());
            }
        }
        var result = out.join(" ");

        // 6) Post-pass : restore mixed-case acronyms that the camelCase
        //    splitter mangled. Mixed-case acronyms (`PvE`, `PvP`) have a
        //    lowercase letter in the middle, so the camelCase regex breaks
        //    them apart and sentence-case lowercases everything. Patch
        //    known gaming acronyms back to their canonical form.
        var FIXUPS = [
            { pat: /\b[Pp]v\s*e\b/g, to: "PvE" },
            { pat: /\b[Pp]v\s*p\b/g, to: "PvP" }
        ];
        for (var f = 0; f < FIXUPS.length; f++) {
            result = result.replace(FIXUPS[f].pat, FIXUPS[f].to);
        }
        return result;
    }

    /**
     * Translate the parser separator (\x1F) to `.` for dict lookups. The
     * dict uses dotted notation in the JSON for readability, while the
     * parser uses Unit Separator internally.
     */
    function dictKey(configKey) {
        return configKey.split(SECTION_KEY_SEPARATOR).join(".");
    }

    /**
     * Read the entire `params` resource via i18next then bracket-access by
     * the (translated) config key. We avoid `i18n.getResource(ns, "params." + key)`
     * because i18next would re-split on its own dot keySeparator and try to
     * traverse a non-existent nested structure. Bracket access on the
     * fully-loaded params object respects the JSON's flat keys including
     * any dots they might contain.
     */
    function dictParamsFor(lang) {
        var i18n = S.i18n;
        if (!i18n || typeof i18n.getResource !== "function") return null;
        return i18n.getResource(lang, NS, "params");
    }

    function resolveDictEntry(configKey, inferredType) {
        var i18n = S.i18n;
        var raw = null;
        if (i18n && typeof i18n.getResource === "function") {
            var lang = i18n.language || "en";
            var key = dictKey(configKey);
            var params = dictParamsFor(lang);
            if (params && typeof params === "object") raw = params[key];
            if (!raw) {
                var enParams = dictParamsFor("en");
                if (enParams && typeof enParams === "object") raw = enParams[key];
            }
        }
        var defaultLabel = humanizeKey(configKey);
        if (raw && typeof raw === "object") {
            return Object.assign({ label: defaultLabel, type: inferredType }, raw);
        }
        return { label: defaultLabel, type: inferredType };
    }

    /**
     * Look up the file label in the dict against EACH declared path so the
     * admin can list paths in any order and still get the translated name
     * (e.g. dict has the LinuxServer variant labelled, admin listed
     * WindowsServer first → still resolves).
     */
    /**
     * Look up an INI section's translated label. Bracket access (rather
     * than i18next dot-split) so section names with dots (Unreal-style)
     * still resolve correctly. Falls back to the raw section name verbatim
     * when the dict has no entry.
     */
    function sectionDictEntry(sectionName) {
        if (!sectionName) return { label: "General" };
        var i18n = S.i18n;
        if (i18n && typeof i18n.getResource === "function") {
            var lang = i18n.language || "en";
            var sections = i18n.getResource(lang, NS, "sections")
                || i18n.getResource("en", NS, "sections");
            if (sections && typeof sections === "object") {
                var raw = sections[sectionName];
                if (raw && typeof raw === "object") {
                    return Object.assign({ label: sectionName }, raw);
                }
            }
        }
        return { label: sectionName };
    }

    function fileLabel(filePaths, defaultLabel) {
        var i18n = S.i18n;
        if (i18n && typeof i18n.getResource === "function" && filePaths && filePaths.length > 0) {
            var lang = i18n.language || "en";
            for (var i = 0; i < filePaths.length; i++) {
                var raw = i18n.getResource(lang, NS, "files." + filePaths[i]);
                if (!raw) raw = i18n.getResource("en", NS, "files." + filePaths[i]);
                if (raw && typeof raw === "object" && raw.label) return raw.label;
            }
        }
        return defaultLabel;
    }

    function toBool(value) {
        if (typeof value === "boolean") return value;
        if (typeof value !== "string") return false;
        var v = value.toLowerCase();
        return v === "true" || v === "1";
    }

    function chevron(open) {
        return h("svg", {
            width: 16,
            height: 16,
            viewBox: "0 0 24 24",
            fill: "none",
            stroke: "currentColor",
            strokeWidth: 2,
            strokeLinecap: "round",
            strokeLinejoin: "round",
            style: {
                color: "var(--color-text-muted)",
                transition: "transform 200ms ease",
                transform: open ? "rotate(180deg)" : "rotate(0deg)",
                flexShrink: 0
            }
        }, h("path", { d: "M19 9l-7 7-7-7" }));
    }

    /**
     * Single parameter card. Stacks vertically inside its grid cell :
     *   1. Label (wraps freely so long names stay readable — never truncated)
     *   2. Raw config key in mono if `settings.show_raw_key`
     *   3. Description if `settings.show_description` (and the dict has one)
     *   4. Input field (toggle / number / text / select)
     *   5. Save button — only when the field is dirty
     */
    function ParameterCard(props) {
        var p = props.param;
        var dict = props.dict;
        var t = props.t;
        var onSave = props.onSave;
        var isSaving = props.isSaving;
        var canEdit = props.canEdit;
        var settings = props.settings;

        var initial = p.value;
        var state = React.useState(initial);
        var value = state[0];
        var setValue = state[1];

        React.useEffect(function () { setValue(initial); }, [initial]);

        var hasChanged = String(value == null ? "" : value) !== String(initial == null ? "" : initial);

        var inputEl;
        if (dict.type === "boolean") {
            var bool = toBool(value);
            inputEl = h("button", {
                type: "button",
                role: "switch",
                "aria-checked": bool,
                disabled: !canEdit,
                onClick: function () { if (canEdit) setValue(bool ? "false" : "true"); },
                style: {
                    position: "relative",
                    display: "inline-flex",
                    height: "24px",
                    width: "44px",
                    flexShrink: 0,
                    borderRadius: "9999px",
                    transition: "background var(--transition-fast)",
                    background: bool ? "var(--color-primary)" : "var(--color-border)",
                    border: "none",
                    cursor: canEdit ? "pointer" : "not-allowed",
                    opacity: canEdit ? 1 : 0.5
                }
            }, h("span", {
                style: {
                    pointerEvents: "none",
                    display: "inline-block",
                    height: "20px",
                    width: "20px",
                    borderRadius: "9999px",
                    background: "white",
                    boxShadow: "0 1px 3px rgba(0,0,0,0.2)",
                    transform: bool ? "translateX(22px)" : "translateX(2px)",
                    marginTop: "2px",
                    transition: "transform var(--transition-fast)"
                }
            }));
        } else if (dict.type === "select") {
            inputEl = h("select", {
                value: String(value == null ? "" : value),
                disabled: !canEdit,
                onChange: function (e) { setValue(e.target.value); },
                style: {
                    width: "100%",
                    padding: "0.375rem 0.625rem",
                    fontSize: "0.8125rem",
                    background: "var(--color-surface)",
                    borderRadius: "var(--radius)",
                    color: "var(--color-text-primary)",
                    border: "1px solid var(--color-border)",
                    outline: "none"
                }
            }, (dict.options || []).map(function (opt) {
                return h("option", { key: opt.value, value: opt.value }, opt.label);
            }));
        } else {
            var inputType = dict.type === "number" ? "number" : "text";
            var extraProps = {};
            if (dict.type === "number") {
                if (typeof dict.min !== "undefined") extraProps.min = dict.min;
                if (typeof dict.max !== "undefined") extraProps.max = dict.max;
                if (typeof dict.step !== "undefined") extraProps.step = dict.step;
            }
            inputEl = h("input", Object.assign({
                type: inputType,
                value: value == null ? "" : value,
                readOnly: !canEdit,
                onChange: function (e) { setValue(e.target.value); },
                style: {
                    width: "100%",
                    padding: "0.375rem 0.625rem",
                    fontSize: "0.8125rem",
                    background: "var(--color-surface)",
                    borderRadius: "var(--radius)",
                    color: "var(--color-text-primary)",
                    border: "1px solid var(--color-border)",
                    outline: "none",
                    opacity: canEdit ? 1 : 0.5
                }
            }, extraProps));
        }

        var children = [
            h("div", { key: "label", style: {
                fontSize: "0.875rem",
                fontWeight: 600,
                color: "var(--color-text-primary)",
                lineHeight: 1.35,
                wordBreak: "break-word"
            } }, dict.label || humanizeKey(p.config_key))
        ];

        if (settings.show_raw_key) {
            children.push(h("div", { key: "raw", style: {
                fontFamily: "var(--font-mono)",
                fontSize: "0.6875rem",
                color: "var(--color-text-muted)",
                marginTop: "0.125rem",
                wordBreak: "break-all"
            } }, leafKey(p.config_key)));
        }

        if (settings.show_description && dict.description) {
            children.push(h("div", { key: "desc", style: {
                fontSize: "0.75rem",
                color: "var(--color-text-muted)",
                lineHeight: 1.4,
                marginTop: "0.25rem"
            } }, dict.description));
        }

        children.push(h("div", { key: "input", style: { marginTop: "0.625rem" } }, inputEl));

        if (hasChanged && canEdit) {
            children.push(h("button", {
                key: "save",
                type: "button",
                disabled: isSaving,
                onClick: function () {
                    var coerced = value;
                    if (dict.type === "boolean") coerced = toBool(value);
                    else if (dict.type === "number" && value !== "" && value !== null) coerced = Number(value);
                    onSave(p.config_key, coerced);
                },
                style: {
                    marginTop: "0.5rem",
                    padding: "0.375rem 0.75rem",
                    fontSize: "0.75rem",
                    fontWeight: 500,
                    borderRadius: "var(--radius)",
                    cursor: isSaving ? "not-allowed" : "pointer",
                    background: "var(--color-primary)",
                    color: "#fff",
                    border: "none",
                    alignSelf: "flex-start",
                    opacity: isSaving ? 0.5 : 1
                }
            }, isSaving ? t("saving") : t("save")));
        }

        return h("div", {
            style: {
                display: "flex",
                flexDirection: "column",
                padding: "0.875rem 1rem",
                borderRadius: "var(--radius)",
                border: "1px solid var(--color-border)",
                background: "var(--color-surface)"
            }
        }, children);
    }

    /**
     * Build the CSS `grid-template-columns` string for the parameter card
     * grid. Honours the admin-configured `max_columns_per_row` setting :
     *   - "auto"     → responsive : minmax(280px, 1fr) auto-fill
     *   - 1 / 2 / 3 / 4 → fixed N columns regardless of viewport width
     */
    function buildGridTemplate(settings) {
        var maxCols = settings && settings.max_columns_per_row;
        if (!maxCols || maxCols === "auto") {
            return "repeat(auto-fill, minmax(280px, 1fr))";
        }
        var n = parseInt(maxCols, 10);
        if (isNaN(n) || n < 1) return "repeat(auto-fill, minmax(280px, 1fr))";
        return "repeat(" + n + ", minmax(0, 1fr))";
    }

    /**
     * Third-level collapsible block: one per INI section within a file.
     * Header shows the (translated) section name + parameter count. Body
     * is the same 2-column grid as the file-level fallback.
     */
    function SectionBlock(props) {
        var bucket = props.bucket;
        var sectionDict = props.sectionDict;
        var t = props.t;
        var onSave = props.onSave;
        var isSaving = props.isSaving;
        var settings = props.settings;
        var gridTemplate = props.gridTemplate;

        var openState = React.useState(!!props.defaultOpen);
        var open = openState[0];
        var setOpen = openState[1];

        var headerBtn = h("button", {
            key: "h",
            type: "button",
            onClick: function () { setOpen(!open); },
            "aria-expanded": open,
            style: {
                display: "flex",
                width: "100%",
                alignItems: "center",
                justifyContent: "space-between",
                gap: "0.5rem",
                background: "rgba(255,255,255,0.04)",
                border: "1px solid var(--color-border)",
                cursor: "pointer",
                padding: "0.5rem 0.75rem",
                textAlign: "left",
                borderRadius: "var(--radius)",
                transition: "background var(--transition-fast)"
            }
        }, [
            h("div", { key: "left", style: { display: "flex", alignItems: "center", gap: "0.5rem", minWidth: 0, flex: 1 } }, [
                h("span", {
                    key: "label",
                    style: {
                        fontSize: "0.875rem",
                        fontWeight: 600,
                        color: "var(--color-text-primary)",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        whiteSpace: "nowrap"
                    }
                }, sectionDict.label),
                bucket.section ? h("span", {
                    key: "tag",
                    style: {
                        fontFamily: "var(--font-mono)",
                        fontSize: "0.6875rem",
                        color: "var(--color-text-muted)",
                        padding: "0.0625rem 0.375rem",
                        borderRadius: "var(--radius-sm, 0.25rem)",
                        background: "rgba(255,255,255,0.04)"
                    }
                }, "[" + bucket.section + "]") : null
            ]),
            h("div", { key: "right", style: { display: "flex", alignItems: "center", gap: "0.5rem" } }, [
                h("span", {
                    key: "n",
                    style: { fontSize: "0.6875rem", color: "var(--color-text-muted)" }
                }, t("param_count", { count: bucket.items.length })),
                chevron(open)
            ])
        ]);

        var body = null;
        if (open) {
            body = h("div", {
                key: "grid",
                style: {
                    display: "grid",
                    gridTemplateColumns: gridTemplate,
                    gap: "0.625rem",
                    marginTop: "0.625rem"
                }
            }, bucket.items.map(function (entry) {
                return h(ParameterCard, {
                    key: entry.param.config_key,
                    param: entry.param,
                    dict: entry.dict,
                    t: t,
                    onSave: onSave,
                    isSaving: isSaving,
                    canEdit: true,
                    settings: settings
                });
            }));
        }

        return h("div", { style: { display: "flex", flexDirection: "column" } }, [headerBtn, body]);
    }

    /**
     * One sub-section per config file. Loads its own data (via React Query)
     * only when expanded so we don't hit Pelican on every render for files
     * the player doesn't open. The first sub-section auto-expands so the
     * card never feels empty on first paint.
     */
    function FileSubSection(props) {
        var serverId = props.serverId;
        var config = props.config;
        var t = props.t;
        var defaultOpen = props.defaultOpen;
        var settings = props.settings;
        var gridTemplate = props.gridTemplate;

        var openState = React.useState(!!defaultOpen);
        var open = openState[0];
        var setOpen = openState[1];

        var qc = ReactQuery.useQueryClient();

        var detailQuery = ReactQuery.useQuery({
            queryKey: ["ece-detail", serverId, config.id],
            queryFn: function () { return api(BASE + "/servers/" + serverId + "/configs/" + config.id); },
            enabled: open,
            staleTime: 30000
        });

        var saveMutation = ReactQuery.useMutation({
            mutationFn: function (payload) {
                return api(BASE + "/servers/" + serverId + "/configs/" + config.id, {
                    method: "POST",
                    body: JSON.stringify({ values: payload })
                });
            },
            onSuccess: function () {
                qc.invalidateQueries({ queryKey: ["ece-detail", serverId, config.id] });
            }
        });

        var detail = detailQuery.data && detailQuery.data.data;

        // Bucket parameters by their INI section. Files without sections
        // (Properties, JSON) all land in a single "General" bucket which
        // renders as a flat grid. Sections come in the order they were
        // first seen in the file so the player's mental model matches the
        // INI layout.
        var bucketsByName = {};
        var bucketOrder = [];
        var totalVisible = 0;
        if (detail && detail.parameters) {
            for (var i = 0; i < detail.parameters.length; i++) {
                var p = detail.parameters[i];
                var d = resolveDictEntry(p.config_key, p.inferred_type);
                if (d.hidden === true) continue;
                var key = p.section || "";
                if (!bucketsByName[key]) {
                    bucketsByName[key] = { section: p.section || null, items: [] };
                    bucketOrder.push(key);
                }
                bucketsByName[key].items.push({ param: p, dict: d });
                totalVisible++;
            }
        }

        // Filter out section blocks the dict marked as hidden — keeps them
        // in the file, just invisible in the UI.
        var visibleBuckets = [];
        for (var bi = 0; bi < bucketOrder.length; bi++) {
            var bucket = bucketsByName[bucketOrder[bi]];
            var sectionDict = sectionDictEntry(bucket.section);
            if (sectionDict.hidden === true) continue;
            visibleBuckets.push({ bucket: bucket, sectionDict: sectionDict });
        }

        var paths = config.file_paths || [];
        var label = fileLabel(paths, config.default_label);
        // Closed sub-section : show the first declared path (or all of them
        // joined when there are multiple — helps the admin who configured
        // the multi-path quickly see what's covered).
        var pathLabel = paths.length > 1
            ? paths[0] + " (+" + (paths.length - 1) + " other)"
            : (paths[0] || "");
        var countLabel = open && detail
            ? t("param_count", { count: totalVisible })
            : pathLabel;

        var headerBtn = h("button", {
            key: "h",
            type: "button",
            onClick: function () { setOpen(!open); },
            "aria-expanded": open,
            style: {
                display: "flex",
                width: "100%",
                alignItems: "center",
                justifyContent: "space-between",
                gap: "0.5rem",
                background: "transparent",
                border: "none",
                cursor: "pointer",
                padding: "0.875rem 1rem",
                textAlign: "left",
                borderRadius: "var(--radius)",
                transition: "background var(--transition-fast)"
            },
            onMouseEnter: function (e) { e.currentTarget.style.background = "var(--color-surface-hover, rgba(255,255,255,0.03))"; },
            onMouseLeave: function (e) { e.currentTarget.style.background = "transparent"; }
        }, [
            h("div", { key: "left", style: { minWidth: 0, flex: 1 } }, [
                h("p", {
                    key: "label",
                    style: {
                        margin: 0,
                        fontSize: "0.9375rem",
                        fontWeight: 600,
                        color: "var(--color-text-primary)",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        whiteSpace: "nowrap"
                    }
                }, label),
                h("p", {
                    key: "meta",
                    style: {
                        margin: "0.125rem 0 0",
                        fontFamily: "var(--font-mono)",
                        fontSize: "0.6875rem",
                        color: "var(--color-text-muted)",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        whiteSpace: "nowrap"
                    }
                }, countLabel)
            ]),
            chevron(open)
        ]);

        var body = null;
        if (open) {
            var bodyChildren = [];
            if (!detail) {
                bodyChildren.push(h("p", {
                    key: "loading",
                    style: { fontSize: "0.875rem", color: "var(--color-text-muted)", padding: "0.5rem 1rem 1rem" }
                }, t("loading")));
            } else {
                if (detail.file_exists === false) {
                    bodyChildren.push(h("div", {
                        key: "missing",
                        style: {
                            margin: "0 1rem",
                            padding: "0.625rem 0.875rem",
                            fontSize: "0.8125rem",
                            borderRadius: "var(--radius)",
                            background: "rgba(245,158,11,0.08)",
                            color: "var(--color-warning)",
                            border: "1px solid rgba(245,158,11,0.2)"
                        }
                    }, t("file_missing", { path: detail.file_path })));
                }
                if (totalVisible === 0) {
                    bodyChildren.push(h("p", {
                        key: "empty",
                        style: { fontSize: "0.875rem", color: "var(--color-text-muted)", padding: "0.5rem 1rem 1rem" }
                    }, t("no_params")));
                } else if (visibleBuckets.length === 1 && !visibleBuckets[0].bucket.section) {
                    // Single bucket with no section name (Properties / JSON
                    // files, or INI files where no `[Section]` appeared) →
                    // render the cards directly without a section wrapper.
                    bodyChildren.push(h("div", {
                        key: "grid",
                        style: {
                            display: "grid",
                            gridTemplateColumns: gridTemplate,
                            gap: "0.625rem",
                            margin: "0.5rem 1rem 1rem"
                        }
                    }, visibleBuckets[0].bucket.items.map(function (entry) {
                        return h(ParameterCard, {
                            key: entry.param.config_key,
                            param: entry.param,
                            dict: entry.dict,
                            t: t,
                            onSave: function (key, value) {
                                var payload = {};
                                payload[key] = value;
                                saveMutation.mutate(payload);
                            },
                            isSaving: saveMutation.isPending,
                            canEdit: true,
                            settings: settings
                        });
                    })));
                } else {
                    // Multiple sections → render each as its own nested
                    // collapsible block (third level: file → section → table).
                    bodyChildren.push(h("div", {
                        key: "sections",
                        style: { display: "flex", flexDirection: "column", gap: "0.5rem", padding: "0.5rem 1rem 1rem" }
                    }, visibleBuckets.map(function (entry, idx) {
                        return h(SectionBlock, {
                            key: entry.bucket.section || "__general__" + idx,
                            bucket: entry.bucket,
                            sectionDict: entry.sectionDict,
                            t: t,
                            onSave: function (key, value) {
                                var payload = {};
                                payload[key] = value;
                                saveMutation.mutate(payload);
                            },
                            isSaving: saveMutation.isPending,
                            defaultOpen: idx === 0,
                            settings: settings,
                            gridTemplate: gridTemplate
                        });
                    })));
                }
            }
            body = h("div", { key: "body" }, bodyChildren);
        }

        return h("div", {
            style: {
                borderRadius: "var(--radius)",
                border: "1px solid var(--color-border)",
                background: "rgba(255,255,255,0.02)",
                overflow: "hidden"
            }
        }, [headerBtn, body]);
    }

    function Section(props) {
        var serverId = props.serverId;
        var translation = S.useTranslation(NS);
        var t = translation.t;

        // Plugin settings drive which optional columns the table renders.
        var settings = usePluginSettings();
        var gridTemplate = buildGridTemplate(settings);

        var openState = React.useState(true);
        var open = openState[0];
        var setOpen = openState[1];

        var configsQuery = ReactQuery.useQuery({
            queryKey: ["ece-configs", serverId],
            queryFn: function () { return api(BASE + "/servers/" + serverId + "/configs"); },
            staleTime: 60000
        });

        if (configsQuery.isLoading) return null;

        var configs = (configsQuery.data && configsQuery.data.data) || [];
        if (configs.length === 0) return null;

        var headerBtn = h("button", {
            key: "h",
            type: "button",
            onClick: function () { setOpen(!open); },
            "aria-expanded": open,
            style: {
                display: "flex",
                width: "100%",
                alignItems: "center",
                justifyContent: "space-between",
                gap: "0.5rem",
                background: "transparent",
                border: "none",
                cursor: "pointer",
                padding: 0,
                textAlign: "left"
            }
        }, [
            h("h2", {
                key: "title",
                style: { fontSize: "1.125rem", fontWeight: 600, color: "var(--color-text-primary)", margin: 0 }
            }, t("title")),
            h("div", {
                key: "right",
                style: { display: "flex", alignItems: "center", gap: "0.5rem" }
            }, [
                h("span", {
                    key: "count",
                    style: { fontSize: "0.75rem", color: "var(--color-text-muted)" }
                }, t("file_count", { count: configs.length })),
                chevron(open)
            ])
        ]);

        var content = null;
        if (open) {
            content = h("div", {
                key: "files",
                style: { display: "flex", flexDirection: "column", gap: "0.625rem", marginTop: "1rem" }
            }, configs.map(function (cfg, idx) {
                return h(FileSubSection, {
                    key: cfg.id,
                    serverId: serverId,
                    config: cfg,
                    t: t,
                    // Auto-open the very first file so the card never feels
                    // empty on first paint, but keep the rest collapsed so
                    // the home stays scannable when many files are exposed.
                    defaultOpen: idx === 0,
                    settings: settings,
                    gridTemplate: gridTemplate
                });
            }));
        }

        return h("div", {
            className: "rounded-[var(--radius-lg)] glass-card-enhanced",
            style: { padding: "1.5rem", transition: "all 300ms" }
        }, [headerBtn, content]);
    }

    P.registerServerHomeSection("egg-config-editor", Section);
})();
