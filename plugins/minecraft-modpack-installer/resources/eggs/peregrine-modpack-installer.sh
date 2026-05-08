#!/bin/bash
#
# Minecraft: Modpack — Installer
# Copyright (c) Peregrine — License: MIT
#
# Universal Minecraft modpack installer. Authored from scratch from the public
# marketplace docs; not derived from any third-party installer. Runs inside the
# Pelican install container with /mnt/server bound as the working directory.
#
# Required env vars (injected by the plugin through the egg variables):
#   BB_MODPACK_PROVIDER       modrinth | curseforge | atlauncher | ftb | technic | voidswrath
#   BB_MODPACK_ID             provider-side modpack identifier
#   BB_MODPACK_VERSION_ID     provider-side version identifier
#   BB_MODPACK_GAME_VERSION   minecraft version hint (optional)
#   BB_MODPACK_PURGE          1 to wipe /mnt/server before installing
#   BB_MODPACK_CURSEFORGE_KEY CurseForge API key (only used by the curseforge provider)
#   SERVER_JARFILE                   target jar filename (the script symlinks server.jar to whatever it finds)

set -uo pipefail

WORKDIR="/mnt/server"
# Stage on the persistent server volume (subject to the server's disk
# quota, typically several GB) instead of the install container's /tmp
# (typically a few hundred MB tmpfs) — large modpacks like Cobblemon
# expand to 300-500 MB which overflows the container tmpfs and aborts
# the unzip with "write error (disk full?)".
TMPDIR="/mnt/server/.peregrine-modpack-tmp-$$"
USER_AGENT="PeregrineModpackInstaller/1.1 (+https://github.com/peregrine-panel)"

# Counter of soft failures that should still abort the install. Provider
# routines bump this whenever a *required* asset (server jar, gameplay-
# critical mod) fails to download. main() inspects it after the loader
# install and aborts with FATAL if non-zero so Pelican marks the install
# failed instead of silently completing on a broken /mnt/server.
CRITICAL_FAILURES=0

## Diagnostic helpers — ALL write to stderr.
##
## Routing log to stdout (the previous behaviour) was fine for the linear
## call sites but broke the moment a helper started returning a string
## via `$(helper …)` while also logging — the captured value picked up
## the log lines and the caller would try to exec the whole blob as a
## binary path. pick_installer_java was the canonical example: it
## returned a `java` binary path on stdout but apt-installed JDK 8 the
## first time, surfacing the install message as part of the captured
## value and crashing `timeout … "$java_bin"` with `No such file or
## directory`. Sending all status lines to stderr makes the script
## composable: stdout is reserved for return values.
##
## Pelican Wings captures both streams and surfaces them in the install
## panel, so users still see every line in the same order.
log()  { printf '[modpack-installer] %s\n' "$*" >&2; }
warn() { printf '[modpack-installer] WARN: %s\n' "$*" >&2; }
fail() { printf '[modpack-installer] FATAL: %s\n' "$*" >&2; exit 1; }
crit() {
    printf '[modpack-installer] CRITICAL: %s\n' "$*" >&2
    CRITICAL_FAILURES=$((CRITICAL_FAILURES + 1))
}

cleanup() { rm -rf "$TMPDIR" 2>/dev/null || true; }
trap cleanup EXIT

# ---------------------------------------------------------------------------
# Setup
# ---------------------------------------------------------------------------

ensure_tools() {
    log "Installing required tools (curl, unzip, jq, tar, ca-certificates)."
    apt-get update -y >/dev/null 2>&1 || warn "apt-get update returned non-zero (best effort)"
    apt-get install -y --no-install-recommends \
        curl unzip jq tar ca-certificates xz-utils file >/dev/null 2>&1 \
        || fail "unable to install required tools"

    # The Forge / NeoForge / Quilt installers shell out via `java -jar`.
    # The egg's installer container is openjdk-17 by default, but if an
    # operator overrides it with a bare debian / alpine image the JRE is
    # missing and the loader install would silently no-op — leaving
    # finalize_jar to symlink server.jar to a random mod and crash on
    # startup with `no main manifest attribute`. Fall back to the distro
    # JRE so the script still does the right thing in that case.
    if ! command -v java >/dev/null 2>&1; then
        log "Java not present in installer container — installing default-jre-headless."
        apt-get install -y --no-install-recommends default-jre-headless >/dev/null 2>&1 \
            || warn "default-jre-headless install failed — Forge/NeoForge/Quilt providers will fail loudly"
    fi

    for bin in curl unzip jq tar; do
        command -v "$bin" >/dev/null 2>&1 || fail "missing required tool: $bin"
    done
}

# Probe whether a jar's META-INF/MANIFEST.MF declares a Main-Class — i.e.
# whether `java -jar <jar>` would have something to run.
#
# Worth understanding why this is non-trivial:
#  - JAR manifest physical line layout doesn't match the logical headers:
#    any line longer than 72 bytes is wrapped across multiple physical
#    lines with continuations starting in space/tab (Forge 1.12.2's
#    launcher manifest has a Class-Path that's hundreds of bytes long,
#    so the wrap absolutely happens in the wild).
#  - Some toolchains emit the manifest with CRLF, others with LF, others
#    with a stray BOM at the very start.
#
# The implementation below is deliberately permissive: strip CR, trim a
# possible UTF-8 BOM, and search for `Main-Class:` anywhere in the
# manifest. The JAR spec puts `Main-Class:` only in the main section
# (before the first blank line), and per-entry sections never repeat
# that key, so a non-anchored grep is correct in practice and sidesteps
# the wrapping nightmare entirely.
jar_has_main_class() {
    local jar="$1"
    [ -f "$jar" ] || return 1

    local mf
    mf=$(unzip -p "$jar" META-INF/MANIFEST.MF 2>/dev/null) || return 1
    [ -n "$mf" ] || return 1

    # `tr -d '\r'` flattens CRLF endings; the leading sed strips a UTF-8
    # BOM if present (rare but observed in jars built by some Maven
    # plugins). `grep -qai 'Main-Class:'` then matches case-insensitively
    # anywhere in the file (`-a` forces text mode in case unzip's output
    # tripped the binary-detect heuristic).
    printf '%s' "$mf" \
        | tr -d '\r' \
        | sed '1s/^\xef\xbb\xbf//' \
        | grep -qai 'Main-Class:'
}

# Pick the right `java` binary to run a Forge / NeoForge installer against.
#
# Forge installers up through 1.16.5 predate Java 9's module system and
# fail silently on Java 16+ (the JVM's `sun.misc.Unsafe` and other
# now-encapsulated internals are missing — the installer GUI/CLI exits
# cleanly with code 0 but writes no jars to disk). The egg's installer
# container is `java_17` to keep the modern path fast, so for legacy
# Forge we install OpenJDK 8 alongside and call its binary directly.
#
# 1.17+ Forge and every NeoForge release require Java 17 → return the
# default `java` (already on PATH inside the installer container).
#
# Echoes the chosen binary path on stdout. Always succeeds with at
# least `java` so callers don't need to handle a failure mode.
pick_installer_java() {
    local mc="$1"
    local needs_java8=0

    case "$mc" in
        1.[0-9]|1.[0-9].*) needs_java8=1 ;;       # 1.0 - 1.9
        1.1[0-6]|1.1[0-6].*) needs_java8=1 ;;      # 1.10 - 1.16
    esac

    if [ "$needs_java8" -ne 1 ]; then
        printf '%s' "java"
        return 0
    fi

    local java8="/usr/lib/jvm/java-8-openjdk-amd64/bin/java"
    if [ ! -x "$java8" ]; then
        log "  installing OpenJDK 8 for legacy Forge installer (mc=$mc)..."
        apt-get install -y --no-install-recommends openjdk-8-jre-headless >/dev/null 2>&1 \
            || warn "  OpenJDK 8 install failed — Forge $mc installer will likely no-op on Java 17"
    fi

    if [ -x "$java8" ]; then
        printf '%s' "$java8"
    else
        printf '%s' "java"
    fi
}

# Args: <url> <output-file> [optional auth header].
http_download() {
    # Per-attempt budget: 15s connect + 120s body. Three attempts → worst
    # case ~6 minutes per file. Mods are typically < 50MB so 120s body is
    # plenty even on slow links; pack archives use a longer dedicated path
    # (see http_download_large below).
    local url="$1" out="$2" header="${3:-}" attempt rc
    for attempt in 1 2 3; do
        if [ -n "$header" ]; then
            curl -fL --retry 0 --connect-timeout 15 --max-time 120 \
                -H "User-Agent: $USER_AGENT" -H "$header" \
                -o "$out" "$url"
        else
            curl -fL --retry 0 --connect-timeout 15 --max-time 120 \
                -H "User-Agent: $USER_AGENT" \
                -o "$out" "$url"
        fi
        rc=$?
        if [ "$rc" -eq 0 ]; then
            return 0
        fi
        warn "download attempt $attempt failed (curl rc=$rc) — $url"
        sleep $((attempt * 2))
    done
    return 1
}

