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
    phpMyAdmin is <strong>self-hosted by you</strong>. Peregrine mints one-shot tokens; your instance redeems them over HTTPS to log the user in, scoped to the clicked database.
</p>

<div style="{{ $row }}">
    <div style="{{ $badge }}">1</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Prerequisites</h3>
        <ul style="margin:0;padding-left:1.1rem;opacity:0.82;">
            <li>PHP 8.1+ and phpMyAdmin 5.2+ on the phpMyAdmin host</li>
            <li>HTTPS on phpMyAdmin <strong>and</strong> Peregrine</li>
            <li>The phpMyAdmin host must be able to reach <code style="{{ $code }}">{{ $ctx['peregrineUrl'] }}</code></li>
        </ul>
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">2</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Drop the SignonScript</h3>
        <div style="{{ $note }}">
            <strong>Where is <code style="{{ $code }}">config.inc.php</code>?</strong>
            In the root folder of your phpMyAdmin install (where <code style="{{ $code }}">index.php</code> lives). If it doesn't exist yet, copy <code style="{{ $code }}">config.sample.inc.php</code> to <code style="{{ $code }}">config.inc.php</code>. The SignonScript goes in <strong>that same folder</strong>.
            <ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
                <li style="margin-bottom:0.5rem;">
                    <strong>Manual install</strong> — folder <code style="{{ $code }}">/var/www/html/phpmyadmin/</code><br>
                    File: <code style="{{ $code }}">/var/www/html/phpmyadmin/peregrine_signon.php</code><br>
                    Permissions: <code style="{{ $code }}">sudo chown www-data:www-data /var/www/html/phpmyadmin/peregrine_signon.php && sudo chmod 640 /var/www/html/phpmyadmin/peregrine_signon.php</code>
                </li>
                <li style="margin-bottom:0.5rem;">
                    <strong>Debian / Ubuntu (apt)</strong> — config at <code style="{{ $code }}">/etc/phpmyadmin/config.inc.php</code>, app in <code style="{{ $code }}">/usr/share/phpmyadmin/</code><br>
                    File: <code style="{{ $code }}">/usr/share/phpmyadmin/peregrine_signon.php</code><br>
                    Permissions: <code style="{{ $code }}">sudo chown root:www-data /usr/share/phpmyadmin/peregrine_signon.php && sudo chmod 640 /usr/share/phpmyadmin/peregrine_signon.php</code>
                </li>
                <li>
                    <strong>Docker (<code style="{{ $code }}">phpmyadmin</code> image)</strong> — app in <code style="{{ $code }}">/var/www/html/</code><br>
                    Mount the file at <code style="{{ $code }}">/var/www/html/peregrine_signon.php</code> (volume or <code style="{{ $code }}">COPY</code>); it runs as <code style="{{ $code }}">www-data</code>, so <code style="{{ $code }}">chmod 644</code> is enough.
                </li>
            </ul>
            <p style="margin:0.55rem 0 0;font-size:0.78rem;opacity:0.8;">The file holds your secret: it must be readable by the web-server user (<code style="{{ $code }}">www-data</code> on Debian/Ubuntu, <code style="{{ $code }}">apache</code> on RHEL/Alma) but not world-readable — hence <code style="{{ $code }}">640</code> rather than <code style="{{ $code }}">644</code>.</p>
        </div>
        <p style="{{ $p }}">Create the file <code style="{{ $code }}">peregrine_signon.php</code> in that folder, then paste this (already filled with your URL and secret):</p>
        @include('peregrine-phpmyadmin::partials.copy-block', ['code' => $ctx['signonScript'], 'filename' => 'peregrine_signon.php'])
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">3</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Edit <code style="{{ $code }}">config.inc.php</code></h3>
        <p style="{{ $p }}">Append this server block to your <code style="{{ $code }}">config.inc.php</code>:</p>
        @include('peregrine-phpmyadmin::partials.copy-block', ['code' => $ctx['configSnippet'], 'filename' => 'config.inc.php'])
        <p style="margin:0.4rem 0 0;opacity:0.82;font-size:0.8rem;"><strong>Important:</strong> <code style="{{ $code }}">AllowArbitraryServer = true</code> lets phpMyAdmin accept the database host injected dynamically by the SignonScript (Pelican is multi-host).</p>
        <p style="margin:0.3rem 0 0;opacity:0.82;font-size:0.8rem;">This block adds an <strong>additional server</strong>. Note its index <code style="{{ $code }}">$i</code> (often <strong>2</strong> when a local server is already defined — Debian/apt, <code style="{{ $code }}">config.sample.inc.php</code>) and enter it in Peregrine's <strong>“Signon server index”</strong> field. The button then opens that server via <code style="{{ $code }}">?server=2</code>, while <strong>direct</strong> phpMyAdmin access keeps its <strong>normal login</strong> on the default server. Do <strong>not</strong> set <code style="{{ $code }}">$cfg['ServerDefault']</code> to the signon server (or you could no longer log in without Peregrine).</p>
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">4</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Test the bridge</h3>
        <p style="{{ $p }}">Close this guide and click <strong>“Test the bridge (curl)”</strong>. Copy the command and run it from your phpMyAdmin host: a <code style="{{ $code }}">200</code> response with fake credentials confirms the bridge works.</p>
    </div>
</div>

<div style="{{ $row }}">
    <div style="{{ $badge }}">5</div>
    <div style="{{ $col }}">
        <h3 style="{{ $h }}">Enable</h3>
        <p style="{{ $p }}">Toggle <strong>“Enabled”</strong> on and save. The phpMyAdmin button now appears on every database of every server.</p>
    </div>
</div>

<h3 style="margin:1.4rem 0 0.4rem;font-weight:700;font-size:0.92rem;">{{ __('peregrine-phpmyadmin::messages.settings.troubleshooting') }}</h3>
<ul style="margin:0;padding-left:1.1rem;opacity:0.82;font-size:0.82rem;">
    <li><code style="{{ $code }}">Invalid or expired token</code> — TTL too short / clock skew → raise TTL to 60s, sync NTP.</li>
    <li><code style="{{ $code }}">403 Invalid shared secret</code> — wrong secret on the PMA side → regenerate it, update <code style="{{ $code }}">peregrine_signon.php</code>.</li>
    <li><code style="{{ $code }}">403 IP not allowed</code> — PMA host outside the allowlist → add its IP in the Security section.</li>
    <li><code style="{{ $code }}">SSL certificate problem</code> — self-signed cert → use a real certificate (Let's Encrypt).</li>
    <li>Button never appears → plugin disabled or URL empty.</li>
</ul>
