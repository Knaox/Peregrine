@php
    $badge = 'flex:none;width:1.55rem;height:1.55rem;border-radius:50%;background:#f97316;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.78rem;line-height:1;';
    $row = 'display:flex;gap:0.7rem;margin-bottom:1.15rem;';
    $col = 'flex:1;min-width:0;';
    $h = 'margin:0 0 0.3rem;font-weight:700;font-size:0.92rem;';
    $p = 'margin:0 0 0.2rem;opacity:0.82;';
    $code = 'background:rgba(148,163,184,0.18);padding:0.05rem 0.3rem;border-radius:0.25rem;font-family:ui-monospace,monospace;font-size:0.82em;';
    $note = 'margin:0 0 0.6rem;padding:0.6rem 0.75rem;border:1px solid rgba(249,115,22,0.35);background:rgba(249,115,22,0.08);border-radius:0.5rem;font-size:0.8rem;';
@endphp

<p style="margin:0 0 1.1rem;opacity:0.82;">
    phpMyAdmin est <strong>hébergé par vos soins</strong>. Peregrine génère des tokens à usage unique ; votre instance les échange en HTTPS pour connecter l'utilisateur, scopé sur la base cliquée.
</p>

<div style="{{ $row }}">
    <div style="{{ $badge }}">1</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Pré-requis</h3>
        <ul style="margin:0;padding-left:1.1rem;opacity:0.82;">
            <li>PHP 8.1+ et phpMyAdmin 5.2+ sur le serveur phpMyAdmin</li>
            <li>HTTPS sur phpMyAdmin <strong>et</strong> sur Peregrine</li>
            <li>Le serveur phpMyAdmin doit pouvoir joindre <code style="{{ $code }}">{{ $ctx['peregrineUrl'] }}</code></li>
        </ul>
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">2</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Déposer le SignonScript</h3>
        <div style="{{ $note }}">
            <strong>Où se trouve <code style="{{ $code }}">config.inc.php</code> ?</strong>
            Dans le dossier racine de votre phpMyAdmin (là où se trouve <code style="{{ $code }}">index.php</code>). S'il n'existe pas encore, copiez <code style="{{ $code }}">config.sample.inc.php</code> en <code style="{{ $code }}">config.inc.php</code>. Le SignonScript se place dans <strong>ce même dossier</strong>.
            <ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
                <li style="margin-bottom:0.5rem;">
                    <strong>Installation manuelle</strong> — dossier <code style="{{ $code }}">/var/www/html/phpmyadmin/</code><br>
                    Fichier : <code style="{{ $code }}">/var/www/html/phpmyadmin/peregrine_signon.php</code><br>
                    Permissions : <code style="{{ $code }}">sudo chown www-data:www-data /var/www/html/phpmyadmin/peregrine_signon.php && sudo chmod 640 /var/www/html/phpmyadmin/peregrine_signon.php</code>
                </li>
                <li style="margin-bottom:0.5rem;">
                    <strong>Debian / Ubuntu (apt)</strong> — config dans <code style="{{ $code }}">/etc/phpmyadmin/config.inc.php</code>, application dans <code style="{{ $code }}">/usr/share/phpmyadmin/</code><br>
                    Fichier : <code style="{{ $code }}">/usr/share/phpmyadmin/peregrine_signon.php</code><br>
                    Permissions : <code style="{{ $code }}">sudo chown root:www-data /usr/share/phpmyadmin/peregrine_signon.php && sudo chmod 640 /usr/share/phpmyadmin/peregrine_signon.php</code>
                </li>
                <li>
                    <strong>Docker (image <code style="{{ $code }}">phpmyadmin</code>)</strong> — application dans <code style="{{ $code }}">/var/www/html/</code><br>
                    Montez le fichier sur <code style="{{ $code }}">/var/www/html/peregrine_signon.php</code> (volume ou <code style="{{ $code }}">COPY</code>) ; il tourne en <code style="{{ $code }}">www-data</code>, <code style="{{ $code }}">chmod 644</code> suffit.
                </li>
            </ul>
            <p style="margin:0.55rem 0 0;font-size:0.78rem;opacity:0.8;">Le fichier contient votre secret : il doit être lisible par l'utilisateur du serveur web (<code style="{{ $code }}">www-data</code> sur Debian/Ubuntu, <code style="{{ $code }}">apache</code> sur RHEL/Alma) mais pas par tout le monde — d'où <code style="{{ $code }}">640</code> plutôt que <code style="{{ $code }}">644</code>.</p>
        </div>
        <p style="{{ $p }}">Créez le fichier <code style="{{ $code }}">peregrine_signon.php</code> dans ce dossier, puis collez ce contenu (déjà pré-rempli avec votre URL et votre secret) :</p>
        @include('peregrine-phpmyadmin::partials.copy-block', ['code' => $ctx['signonScript'], 'filename' => 'peregrine_signon.php'])
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">3</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Modifier <code style="{{ $code }}">config.inc.php</code></h3>
        <p style="{{ $p }}">Ajoutez ce bloc serveur à la fin de votre <code style="{{ $code }}">config.inc.php</code> :</p>
        @include('peregrine-phpmyadmin::partials.copy-block', ['code' => $ctx['configSnippet'], 'filename' => 'config.inc.php'])
        <p style="margin:0.4rem 0 0;opacity:0.82;font-size:0.8rem;"><strong>Important :</strong> <code style="{{ $code }}">AllowArbitraryServer = true</code> permet à phpMyAdmin d'accepter l'hôte de base injecté dynamiquement par le SignonScript (Pelican est multi-hôte).</p>
        <p style="margin:0.3rem 0 0;opacity:0.82;font-size:0.8rem;">Ce bloc ajoute un <strong>serveur supplémentaire</strong>. Notez son index <code style="{{ $code }}">$i</code> (souvent <strong>2</strong> si un serveur local est déjà défini — Debian/apt, <code style="{{ $code }}">config.sample.inc.php</code>) et renseignez-le dans Peregrine, champ <strong>« Index du serveur signon »</strong>. Le bouton ouvrira ce serveur via <code style="{{ $code }}">?server=2</code>, tandis que l'<strong>accès direct</strong> à phpMyAdmin garde son <strong>login normal</strong> sur le serveur par défaut. Ne mettez <strong>pas</strong> <code style="{{ $code }}">$cfg['ServerDefault']</code> sur le serveur signon (sinon vous ne pourriez plus vous connecter sans Peregrine).</p>
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">4</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Tester le pont</h3>
        <p style="{{ $p }}">Fermez ce guide et cliquez sur <strong>« Tester le pont (curl) »</strong>. Copiez la commande, exécutez-la depuis votre serveur phpMyAdmin : une réponse <code style="{{ $code }}">200</code> avec des identifiants factices confirme que le pont fonctionne.</p>
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">5</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Activer</h3>
        <p style="{{ $p }}">Basculez <strong>« Activé »</strong> puis enregistrez. Le bouton phpMyAdmin apparaît alors sur chaque base de chaque serveur.</p>
    </div>
</div>

<h3 style="margin:1.4rem 0 0.4rem;font-weight:700;font-size:0.92rem;">{{ __('peregrine-phpmyadmin::messages.settings.troubleshooting') }}</h3>
<ul style="margin:0;padding-left:1.1rem;opacity:0.82;font-size:0.82rem;">
    <li><code style="{{ $code }}">Invalid or expired token</code> — TTL trop court / horloges désynchronisées → passez le TTL à 60 s, synchronisez NTP.</li>
    <li><code style="{{ $code }}">403 Invalid shared secret</code> — mauvais secret côté PMA → régénérez-le, mettez à jour <code style="{{ $code }}">peregrine_signon.php</code>.</li>
    <li><code style="{{ $code }}">403 IP not allowed</code> — serveur PMA hors allowlist → ajoutez son IP dans la section Sécurité.</li>
    <li><code style="{{ $code }}">SSL certificate problem</code> — certificat auto-signé → utilisez un vrai certificat (Let's Encrypt).</li>
    <li>Le bouton n'apparaît pas → plugin désactivé ou URL vide.</li>
</ul>