# Used for the modpack archive itself (CurseForge zips, .mrpack, FTB
# server bundles) which can legitimately exceed 100MB and need a longer
# body window than individual mods.
http_download_large() {
    local url="$1" out="$2" header="${3:-}" attempt rc
    for attempt in 1 2 3; do
        if [ -n "$header" ]; then
            curl -fL --retry 0 --connect-timeout 30 --max-time 600 \
                -H "User-Agent: $USER_AGENT" -H "$header" \
                -o "$out" "$url"
        else
            curl -fL --retry 0 --connect-timeout 30 --max-time 600 \
                -H "User-Agent: $USER_AGENT" \
                -o "$out" "$url"
        fi
        rc=$?
        if [ "$rc" -eq 0 ]; then
            return 0
        fi
        warn "large download attempt $attempt failed (curl rc=$rc) — $url"
        sleep $((attempt * 3))
    done
    return 1
}

http_get() {
    local url="$1" header="${2:-}" attempt rc body
    for attempt in 1 2 3; do
        if [ -n "$header" ]; then
            body=$(curl -fsSL --retry 0 --connect-timeout 30 --max-time 60 \
                -H "User-Agent: $USER_AGENT" -H "$header" "$url")
        else
            body=$(curl -fsSL --retry 0 --connect-timeout 30 --max-time 60 \
                -H "User-Agent: $USER_AGENT" "$url")
        fi
        rc=$?
        if [ "$rc" -eq 0 ]; then
            printf '%s' "$body"
            return 0
        fi
        warn "http_get attempt $attempt failed (rc=$rc) — $url"
        sleep $((attempt * 2))
    done
    return 1
}

http_post_json() {
    local url="$1" body="$2" header="${3:-}" attempt rc resp
    for attempt in 1 2 3; do
        if [ -n "$header" ]; then
            resp=$(curl -fsSL --retry 0 --connect-timeout 30 --max-time 60 \
                -H "User-Agent: $USER_AGENT" -H "$header" \
                -H 'Content-Type: application/json' \
                -d "$body" "$url")
        else
            resp=$(curl -fsSL --retry 0 --connect-timeout 30 --max-time 60 \
                -H "User-Agent: $USER_AGENT" \
                -H 'Content-Type: application/json' \
                -d "$body" "$url")
        fi
        rc=$?
        if [ "$rc" -eq 0 ]; then
            printf '%s' "$resp"
            return 0
        fi
        warn "http_post_json attempt $attempt failed (rc=$rc) — $url"
        sleep $((attempt * 2))
    done
    return 1
}

prepare_workdir() {
    mkdir -p "$WORKDIR"
    cd "$WORKDIR" || fail "cannot cd $WORKDIR"

    if [ "${BB_MODPACK_PURGE:-1}" = "1" ]; then
        log "Wiping server directory."
        find . -mindepth 1 -delete 2>/dev/null || true
    fi

    mkdir -p "$TMPDIR"
}

# Many CurseForge / FTB / VoidsWrath server-pack zips wrap their contents
# in a single top-level directory named after the pack (e.g. RLCraft v2.9.2d
# nests everything inside `RLCraft Server Pack 1.12.2 - Release v2.9.2d/`).
# After unzip the launcher script and the bundled forge installer end up
# one level deeper than where finalize_jar expects them, the egg can't
# find a runnable jar, and the install fails with an opaque
# "no candidate jar found at /mnt/server root".
#
# This helper detects the "single nested dir" case and unfolds the dir's
# contents up to the workdir root. It's a best-effort no-op when the zip
# was extracted flat or when there are multiple top-level entries.
flatten_single_top_dir() {
    local target="$1"
    [ -d "$target" ] || return 0
    cd "$target" || return 0

    # Count visible top-level entries, ignoring our own staging dir and
    # hidden dotfiles (some packs ship a .minecraft/ alongside the real
    # subdir — those don't count for the unfold heuristic).
    local entries top_dir
    entries=$(find . -mindepth 1 -maxdepth 1 \
        -not -name '.peregrine-modpack-tmp-*' \
        -not -name '.*' \
        2>/dev/null | wc -l)

    if [ "$entries" -ne 1 ]; then
        return 0
    fi

    top_dir=$(find . -mindepth 1 -maxdepth 1 -type d \
        -not -name '.peregrine-modpack-tmp-*' \
        -not -name '.*' \
        2>/dev/null | head -n1)

    if [ -z "$top_dir" ]; then
        return 0  # the single entry was a file, not a dir — nothing to unfold
    fi

    log "Server pack nested under '$(basename "$top_dir")' — unfolding into $target"
    # Move everything (including hidden files) one level up. Using find +
    # `mv -t` instead of a glob because bash globs skip dotfiles unless
    # `shopt -s dotglob` is on, and we want to be agnostic to the shell
    # config. Two passes (visible then hidden) keeps the command line
    # short on packs with many top-level entries.
    (cd "$top_dir" && find . -mindepth 1 -maxdepth 1 -exec mv -t "$target" {} +) 2>/dev/null || true
    rmdir "$top_dir" 2>/dev/null || warn "  could not remove now-empty $(basename "$top_dir") — manual cleanup may be needed"
}

# Run a Forge / NeoForge installer jar that the pack bundled at the
# workdir root. Many CurseForge server packs ship the installer alongside
# their startserver.sh wrapper, so when the wrapper isn't usable (interactive
# prompts, missing on this version, …) we fall back to invoking the bundled
# installer ourselves with the same flags `install_forge` would use.
run_bundled_loader_installer() {
    local installer
    installer=$(find "$WORKDIR" -maxdepth 1 -type f \
        \( -name 'forge-*-installer.jar' -o -name 'neoforge-*-installer.jar' \) \
        -size +1c 2>/dev/null | head -n1)

    if [ -z "$installer" ]; then
        return 1
    fi

    log "Running bundled loader installer: $(basename "$installer")"
    local installer_log="$TMPDIR/bundled-installer.log"
    if ! (cd "$WORKDIR" && timeout 900 java -jar "$installer" --installServer . > "$installer_log" 2>&1); then
        warn "bundled installer returned non-zero — last 30 lines:"
        tail -n 30 "$installer_log" >&2 || true
    fi
    return 0
}

# Run any installer-style launcher script the pack ships at workdir root.
# CurseForge server packs name this wildly inconsistently — RLCraft
# pre-2.9.3 used `startserver.sh`, ATM packs use `LaunchServer.sh` and
# `ServerStart.sh` (AllTheMods ServerStarter), older packs use `start.sh`,
# etc. We try them all in a stable order, the first one we find wins.
# Returns 0 if a launcher was found and ran (regardless of exit code —
# many wrappers exit non-zero after a successful install), 1 otherwise.
run_pack_launcher_script() {
    # `local launcher` alone leaves the variable unset rather than empty,
    # and `set -u` (top of file) then crashes on the `[ -z "$launcher" ]`
    # probe below when the loop didn't find anything. Initialise to "".
    local launcher="" candidate
    for candidate in startserver.sh ServerStart.sh LaunchServer.sh start.sh run-server.sh start-server.sh; do
        if [ -f "$WORKDIR/$candidate" ]; then
            launcher="$candidate"
            break
        fi
    done

    if [ -z "$launcher" ]; then
        return 1
    fi

    log "Running pack launcher script: $launcher"
    chmod +x "$WORKDIR/$launcher" 2>/dev/null
    # `</dev/null` keeps the wrapper from blocking on stdin — several
    # CurseForge server packs ship a `read` prompt (EULA / accept Y/N)
    # that would otherwise hang forever even with a `timeout`.
    (cd "$WORKDIR" && timeout 600 bash "$launcher" nogui </dev/null 2>&1 | head -n 200) || true
    return 0
}

