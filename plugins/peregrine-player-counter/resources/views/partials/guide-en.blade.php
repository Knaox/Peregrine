@php
    $h = 'font-size:0.95rem;font-weight:700;margin:1.3rem 0 0.4rem;';
    $p = 'margin:0.35rem 0;';
    $note = 'margin:0.7rem 0;padding:0.6rem 0.8rem;border-radius:0.5rem;background:rgba(148,163,184,0.12);border:1px solid rgba(148,163,184,0.2);font-size:0.8rem;';
    $code = 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:rgba(148,163,184,0.18);padding:0.05rem 0.3rem;border-radius:0.3rem;font-size:0.8rem;';
@endphp

<p style="{{ $p }}">The player counter needs a tiny companion service — the <strong>GameDig sidecar</strong> — that speaks each game's query protocol (Steam/Source A2S, Minecraft, Epic/EOS for ARK: Survival Ascended, Hytale). Peregrine talks to it over HTTP and caches the result; the sidecar reaches your game servers (and, for EOS games, Epic's API). It ships inside this plugin under <code style="{{ $code }}">plugins/peregrine-player-counter/sidecar</code>.</p>

<h3 style="{{ $h }}">1 — Add the sidecar to your <code style="{{ $code }}">docker-compose.yml</code></h3>
<p style="{{ $p }}">Paste this service under <code style="{{ $code }}">services:</code>, next to the <code style="{{ $code }}">peregrine</code> service, then run <code style="{{ $code }}">docker compose up -d --build game-query</code>.</p>
@include('peregrine-player-counter::partials.copy-block', ['code' => $ctx['composeSnippet'], 'filename' => 'docker-compose.yml — add under services:'])

<div style="{{ $note }}">The sidecar stays on the internal Docker network (no published port) and needs <strong>outbound</strong> access: UDP for A2S/Minecraft, and HTTPS to Epic for EOS games (ARK). Docker allows outbound by default.</div>

<h3 style="{{ $h }}">2 — Point the plugin at the sidecar</h3>
<p style="{{ $p }}">In the form above, set <strong>Sidecar URL</strong> to the service name on the Docker network, switch <strong>Enabled</strong> on, then Save:</p>
@include('peregrine-player-counter::partials.copy-block', ['code' => $ctx['sidecarUrlDocker'], 'filename' => 'Sidecar URL (Docker)'])
<p style="{{ $p }}">Then click <strong>Test sidecar</strong> (top-right) to confirm Peregrine can reach it.</p>

<h3 style="{{ $h }}">Not using Docker?</h3>
<p style="{{ $p }}">Run the sidecar with Node directly (needs Node 18+), then set the Sidecar URL to <code style="{{ $code }}">{{ $ctx['sidecarUrlLocal'] }}</code>:</p>
@include('peregrine-player-counter::partials.copy-block', ['code' => $ctx['bareMetalCmd'], 'filename' => 'shell'])
<div style="{{ $note }}">For production, keep it alive with systemd or supervisor — see <code style="{{ $code }}">plugins/peregrine-player-counter/sidecar/README</code> (or the panel's process manager).</div>

<h3 style="{{ $h }}">Optional — lock the sidecar with a token</h3>
<p style="{{ $p }}">For a shared host, set a <strong>Shared token</strong> above (use the generate button), Save, and add the SAME value as <code style="{{ $code }}">GAME_QUERY_TOKEN</code> on the sidecar service. The compose snippet above already includes it when a token is set.</p>

<div style="{{ $note }}">Once enabled, the widget appears on every server's overview: Steam/Source &amp; Minecraft report instantly; EOS (ARK) &amp; Hytale refresh on a short poll. Hytale needs the <code style="{{ $code }}">hytale-plugin-query</code> mod on the game server. If the sidecar is down, the widget simply shows "offline" — nothing breaks.</div>
