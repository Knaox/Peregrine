@php
    $h = 'font-size:0.95rem;font-weight:700;margin:1.3rem 0 0.4rem;';
    $p = 'margin:0.35rem 0;';
    $note = 'margin:0.7rem 0;padding:0.6rem 0.8rem;border-radius:0.5rem;background:rgba(148,163,184,0.12);border:1px solid rgba(148,163,184,0.2);font-size:0.8rem;';
    $code = 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:rgba(148,163,184,0.18);padding:0.05rem 0.3rem;border-radius:0.3rem;font-size:0.8rem;';
@endphp

<p style="{{ $p }}">Le compteur de joueurs a besoin d'un petit service compagnon — le <strong>sidecar GameDig</strong> — qui parle le protocole de requête de chaque jeu (A2S Steam/Source, Minecraft, Epic/EOS pour ARK : Survival Ascended, Hytale). Peregrine le contacte en HTTP et met le résultat en cache ; le sidecar, lui, joint vos serveurs de jeu (et, pour les jeux EOS, l'API d'Epic). Il est livré dans ce plugin sous <code style="{{ $code }}">plugins/peregrine-player-counter/sidecar</code>.</p>

<h3 style="{{ $h }}">1 — Ajoutez le sidecar à votre <code style="{{ $code }}">docker-compose.yml</code></h3>
<p style="{{ $p }}">Collez ce service sous <code style="{{ $code }}">services:</code>, à côté du service <code style="{{ $code }}">peregrine</code>, puis lancez <code style="{{ $code }}">docker compose up -d --build game-query</code>.</p>
@include('peregrine-player-counter::partials.copy-block', ['code' => $ctx['composeSnippet'], 'filename' => 'docker-compose.yml — à ajouter sous services:'])

<div style="{{ $note }}">Le sidecar reste sur le réseau interne Docker (aucun port publié) et a besoin d'un accès <strong>sortant</strong> : UDP pour A2S/Minecraft, et HTTPS vers Epic pour les jeux EOS (ARK). Docker autorise le sortant par défaut.</div>

<h3 style="{{ $h }}">2 — Pointez le plugin vers le sidecar</h3>
<p style="{{ $p }}">Dans le formulaire ci-dessus, mettez <strong>URL du sidecar</strong> au nom du service sur le réseau Docker, activez <strong>Activé</strong>, puis Enregistrez :</p>
@include('peregrine-player-counter::partials.copy-block', ['code' => $ctx['sidecarUrlDocker'], 'filename' => 'URL du sidecar (Docker)'])
<p style="{{ $p }}">Cliquez ensuite sur <strong>Tester le sidecar</strong> (en haut à droite) pour vérifier que Peregrine le joint.</p>

<h3 style="{{ $h }}">Pas de Docker ?</h3>
<p style="{{ $p }}">Lancez le sidecar directement avec Node (Node 18+ requis), puis mettez l'URL du sidecar à <code style="{{ $code }}">{{ $ctx['sidecarUrlLocal'] }}</code> :</p>
@include('peregrine-player-counter::partials.copy-block', ['code' => $ctx['bareMetalCmd'], 'filename' => 'shell'])
<div style="{{ $note }}">En production, gardez-le actif avec systemd ou supervisor — voir <code style="{{ $code }}">plugins/peregrine-player-counter/sidecar/README</code>.</div>

<h3 style="{{ $h }}">Optionnel — verrouillez le sidecar avec un jeton</h3>
<p style="{{ $p }}">Sur un hôte partagé, définissez un <strong>Jeton partagé</strong> ci-dessus (bouton de génération), Enregistrez, et ajoutez la MÊME valeur en <code style="{{ $code }}">GAME_QUERY_TOKEN</code> sur le service sidecar. Le bloc compose ci-dessus l'inclut déjà quand un jeton est défini.</p>

<div style="{{ $note }}">Une fois activé, le widget apparaît sur l'aperçu de chaque serveur : Steam/Source et Minecraft sont instantanés ; EOS (ARK) et Hytale se rafraîchissent par un court polling. Hytale requiert le mod <code style="{{ $code }}">hytale-plugin-query</code> sur le serveur. Si le sidecar est arrêté, le widget affiche simplement « hors ligne » — rien ne casse.</div>