# Tell whether finalize_jar will have a runnable launcher to symlink. Used
# as a "did the loader install succeed?" probe between fallback paths so
# we know whether to keep cascading or bail early.
loader_jar_present() {
    if [ -f "$WORKDIR/run.sh" ]; then
        return 0  # modern Forge / NeoForge launcher
    fi
    find "$WORKDIR" -maxdepth 1 -type f \
        \( -name 'forge-*-universal.jar' \
           -o -name 'forge-*-server.jar' \
           -o -name 'forge-*.jar' \
           -o -name 'neoforge-*-universal.jar' \
           -o -name 'neoforge-*-server.jar' \
           -o -name 'neoforge-*.jar' \
           -o -name 'fabric-server-launch.jar' \
           -o -name 'fabric-server-mc*.jar' \
           -o -name 'quilt-server-launch.jar' \
           -o -name 'quilt-server-launcher.jar' \
           -o -name 'minecraft_server.*.jar' \
           -o -name 'paper-*.jar' \
           -o -name 'purpur-*.jar' \) \
        -size +1c 2>/dev/null \
        ! -name 'forge-*-installer.jar' \
        ! -name 'neoforge-*-installer.jar' \
        | grep -q . && return 0

    return 1
}

# Read MC + loader from a CurseForge `manifest.json` and dispatch to the
# right install_<loader> routine. Common path used by both
# install_curseforge_manifest (user-modpack flow) and the server-pack
# fallback (when we sideload manifest.json from elsewhere).
install_loader_from_curseforge_manifest() {
    local manifest="$1"
    [ -f "$manifest" ] || return 1

    local mc loader_id
    mc=$(jq -r '.minecraft.version // empty' "$manifest")
    loader_id=$(jq -r '.minecraft.modLoaders[]? | select(.primary == true) | .id // empty' "$manifest")

    if [ -z "$mc" ]; then
        warn "manifest at $manifest has no minecraft.version"
        return 1
    fi
    if [ -z "$loader_id" ]; then
        warn "manifest at $manifest has no primary modLoader"
        return 1
    fi

    log "Manifest declares MC=$mc loader=$loader_id"
    case "$loader_id" in
        forge-*)    install_forge    "$mc" "${loader_id#forge-}" ;;
        neoforge-*) install_neoforge "$mc" "${loader_id#neoforge-}" ;;
        fabric-*)   install_fabric   "$mc" "${loader_id#fabric-}" ;;
        quilt-*)    install_quilt    "$mc" "${loader_id#quilt-}" ;;
        *)
            warn "unknown loader id: $loader_id — running vanilla $mc instead"
            install_vanilla "$mc"
            ;;
    esac
    return 0
}

# Last-resort loader-version source for CurseForge server packs that ship
# a flat bundle (mods + configs only) without a `manifest.json` and
# without a bundled forge installer — typified by RLCraft 1.12.2 v2.9.3,
# whose published install instructions tell the user to manually fetch
# `forge-1.12.2-14.23.5.2860-installer.jar` and run it themselves.
#
# CurseForge's user-modpack file (the file the user originally picked,
# tracked here as $BB_MODPACK_VERSION_ID) IS in the canonical CurseForge
# modpack format with manifest.json + overrides, even when its server-pack
# twin isn't. We download just enough of it to read manifest.json and
# pass it through `install_loader_from_curseforge_manifest`. The mods
# don't get touched — the server pack already extracted them into mods/.
install_loader_from_user_pack_manifest() {
    local user_file_json="$1" hdr="$2"

    local download_url file_name file_id_str padded
    download_url=$(echo "$user_file_json" | jq -r '.data.downloadUrl // empty')
    file_name=$(echo "$user_file_json" | jq -r '.data.fileName // "user-modpack.zip"')

    if [ -z "$download_url" ]; then
        file_id_str=$(echo "$user_file_json" | jq -r '.data.id')
        padded=$(printf '%07d' "$file_id_str")
        download_url="https://edge.forgecdn.net/files/${padded:0:4}/${padded:4}/$file_name"
        log "  user-modpack downloadUrl null; reconstructed CDN URL"
    fi

    log "Downloading user-modpack file for manifest: $file_name"
    local user_zip="$TMPDIR/userpack-$file_name"
    if ! http_download_large "$download_url" "$user_zip" "$hdr"; then
        warn "user-modpack download failed — cannot recover loader version"
        return 1
    fi

    local extract="$TMPDIR/userpack"
    mkdir -p "$extract"
    if ! unzip -qo "$user_zip" -d "$extract" 2>/dev/null; then
        warn "user-modpack extract failed — file may be corrupted or not a zip"
        return 1
    fi

    local manifest
    manifest=$(find "$extract" -maxdepth 3 -type f -name 'manifest.json' 2>/dev/null | head -n1)
    if [ -z "$manifest" ]; then
        warn "user-modpack also lacks manifest.json — cannot determine loader version"
        return 1
    fi

    install_loader_from_curseforge_manifest "$manifest"
}

write_eula() {
    cat > "$WORKDIR/eula.txt" <<'EOF'
# Auto-generated by the Modpack Installer plugin.
# By accepting this EULA you confirm the player accepted Mojang's EULA at
# https://account.mojang.com/documents/minecraft_eula
eula=true
EOF
}

# Find a runnable launcher jar in $WORKDIR and symlink server.jar to it.
# Two non-obvious things this function handles:
#  - Modern Forge / NeoForge (1.17+) doesn't ship a runnable fat jar at all —
#    the launcher lives in `libraries/.../forge-*-server.jar` and is invoked
#    via `run.sh` and an `@unix_args.txt` argfile. The Pelican Forge / NeoForge
#    runtime eggs gate startup on `[ -f unix_args.txt ]` AT THE WORKDIR ROOT,
#    so we symlink the in-libraries argfile up so the egg picks `@unix_args.txt`
#    instead of falling through to a missing `-jar server.jar`.
#  - Every candidate is verified to actually expose a Main-Class. Without
#    that check, a stale leftover (purge=0 path) or a random mod jar caught
#    by a too-permissive find could end up symlinked as the entry point and
#    crash the server at startup with `no main manifest attribute`.
finalize_jar() {
    cd "$WORKDIR" || return 1

    # Authoritative path: a loader-install routine (install_forge,
    # install_neoforge, …) dropped a `.peregrine-server-jar` marker
    # naming the file it just produced. The routine already verified
    # the file is at workdir root and non-empty, so trust it without
    # re-running jar_has_main_class — that probe is heuristic and
    # known to false-negative on jars whose manifest wraps oddly
    # (Forge 1.12.2's hundreds-of-bytes Class-Path is the canonical
    # offender). The marker bypass eliminates that whole class of
    # false negatives in one shot.
    if [ -f "$WORKDIR/.peregrine-server-jar" ]; then
        local marker_target
        marker_target=$(head -n1 "$WORKDIR/.peregrine-server-jar" 2>/dev/null | tr -d '\r\n ')
        if [ -n "$marker_target" ] && [ -f "$WORKDIR/$marker_target" ]; then
            log "Using marker .peregrine-server-jar: server.jar -> $marker_target"
            ln -sf "$marker_target" "$WORKDIR/server.jar"
            return 0
        fi
        warn "marker .peregrine-server-jar points at '$marker_target' which is missing — falling through to heuristic detection"
    fi

    # An existing root-level server.jar is only acceptable if it actually
    # has a Main-Class. Otherwise it's either a stale leftover (purge=0
    # path), the universal jar of modern Forge (no main class), or an
    # installer artifact that snuck in — discard and let the detection
    # logic below pick the real launcher.
    if [ -f "server.jar" ] && [ -s "server.jar" ] && jar_has_main_class "server.jar"; then
        log "server.jar already in place ($(stat -c%s server.jar) bytes, has Main-Class)."
        return 0
    fi
    if [ -f "server.jar" ] || [ -L "server.jar" ]; then
        warn "Existing server.jar lacks Main-Class — discarding so detection can pick a runnable jar"
        rm -f "server.jar"
    fi

    # Modern Forge / NeoForge: launcher script + libraries tree. Surface
    # `unix_args.txt` to the workdir root so existing Pelican Forge eggs
    # whose startup checks `[ -f unix_args.txt ]` resolve to
    # `@unix_args.txt` instead of `-jar server.jar`. Without this symlink
    # the egg falls back to a non-existent server.jar and the server
    # crashes on first start.
    if [ -f "$WORKDIR/run.sh" ]; then
        log "Detected modern Forge/NeoForge launcher (run.sh)"
        local args_file rel
        args_file=$(find "$WORKDIR/libraries" -type f -name 'unix_args.txt' 2>/dev/null | head -n1)
        if [ -n "$args_file" ]; then
            rel=$(realpath --relative-to="$WORKDIR" "$args_file" 2>/dev/null) || rel="$args_file"
            ln -sf "$rel" "$WORKDIR/unix_args.txt"
            log "Symlinked unix_args.txt -> $rel (so Pelican Forge egg startup uses @unix_args.txt)"
        else
            warn "run.sh found but libraries/.../unix_args.txt missing — install may be incomplete"
        fi
        return 0
    fi

    # Pattern lookup is restricted to WORKDIR root (-maxdepth 1). The old
    # -maxdepth 2 search let `mods/random.jar` and our own
    # `.peregrine-modpack-tmp-XXX/forge-installer.jar` win the catch-all
    # `*.jar` race when the loader install failed silently — the symlink
    # then pointed at a mod with no Main-Class and the server crashed at
    # startup with `no main manifest attribute, in server.jar`.
    local candidate=""
    local pattern
    for pattern in \
        "neoforge-*-universal.jar" \
        "neoforge-*-server.jar" \
        "neoforge-*.jar" \
        "forge-*-universal.jar" \
        "forge-*-server.jar" \
        "forge-*.jar" \
        "fabric-server-launch.jar" \
        "fabric-server-mc*.jar" \
        "quilt-server-launch.jar" \
        "quilt-server-launcher.jar" \
        "minecraft_server.*.jar" \
        "paper-*.jar" \
        "purpur-*.jar"
    do
        candidate=$(find "$WORKDIR" -maxdepth 1 -type f -name "$pattern" -size +1c 2>/dev/null | head -n1)
        [ -n "$candidate" ] && break
    done

    # Final fallback: any jar at workdir root (depth 1) that actually has
    # a Main-Class. Mods (mods/), libraries (libraries/) and our staging
    # dir (.peregrine-modpack-tmp-*) are excluded by depth alone, so this
    # can only land on a real launcher. Without the Main-Class check, a
    # legacy Forge install where the catch-all picked
    # `minecraft_server.{mc}.jar` over `forge-{mc}-{forge}.jar` would
    # boot vanilla and silently ignore the mods.
    if [ -z "$candidate" ]; then
        local jar
        while IFS= read -r jar; do
            if jar_has_main_class "$jar"; then
                candidate="$jar"
                break
            fi
        done < <(find "$WORKDIR" -maxdepth 1 -type f -name '*.jar' -size +1c 2>/dev/null)
    fi

    if [ -z "$candidate" ]; then
        warn "no candidate jar found at $WORKDIR root — server.jar not created"
        return 1
    fi

    if ! jar_has_main_class "$candidate"; then
        warn "best candidate $(basename "$candidate") has no Main-Class — refusing to symlink"
        return 1
    fi

    log "Symlinking server.jar -> $(basename "$candidate") ($(stat -c%s "$candidate") bytes)"
    ln -sf "$(realpath --relative-to="$WORKDIR" "$candidate")" "$WORKDIR/server.jar"
}

# ---------------------------------------------------------------------------
# Provider: Modrinth — https://docs.modrinth.com/api/operations/getversion/
# ---------------------------------------------------------------------------
install_modrinth() {
    log "Provider: Modrinth — version $BB_MODPACK_VERSION_ID"

    local version_json files_count primary_url primary_filename
    version_json=$(http_get "https://api.modrinth.com/v2/version/$BB_MODPACK_VERSION_ID") \
        || fail "failed to fetch Modrinth version metadata"

    files_count=$(echo "$version_json" | jq '.files | length')
    [ "$files_count" -gt 0 ] || fail "Modrinth version has no files"

    primary_url=$(echo "$version_json" | jq -r '(.files[] | select(.primary == true) | .url) // .files[0].url')
    primary_filename=$(echo "$version_json" | jq -r '(.files[] | select(.primary == true) | .filename) // .files[0].filename')
    [ -n "$primary_url" ] && [ "$primary_url" != "null" ] || fail "Modrinth primary file URL missing"

    log "Downloading $primary_filename"
    # Modpack archives can run >100MB (Cobblemon, ATM10, etc.) — use the
    # large-file budget instead of the per-mod default.
    http_download_large "$primary_url" "$TMPDIR/$primary_filename" \
        || fail "Modrinth pack download failed"

    case "$primary_filename" in
        *.mrpack)
            install_modrinth_mrpack "$TMPDIR/$primary_filename"
            ;;
        *.zip)
            log "Extracting zip into $WORKDIR"
            unzip -qo "$TMPDIR/$primary_filename" -d "$WORKDIR" || fail "unzip failed"
            # Same nesting heuristic as the CurseForge server-pack path —
            # some Modrinth uploads wrap their server bundle in a single
            # top-level directory, which breaks finalize_jar's root-only
            # lookup.
            flatten_single_top_dir "$WORKDIR"
            ;;
        *.jar)
            log "Direct jar — installing as $primary_filename"
            cp "$TMPDIR/$primary_filename" "$WORKDIR/$primary_filename"
            ;;
        *)
            warn "unrecognized Modrinth artifact: $primary_filename — copying raw"
            cp "$TMPDIR/$primary_filename" "$WORKDIR/$primary_filename"
            ;;
    esac
}

# .mrpack format: zip with modrinth.index.json describing files{} (downloads from
# external URLs, write to path) and an overrides/ directory copied as-is.
install_modrinth_mrpack() {
    local pack="$1"
    local extract="$TMPDIR/mrpack"
    local pack_size
    pack_size=$(stat -c%s "$pack" 2>/dev/null || echo 0)

    log "Extracting .mrpack manifest ($((pack_size / 1024 / 1024)) MB) into $extract"
    mkdir -p "$extract"
    if ! unzip -qo "$pack" -d "$extract" </dev/null; then
        fail "mrpack extract failed"
    fi

    local extracted_count
    extracted_count=$(find "$extract" -type f 2>/dev/null | wc -l)
    log "  → extracted $extracted_count file(s) from .mrpack"

    [ -f "$extract/modrinth.index.json" ] || fail "modrinth.index.json missing in mrpack"

    if [ -d "$extract/overrides" ]; then
        local overrides_count
        overrides_count=$(find "$extract/overrides" -type f 2>/dev/null | wc -l)
        log "Copying overrides/ ($overrides_count file(s))"
        cp -rT "$extract/overrides" "$WORKDIR"
        log "  → overrides copied"
    fi
    if [ -d "$extract/server-overrides" ]; then
        local soverrides_count
        soverrides_count=$(find "$extract/server-overrides" -type f 2>/dev/null | wc -l)
        log "Copying server-overrides/ ($soverrides_count file(s))"
        cp -rT "$extract/server-overrides" "$WORKDIR"
        log "  → server-overrides copied"
    fi

    local total resolved required required_failed index
    total=$(jq '.files | length' "$extract/modrinth.index.json")
    log "Resolving $total external file(s) declared in modrinth.index.json"
    resolved=0
    required=0
    required_failed=0

    local progress_start
    progress_start=$(date +%s)

    for index in $(seq 0 $((total - 1))); do
        local entry path url server_env mirror mirror_count m
        entry=$(jq -c ".files[$index]" "$extract/modrinth.index.json")
        path=$(echo "$entry" | jq -r '.path')
        url=$(echo "$entry" | jq -r '.downloads[0]')
        server_env=$(echo "$entry" | jq -r '.env.server // "required"')

        # Periodic progress so the operator can see the script is alive
        # during long mod resolutions (large packs declare 200+ files).
        if [ $((index % 10)) -eq 0 ] && [ "$index" -gt 0 ]; then
            local elapsed=$(( $(date +%s) - progress_start ))
            log "  progress: $index/$total resolved=$resolved (${elapsed}s elapsed)"
        fi

        if [ "$server_env" = "unsupported" ]; then
            continue
        fi

        if [ "$server_env" = "required" ]; then
            required=$((required + 1))
        fi

        local outpath="$WORKDIR/$path"
        mkdir -p "$(dirname "$outpath")"

        # mrpack files declare a list of download mirrors — try each in
        # order. The previous version only ever tried the first URL and
        # silently dropped required files on transient failures.
        local got=0
        if http_download "$url" "$outpath"; then
            got=1
        else
            mirror_count=$(echo "$entry" | jq '.downloads | length // 0')
            for m in $(seq 1 $((mirror_count - 1))); do
                mirror=$(echo "$entry" | jq -r ".downloads[$m]")
                if [ -n "$mirror" ] && [ "$mirror" != "null" ]; then
                    log "  retrying via mirror: $mirror"
                    if http_download "$mirror" "$outpath"; then
                        got=1
                        break
                    fi
                fi
            done
        fi

        if [ "$got" -eq 1 ]; then
            resolved=$((resolved + 1))
        else
            if [ "$server_env" = "required" ]; then
                required_failed=$((required_failed + 1))
                warn "REQUIRED file failed: $path"
            else
                warn "optional file failed: $path"
            fi
        fi
    done
    log "Resolved $resolved/$total file(s) (required failed: $required_failed/$required)"

    if [ "$required_failed" -gt 0 ]; then
        crit "$required_failed required mrpack file(s) failed to download — modpack would be incomplete"
    fi

    local mc forge fabric quilt neoforge
    mc=$(jq -r '.dependencies.minecraft // ""' "$extract/modrinth.index.json")
    forge=$(jq -r '.dependencies["forge"] // ""' "$extract/modrinth.index.json")
    neoforge=$(jq -r '.dependencies["neoforge"] // ""' "$extract/modrinth.index.json")
    fabric=$(jq -r '.dependencies["fabric-loader"] // ""' "$extract/modrinth.index.json")
    quilt=$(jq -r '.dependencies["quilt-loader"] // ""' "$extract/modrinth.index.json")

    if [ -n "$forge" ] && [ -n "$mc" ]; then
        install_forge "$mc" "$forge"
    elif [ -n "$neoforge" ] && [ -n "$mc" ]; then
        install_neoforge "$mc" "$neoforge"
    elif [ -n "$fabric" ] && [ -n "$mc" ]; then
        install_fabric "$mc" "$fabric"
    elif [ -n "$quilt" ] && [ -n "$mc" ]; then
        install_quilt "$mc" "$quilt"
    elif [ -n "$mc" ]; then
        install_vanilla "$mc"
    else
        warn "no loader dependency declared in mrpack — leaving server jar to manual placement"
    fi
}

# ---------------------------------------------------------------------------
# Provider: CurseForge — https://docs.curseforge.com/rest-api/
# ---------------------------------------------------------------------------
install_curseforge() {
    log "Provider: CurseForge — mod $BB_MODPACK_ID, file $BB_MODPACK_VERSION_ID"
    [ -n "${BB_MODPACK_CURSEFORGE_KEY:-}" ] || fail "CurseForge API key missing — operator must set it in /admin/modpack-settings"

    local hdr="x-api-key: $BB_MODPACK_CURSEFORGE_KEY"
    local user_file_json server_file_json server_pack_id is_server_pack

    # Always fetch the user-modpack metadata first and keep it around even
    # when we redirect to a server pack — the user-side file is the only
    # one guaranteed to ship `manifest.json` (server packs are often flat
    # runtime bundles without loader info — RLCraft 1.12.2 v2.9.3 is the
    # canonical example).
    user_file_json=$(http_get "https://api.curseforge.com/v1/mods/$BB_MODPACK_ID/files/$BB_MODPACK_VERSION_ID" "$hdr") \
        || fail "failed to fetch CurseForge file metadata"

    is_server_pack=$(echo "$user_file_json" | jq -r '.data.isServerPack // false')
    server_pack_id=$(echo "$user_file_json" | jq -r '.data.serverPackFileId // empty')

    if [ "$is_server_pack" != "true" ] && [ -n "$server_pack_id" ]; then
        log "Modpack file points at server pack $server_pack_id — fetching that for /mnt/server contents."
        server_file_json=$(http_get "https://api.curseforge.com/v1/mods/$BB_MODPACK_ID/files/$server_pack_id" "$hdr") \
            || fail "failed to fetch CurseForge server-pack file"
        is_server_pack="true"
    else
        # Either the user picked a server-pack file directly OR the file
        # has no companion server pack. In both cases the user file is
        # also the server file — no separate manifest fallback needed.
        server_file_json="$user_file_json"
        server_pack_id=""
    fi

    local download_url file_name
    download_url=$(echo "$server_file_json" | jq -r '.data.downloadUrl // empty')
    file_name=$(echo "$server_file_json" | jq -r '.data.fileName // "modpack.zip"')

    if [ -z "$download_url" ]; then
        local file_id_str padded
        file_id_str=$(echo "$server_file_json" | jq -r '.data.id')
        padded=$(printf '%07d' "$file_id_str")
        download_url="https://edge.forgecdn.net/files/${padded:0:4}/${padded:4}/$file_name"
        warn "downloadUrl null; reconstructed CDN URL: $download_url"
    fi

    log "Downloading $file_name"
    http_download_large "$download_url" "$TMPDIR/$file_name" "$hdr" \
        || fail "CurseForge download failed"

    if [ "$is_server_pack" = "true" ]; then
        log "Extracting server-pack zip into $WORKDIR"
        unzip -qo "$TMPDIR/$file_name" -d "$WORKDIR" || fail "unzip failed"

        # Server packs that nest everything in a single top-level dir
        # (RLCraft v2.9.2d, ATM, …) get unfolded so the launcher and any
        # bundled forge installer end up at WORKDIR root where the rest
        # of the egg expects them.
        flatten_single_top_dir "$WORKDIR"

        # ---- Loader-install cascade -----------------------------------
        # Server-pack zips ship in wildly inconsistent shapes. We try
        # paths in decreasing order of confidence and stop the moment a
        # runnable launcher jar (or `run.sh` for modern Forge/NeoForge)
        # appears at the workdir root. Each step is a no-op if its
        # precondition isn't met.
        #
        #  1. `manifest.json` at workdir root         — modern modpack-
        #     style server packs (some ATM releases, ServerStarter packs)
        #  2. launcher script (startserver.sh, ServerStart.sh,
        #     LaunchServer.sh, …)                    — CurseForge wrappers
        #  3. bundled `forge-*-installer.jar`        — RLCraft v2.9.2d
        #  4. user-modpack `manifest.json` fallback  — RLCraft v2.9.3
        #     (server pack ships nothing actionable; the user file does)
        # ---------------------------------------------------------------
        if [ -f "$WORKDIR/manifest.json" ] && ! loader_jar_present; then
            log "Server pack ships manifest.json — installing loader from it"
            install_loader_from_curseforge_manifest "$WORKDIR/manifest.json" || true
        fi

        if ! loader_jar_present; then
            run_pack_launcher_script || true
        fi

        if ! loader_jar_present; then
            run_bundled_loader_installer || true
        fi

        if ! loader_jar_present && [ -n "$server_pack_id" ]; then
            log "Server pack didn't yield a launcher — falling back to user-modpack manifest"
            install_loader_from_user_pack_manifest "$user_file_json" "$hdr" || true
        fi

        if ! loader_jar_present; then
            warn "loader-install cascade exhausted without producing a runnable launcher — finalize_jar will still try, but expect the install to fail"
        fi
    else
        install_curseforge_manifest "$TMPDIR/$file_name" "$hdr"
    fi
}

install_curseforge_manifest() {
    local pack="$1" hdr="$2"
    local extract="$TMPDIR/cfpack"

    mkdir -p "$extract"
    unzip -qo "$pack" -d "$extract" || fail "extract cfpack failed"
    [ -f "$extract/manifest.json" ] || fail "manifest.json missing in CurseForge pack"

    if [ -d "$extract/overrides" ]; then
        log "Copying overrides/"
        cp -rT "$extract/overrides" "$WORKDIR"
    fi

    local total resolved missing required missing_required index
    total=$(jq '.files | length' "$extract/manifest.json")
    log "Resolving $total mod file(s) from CurseForge manifest"
    mkdir -p "$WORKDIR/mods"
    resolved=0
    missing=0
    required=0
    missing_required=0

    for index in $(seq 0 $((total - 1))); do
        local pid fid req file_meta dl filename
        pid=$(jq -r ".files[$index].projectID" "$extract/manifest.json")
        fid=$(jq -r ".files[$index].fileID" "$extract/manifest.json")
        req=$(jq -r ".files[$index].required // true" "$extract/manifest.json")

        if [ "$req" = "true" ]; then
            required=$((required + 1))
        fi

        if ! file_meta=$(http_get "https://api.curseforge.com/v1/mods/$pid/files/$fid" "$hdr"); then
            warn "metadata fetch failed for mod $pid/$fid"
            missing=$((missing + 1))
            [ "$req" = "true" ] && missing_required=$((missing_required + 1))
            continue
        fi

        dl=$(echo "$file_meta" | jq -r '.data.downloadUrl // empty')
        filename=$(echo "$file_meta" | jq -r '.data.fileName // empty')

        # Detect mods that have third-party-distribution disabled. CurseForge's
        # API returns downloadUrl=null AND the reconstructed CDN URL is gated
        # behind authenticated client redirects. There is no automated way to
        # bypass this — surface a clear actionable error so the operator knows
        # the modpack can't be installed automatically.
        if [ -z "$dl" ]; then
            local file_id_str padded reconstructed
            file_id_str=$(echo "$file_meta" | jq -r '.data.id')
            padded=$(printf '%07d' "$file_id_str")
            reconstructed="https://edge.forgecdn.net/files/${padded:0:4}/${padded:4}/$filename"
            log "  $filename: downloadUrl null, trying reconstructed CDN URL"
            if ! http_download "$reconstructed" "$WORKDIR/mods/$filename"; then
                warn "third-party-distribution disabled for $filename (project $pid file $fid) — CDN refused"
                missing=$((missing + 1))
                [ "$req" = "true" ] && missing_required=$((missing_required + 1))
                continue
            fi
            resolved=$((resolved + 1))
            continue
        fi

        if http_download "$dl" "$WORKDIR/mods/$filename"; then
            resolved=$((resolved + 1))
        else
            warn "download failed for $filename"
            missing=$((missing + 1))
            [ "$req" = "true" ] && missing_required=$((missing_required + 1))
        fi
    done
    log "Resolved $resolved/$total mod(s) (missing required: $missing_required/$required)"

    if [ "$missing_required" -gt 0 ]; then
        crit "$missing_required required CurseForge mod(s) failed to download — modpack incomplete (likely third-party-distribution disabled)"
    fi

    install_loader_from_curseforge_manifest "$extract/manifest.json"
}

# ---------------------------------------------------------------------------
# Provider: ATLauncher — https://wiki.atlauncher.com/api-docs/v2/
# ---------------------------------------------------------------------------
install_atlauncher() {
    log "Provider: ATLauncher — pack $BB_MODPACK_ID, version $BB_MODPACK_VERSION_ID"

    local query
    query=$(jq -nc \
        --arg safe "$BB_MODPACK_ID" \
        '{ query: "query($safe: String!) { packBySafeName(safeName: $safe) { id safeName name versions { version minecraftVersion } } }", variables: { safe: $safe } }')
    local resp
    resp=$(http_post_json "https://api.atlauncher.com/v2/graphql" "$query") \
        || fail "ATLauncher GraphQL request failed"

    local pack_id
    pack_id=$(echo "$resp" | jq -r '.data.packBySafeName.id // empty')
    [ -n "$pack_id" ] || fail "ATLauncher pack '$BB_MODPACK_ID' not found"

    # ATLauncher hosts the per-version JSON manifest on a CDN. The launcher's
    # canonical CDN host is `download.nodecdn.net/containers/atl/...` but the
    # public V2 docs do not formally pin the per-version path. Try the nodecdn
    # path first (matches what the official launcher fetches), then fall back
    # to the legacy `download.atlauncher.com` host. If both fail, surface the
    # failure clearly — install cannot proceed without the manifest.
    local manifest=""
    local manifest_urls=(
        "https://download.nodecdn.net/containers/atl/launcher/json/packs/$BB_MODPACK_ID/versions/$BB_MODPACK_VERSION_ID.json"
        "https://download.atlauncher.com/json/packs/$BB_MODPACK_ID/versions/$BB_MODPACK_VERSION_ID.json"
    )
    for manifest_url in "${manifest_urls[@]}"; do
        log "Trying ATLauncher manifest: $manifest_url"
        if manifest=$(http_get "$manifest_url") && [ -n "$manifest" ]; then
            break
        fi
        manifest=""
    done
    [ -n "$manifest" ] || fail "ATLauncher version manifest unreachable on every known CDN"

    local mc loader_type loader_version
    mc=$(echo "$manifest" | jq -r '.minecraft // ""')
    loader_type=$(echo "$manifest" | jq -r '.loader.type // ""')
    loader_version=$(echo "$manifest" | jq -r '.loader.version // ""')

    local total index
    total=$(echo "$manifest" | jq '.mods | length // 0')
    mkdir -p "$WORKDIR/mods"
    for index in $(seq 0 $((total - 1))); do
        local entry side url filename
        entry=$(echo "$manifest" | jq -c ".mods[$index]")
        side=$(echo "$entry" | jq -r '.side // "both"')
        if [ "$side" = "client" ]; then continue; fi
        url=$(echo "$entry" | jq -r '.url // ""')
        filename=$(echo "$entry" | jq -r '.file // ""')
        if [ -z "$url" ] || [ -z "$filename" ]; then continue; fi
        http_download "$url" "$WORKDIR/mods/$filename" || warn "failed mod $filename"
    done

    local cfg_url
    cfg_url=$(echo "$manifest" | jq -r '.configs.url // ""')
    if [ -n "$cfg_url" ]; then
        http_download "$cfg_url" "$TMPDIR/configs.zip" \
            && unzip -qo "$TMPDIR/configs.zip" -d "$WORKDIR" \
            || warn "config bundle failed"
    fi

    case "$loader_type" in
        forge)    install_forge "$mc" "$loader_version" ;;
        neoforge) install_neoforge "$mc" "$loader_version" ;;
        fabric)   install_fabric "$mc" "$loader_version" ;;
        quilt)    install_quilt "$mc" "$loader_version" ;;
        *)        install_vanilla "$mc" ;;
    esac
}

# ---------------------------------------------------------------------------
# Provider: Feed The Beast — https://modpacksch.docs.apiary.io/
# ---------------------------------------------------------------------------
install_ftb() {
    log "Provider: FTB — pack $BB_MODPACK_ID, version $BB_MODPACK_VERSION_ID"

    local server_resp server_url
    server_resp=$(http_get "https://api.modpacks.ch/public/modpack/$BB_MODPACK_ID/$BB_MODPACK_VERSION_ID/server/linux") || true
    server_url=$(echo "$server_resp" | jq -r '.message // empty')
    case "$server_url" in
        http*)
            log "Downloading FTB pre-built server bundle"
            http_download_large "$server_url" "$TMPDIR/ftb-server.tar.gz" || fail "FTB server bundle download failed"
            if file "$TMPDIR/ftb-server.tar.gz" | grep -qi 'gzip'; then
                tar -xzf "$TMPDIR/ftb-server.tar.gz" -C "$WORKDIR"
            else
                unzip -qo "$TMPDIR/ftb-server.tar.gz" -d "$WORKDIR" || true
            fi
            flatten_single_top_dir "$WORKDIR"
            if [ -f "$WORKDIR/serverinstall_linux" ]; then
                chmod +x "$WORKDIR/serverinstall_linux" 2>/dev/null
                (cd "$WORKDIR" && timeout 600 bash serverinstall_linux </dev/null 2>&1 | head -n 200) || true
            fi
            return 0
            ;;
    esac

    log "Server bundle not available, installing files individually."
    local manifest manifest_status
    manifest=$(http_get "https://api.modpacks.ch/public/modpack/$BB_MODPACK_ID/$BB_MODPACK_VERSION_ID") \
        || fail "FTB manifest fetch failed"

    # FTB returns 200 OK with `{"status":"error","message":"..."}` on bad
    # IDs / unknown versions instead of a 4xx — must inspect the envelope.
    manifest_status=$(echo "$manifest" | jq -r '.status // "ok"')
    if [ "$manifest_status" = "error" ]; then
        fail "FTB manifest error: $(echo "$manifest" | jq -r '.message // "unknown"')"
    fi

    local total index
    total=$(echo "$manifest" | jq '.files | length // 0')
    [ "$total" -gt 0 ] || fail "FTB version has no files"
    log "Downloading $total file(s)"
    for index in $(seq 0 $((total - 1))); do
        local entry url path clientonly fullpath
        entry=$(echo "$manifest" | jq -c ".files[$index]")
        url=$(echo "$entry" | jq -r '.url')
        path=$(echo "$entry" | jq -r '(.path // "/") + (.name // "")')
        clientonly=$(echo "$entry" | jq -r '.clientonly // false')
        if [ "$clientonly" = "true" ]; then continue; fi
        fullpath="$WORKDIR/$path"
        mkdir -p "$(dirname "$fullpath")"
        http_download "$url" "$fullpath" || warn "failed: $path"
    done

    local mc loader_type loader_version
    mc=$(echo "$manifest" | jq -r '.targets[] | select(.name == "minecraft") | .version // ""' | head -n1)
    for loader_type in forge neoforge fabric quilt; do
        loader_version=$(echo "$manifest" | jq -r ".targets[] | select(.name == \"$loader_type\") | .version // empty" | head -n1)
        if [ -n "$loader_version" ]; then
            "install_$loader_type" "$mc" "$loader_version"
            return 0
        fi
    done
    install_vanilla "$mc"
}

# ---------------------------------------------------------------------------
# Provider: Technic — https://gist.github.com/EpicKiwi/e91483132ded278b49dbd88c675f0b14
# ---------------------------------------------------------------------------
install_technic() {
    log "Provider: Technic — slug $BB_MODPACK_ID, build $BB_MODPACK_VERSION_ID"

    local launcher_build pack solder build_data zip_url
    launcher_build=$(http_get "https://api.technicpack.net/launcher/version/stable4" 2>/dev/null \
        | jq -r '.build // empty') || true
    [ -n "$launcher_build" ] || launcher_build="746"

    pack=$(http_get "https://api.technicpack.net/modpack/$BB_MODPACK_ID?build=$launcher_build") \
        || fail "Technic pack lookup failed"
    solder=$(echo "$pack" | jq -r '.solder // empty')

    if [ -n "$solder" ] && [ "$solder" != "null" ]; then
        build_data=$(http_get "$solder/modpack/$BB_MODPACK_ID/$BB_MODPACK_VERSION_ID") \
            || fail "Technic Solder build fetch failed"
        local total index
        total=$(echo "$build_data" | jq '.mods | length // 0')
        log "Downloading $total mod archive(s) from Technic Solder"
        for index in $(seq 0 $((total - 1))); do
            local mod_url filename
            mod_url=$(echo "$build_data" | jq -r ".mods[$index].url")
            filename=$(echo "$build_data" | jq -r ".mods[$index].name").zip
            http_download "$mod_url" "$TMPDIR/$filename" || continue
            unzip -qo "$TMPDIR/$filename" -d "$WORKDIR" || true
        done
        local mc forge_version
        mc=$(echo "$build_data" | jq -r '.minecraft // ""')
        forge_version=$(echo "$build_data" | jq -r '.forge // ""')
        if [ -n "$forge_version" ] && [ "$forge_version" != "null" ]; then
            install_forge "$mc" "$forge_version"
        else
            install_vanilla "$mc"
        fi
    else
        zip_url=$(echo "$pack" | jq -r '.url // empty')
        [ -n "$zip_url" ] && [ "$zip_url" != "null" ] || fail "Technic non-Solder pack URL missing"
        log "Downloading flat Technic pack zip"
        http_download_large "$zip_url" "$TMPDIR/technic-pack.zip" || fail "Technic pack download failed"
        unzip -qo "$TMPDIR/technic-pack.zip" -d "$WORKDIR" || fail "unzip failed"
        flatten_single_top_dir "$WORKDIR"
    fi
}

# ---------------------------------------------------------------------------
# Provider: VoidsWrath — community catalog mirror
# ---------------------------------------------------------------------------
install_voidswrath() {
    log "Provider: VoidsWrath — id $BB_MODPACK_ID"

    local catalog entry server_url
    catalog=$(http_get "https://raw.githubusercontent.com/astrooom/minecraft-modpack-index/main/voidswrath-modpacks.json") \
        || fail "VoidsWrath catalog fetch failed"

    entry=$(echo "$catalog" | jq -c "first(.[] | select((.id|tostring) == \"$BB_MODPACK_ID\"))")
    [ -n "$entry" ] && [ "$entry" != "null" ] || fail "VoidsWrath modpack '$BB_MODPACK_ID' not in catalog"

    server_url=$(echo "$entry" | jq -r '.serverPackUrl // empty')
    [ -n "$server_url" ] && [ "$server_url" != "null" ] || fail "VoidsWrath modpack has no serverPackUrl"

    log "Downloading VoidsWrath server pack"
    http_download_large "$server_url" "$TMPDIR/voids.zip" || fail "VoidsWrath download failed"
    unzip -qo "$TMPDIR/voids.zip" -d "$WORKDIR" || fail "unzip failed"
    flatten_single_top_dir "$WORKDIR"
    # VoidsWrath packs sometimes ship the loader installer bundled
    # alongside their startserver wrapper without actually running it.
    run_bundled_loader_installer || true
}

# ---------------------------------------------------------------------------
# Loader installers — vanilla / Forge / NeoForge / Fabric / Quilt
# ---------------------------------------------------------------------------
install_vanilla() {
    local mc="$1"
    [ -n "$mc" ] || { crit "install_vanilla: no mc version"; return 1; }
    log "Installing vanilla Minecraft server $mc"

    local manifest version_url server_url
    manifest=$(http_get "https://launchermeta.mojang.com/mc/game/version_manifest_v2.json") || {
        crit "Mojang version manifest unreachable"
        return 1
    }
    version_url=$(echo "$manifest" | jq -r --arg v "$mc" '.versions[] | select(.id == $v) | .url' | head -n1)
    [ -n "$version_url" ] || { crit "Mojang manifest does not contain version $mc"; return 1; }

    local version_meta
    version_meta=$(http_get "$version_url") || { crit "Mojang version metadata unreachable for $mc"; return 1; }
    server_url=$(echo "$version_meta" | jq -r '.downloads.server.url // empty')
    [ -n "$server_url" ] && [ "$server_url" != "null" ] || {
        crit "No server jar published by Mojang for $mc (likely a snapshot or pre-1.2.5)"
        return 1
    }

    if ! http_download "$server_url" "$WORKDIR/server.jar"; then
        crit "Vanilla server jar download failed for $mc"
        return 1
    fi
    if [ ! -s "$WORKDIR/server.jar" ]; then
        crit "Vanilla server jar empty after download"
        return 1
    fi
}

install_forge() {
    local mc="$1" forge="$2"
    [ -n "$mc" ] && [ -n "$forge" ] || { crit "install_forge: missing args (mc=$mc forge=$forge)"; return 1; }
    log "Installing Forge $forge for $mc"

    local installer="$TMPDIR/forge-installer.jar"
    # Try every known Forge installer URL pattern. Legacy 1.7.10/1.8.x duplicate
    # the MC version in the path; modern 1.16+ does not. We try both rather than
    # branching on heuristics so unusual builds (1.7.10-10.13.x, 1.8.9-11.15.x)
    # are caught.
    local installer_urls=(
        "https://maven.minecraftforge.net/net/minecraftforge/forge/${mc}-${forge}/forge-${mc}-${forge}-installer.jar"
        "https://maven.minecraftforge.net/net/minecraftforge/forge/${mc}-${forge}-${mc}/forge-${mc}-${forge}-${mc}-installer.jar"
        "https://files.minecraftforge.net/maven/net/minecraftforge/forge/${mc}-${forge}/forge-${mc}-${forge}-installer.jar"
    )

    local got=0 url
    for url in "${installer_urls[@]}"; do
        log "Trying Forge installer: $url"
        if http_download "$url" "$installer" && [ -s "$installer" ]; then
            got=1
            break
        fi
    done
    if [ "$got" -ne 1 ]; then
        crit "Forge installer unreachable for $mc-$forge (every known URL failed)"
        return 1
    fi

    # `--installServer .` (with dot) is required by all Forge installers from
    # 1.16+ onwards; older installers ignore the argument silently. The
    # installer is invoked through `pick_installer_java` because Forge
    # 1.7-1.16 silently no-op on Java 17 (uses sealed JVM internals).
    local java_bin
    java_bin=$(pick_installer_java "$mc")
    log "  using $("$java_bin" -version 2>&1 | head -n1) for the Forge installer"

    local installer_log="$TMPDIR/forge-install.log"
    if ! (cd "$WORKDIR" && timeout 900 "$java_bin" -jar "$installer" --installServer . > "$installer_log" 2>&1); then
        warn "Forge installer returned non-zero — last 30 lines:"
        tail -n 30 "$installer_log" >&2 || true
        # Don't fail here — older Forge versions exit non-zero even on
        # success after writing the jar. The post-install jar probe
        # below catches the real failure mode (no jar produced).
    fi

    # Confirm the installer actually wrote a runnable launcher at root.
    # The output filename differs across Forge generations:
    #   - 1.7-1.12 (early Installer 1.x): forge-{mc}-{forge}-universal.jar
    #   - 1.12.2 (Installer 2.x, build 2854+) and 1.13-1.16: forge-{mc}-{forge}.jar
    #   - 1.17+: run.sh + libraries/.../unix_args.txt (no fat jar)
    # We accept any of those, but exclude `forge-*-installer.jar` from
    # the wildcard so the installer-jar we just downloaded doesn't get
    # mistaken for the produced launcher. The Main-Class check in
    # finalize_jar later is what guarantees the picked jar is actually
    # runnable; here we only assert the install step *did something*.
    local produced
    produced=$(find "$WORKDIR" -maxdepth 1 -type f \
        \( -name 'forge-*-universal.jar' \
           -o -name 'forge-*-server.jar' \
           -o -name 'forge-*.jar' \
           -o -name 'run.sh' \) \
        ! -name 'forge-*-installer.jar' \
        -size +1c 2>/dev/null | head -n1)
    if [ -z "$produced" ]; then
        warn "Forge installer ran but produced no launcher at workdir root."
        if [ -s "$installer_log" ]; then
            warn "Last 30 lines of installer output:"
            tail -n 30 "$installer_log" >&2 || true
        fi
        crit "Forge $forge install (mc $mc) failed silently — usually a Java compat issue. See installer log above."
        return 1
    fi
    log "  Forge installer produced: $(basename "$produced")"

    # Drop a marker that finalize_jar will trust unconditionally for the
    # symlink target. The loader-routine has already verified the file
    # exists, is non-empty, and was written by a successful installer
    # run; the heuristic `jar_has_main_class` probe in finalize_jar is
    # only there for the no-marker fallback path. This bypass exists
    # because Forge 1.12.2's launcher manifest has a Class-Path that's
    # hundreds of bytes long with line-wrapping, and depending on the
    # exact manifest layout the probe can return a false negative even
    # though `java -jar` would happily run the jar.
    printf '%s\n' "$(basename "$produced")" > "$WORKDIR/.peregrine-server-jar"
}

install_neoforge() {
    local mc="$1" neo="$2"
    [ -n "$neo" ] || { crit "install_neoforge: missing version"; return 1; }
    log "Installing NeoForge $neo (mc $mc)"

    local installer="$TMPDIR/neoforge-installer.jar"
    # NeoForge moved its maven layout once early on; legacy 20.x packs may
    # still point at the `forge` artifactId. Try both.
    local installer_urls=(
        "https://maven.neoforged.net/releases/net/neoforged/neoforge/${neo}/neoforge-${neo}-installer.jar"
        "https://maven.neoforged.net/releases/net/neoforged/forge/${neo}/forge-${neo}-installer.jar"
    )

    local got=0 url
    for url in "${installer_urls[@]}"; do
        log "Trying NeoForge installer: $url"
        if http_download "$url" "$installer" && [ -s "$installer" ]; then
            got=1
            break
        fi
    done
    if [ "$got" -ne 1 ]; then
        crit "NeoForge installer unreachable for $neo (every known URL failed)"
        return 1
    fi

    # NeoForge ships only for MC 1.20.4+ where Java 17 is required at
    # both install and runtime. pick_installer_java still returns "java"
    # here (modern path) but we route through the helper so a future
    # Java-version regression has only one place to fix.
    local java_bin
    java_bin=$(pick_installer_java "$mc")

    local installer_log="$TMPDIR/neoforge-install.log"
    if ! (cd "$WORKDIR" && timeout 900 "$java_bin" -jar "$installer" --installServer . > "$installer_log" 2>&1); then
        warn "NeoForge installer returned non-zero — last 30 lines:"
        tail -n 30 "$installer_log" >&2 || true
    fi

    # Same silent-no-op verification as install_forge — NeoForge ships
    # only for 1.20.4+ where the installer produces a `run.sh` launcher
    # plus libraries/, but we also tolerate the legacy `*-server.jar`
    # / `*-universal.jar` patterns in case a future build reverts.
    local produced
    produced=$(find "$WORKDIR" -maxdepth 1 -type f \
        \( -name 'neoforge-*-universal.jar' \
           -o -name 'neoforge-*-server.jar' \
           -o -name 'neoforge-*.jar' \
           -o -name 'run.sh' \) \
        ! -name 'neoforge-*-installer.jar' \
        -size +1c 2>/dev/null | head -n1)
    if [ -z "$produced" ]; then
        warn "NeoForge installer ran but produced no launcher at workdir root."
        if [ -s "$installer_log" ]; then
            warn "Last 30 lines of installer output:"
            tail -n 30 "$installer_log" >&2 || true
        fi
        crit "NeoForge $neo install (mc $mc) failed silently — see installer log above."
        return 1
    fi
    log "  NeoForge installer produced: $(basename "$produced")"

    # Same marker as install_forge — only meaningful when the installer
    # produced a runnable launcher jar (not run.sh). Modern NeoForge ships
    # `run.sh` + `unix_args.txt` instead, which finalize_jar handles via
    # its own dedicated branch.
    case "$produced" in
        *.jar) printf '%s\n' "$(basename "$produced")" > "$WORKDIR/.peregrine-server-jar" ;;
    esac
}

install_fabric() {
    local mc="$1" fab="$2"
    [ -n "$mc" ] && [ -n "$fab" ] || { crit "install_fabric: missing args (mc=$mc fab=$fab)"; return 1; }
    log "Installing Fabric loader $fab for $mc"

    local installer_meta installer_version
    installer_meta=$(http_get "https://meta.fabricmc.net/v2/versions/installer") || {
        crit "Fabric installer metadata unreachable"
        return 1
    }
    installer_version=$(echo "$installer_meta" | jq -r '.[0].version // empty')
    [ -n "$installer_version" ] || {
        crit "Fabric installer metadata missing latest version"
        return 1
    }

    local url="https://meta.fabricmc.net/v2/versions/loader/${mc}/${fab}/${installer_version}/server/jar"
    if ! http_download "$url" "$WORKDIR/fabric-server-launch.jar"; then
        crit "Fabric server jar download failed (mc=$mc loader=$fab) — Fabric does not support this MC version or the loader version is invalid"
        return 1
    fi

    if [ ! -s "$WORKDIR/fabric-server-launch.jar" ]; then
        crit "Fabric server jar is empty — download corrupted"
        return 1
    fi
}

install_quilt() {
    local mc="$1" quilt="$2"
    [ -n "$mc" ] && [ -n "$quilt" ] || { crit "install_quilt: missing args"; return 1; }
    log "Installing Quilt loader $quilt for $mc"

    local installer="$TMPDIR/quilt-installer.jar"
    if ! http_download "https://quiltmc.org/api/v1/download-latest-installer/java-universal" "$installer"; then
        crit "Quilt installer unreachable"
        return 1
    fi

    local installer_log="$TMPDIR/quilt-install.log"
    if ! (cd "$WORKDIR" && timeout 600 java -jar "$installer" install server "$mc" "$quilt" --download-server --install-dir="$WORKDIR" > "$installer_log" 2>&1); then
        warn "Quilt installer returned non-zero — last 30 lines:"
        tail -n 30 "$installer_log" >&2 || true
        # quilt installer has been observed to exit non-zero after a
        # successful install — let finalize_jar make the final call.
    fi
}

# ---------------------------------------------------------------------------
# Uninstall mode — wipe /mnt/server clean. The plugin then swaps the server
# back to the original egg with skip_scripts=false to let it install from
# scratch on an empty directory. Phase 1 of the two-phase uninstall flow.
# ---------------------------------------------------------------------------
uninstall_modpack() {
    log "Uninstall mode — wiping $WORKDIR"

    mkdir -p "$WORKDIR"
    cd "$WORKDIR" || fail "cannot cd $WORKDIR"

    # Delete every file/dir at the root of /mnt/server. -mindepth 1 keeps
    # the mountpoint itself intact so Wings doesn't lose the volume.
    find . -mindepth 1 -delete 2>/dev/null || true

    # Pelican expects an install completion signal — a successful exit is
    # enough; no placeholder files needed because the next phase (original
    # egg reinstall) will repopulate the directory from scratch.
    log "Uninstall complete — directory wiped"
    exit 0
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
    log "Modpack Installer starting (operation=${BB_MODPACK_OPERATION:-install})"

    if [ "${BB_MODPACK_OPERATION:-install}" = "uninstall" ]; then
        uninstall_modpack
    fi

    : "${BB_MODPACK_PROVIDER:?provider not set}"
    : "${BB_MODPACK_ID:?modpack id not set}"
    : "${BB_MODPACK_VERSION_ID:?version id not set}"

    ensure_tools
    prepare_workdir

    case "$BB_MODPACK_PROVIDER" in
        modrinth)   install_modrinth ;;
        curseforge) install_curseforge ;;
        atlauncher) install_atlauncher ;;
        ftb)        install_ftb ;;
        technic)    install_technic ;;
        voidswrath) install_voidswrath ;;
        *) fail "unknown provider: $BB_MODPACK_PROVIDER" ;;
    esac

    write_eula

    if ! finalize_jar; then
        fail "server.jar could not be located after install — pack did not produce a runnable server. Check the log above for the loader installer output."
    fi

    if [ "$CRITICAL_FAILURES" -gt 0 ]; then
        fail "$CRITICAL_FAILURES critical error(s) occurred during install (see CRITICAL log lines above) — refusing to mark install successful with a broken /mnt/server"
    fi

    log "Modpack installation complete"
    exit 0
}

main "$@"
