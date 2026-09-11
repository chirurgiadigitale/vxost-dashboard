<?php
/**
 * VXOST Dashboard v2, phpMyAdmin inside the dashboard shell
 *
 * phpMyAdmin stays the original application, untouched, so it can still be
 * updated: it is framed by the dashboard header and footer, which remain in
 * view. For the iframe to work it needs
 * $cfg['AllowThirdPartyFraming'] = 'sameorigin'; in phpmyadmin/config.inc.php.
 */

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

/** Page strings. */
function db_t(string $key): string
{
    static $s = [
        'en' => [
            'title' => 'Database', 'eyebrow' => 'MariaDB / MySQL',
            'subtitle' => 'phpMyAdmin running inside the dashboard',
            'fullscreen' => 'Open full screen', 'reload' => 'Reload',
            'blocked_t' => 'phpMyAdmin cannot be embedded',
            'blocked_p' => 'phpMyAdmin is answering with X-Frame-Options: DENY, so it refuses to be shown inside a frame. Add this line to phpmyadmin/config.inc.php and reload the page:',
            'open' => 'Open phpMyAdmin directly',
            'csp_unknown' => 'phpMyAdmin sends a Content-Security-Policy with a frame-ancestors this page cannot evaluate: it may or may not allow the frame. Open it directly, or read the header with curl -I.',
            'cert_expired' => 'The certificate in etc/ssl.crt is the right one but it is not valid today (expired, or not yet valid): the browser will refuse the https frame. Regenerate it with bin/vxost-ssl-init.',
            'offline_t' => 'phpMyAdmin is not reachable',
            'offline_p' => 'The server did not answer on /phpmyadmin/. Check that Apache is running and that phpMyAdmin is installed.',
        ],
        'it' => [
            'title' => 'Database', 'eyebrow' => 'MariaDB / MySQL',
            'subtitle' => 'phpMyAdmin all\'interno della dashboard',
            'fullscreen' => 'Apri a schermo intero', 'reload' => 'Ricarica',
            'blocked_t' => 'phpMyAdmin non può essere incorporato',
            'blocked_p' => 'phpMyAdmin risponde con X-Frame-Options: DENY e rifiuta di essere mostrato dentro un frame. Aggiungi questa riga a phpmyadmin/config.inc.php e ricarica la pagina:',
            'open' => 'Apri phpMyAdmin direttamente',
            'csp_unknown' => 'phpMyAdmin manda una Content-Security-Policy con un frame-ancestors che questa pagina non sa valutare: potrebbe consentire il frame o no. Aprilo direttamente, o leggi l\'header con curl -I.',
            'cert_expired' => 'Il certificato in etc/ssl.crt è quello giusto ma oggi non è valido (scaduto, o non ancora valido): il browser rifiuterà il frame https. Rigeneralo con bin/vxost-ssl-init.',
            'offline_t' => 'phpMyAdmin non è raggiungibile',
            'offline_p' => 'Il server non ha risposto su /phpmyadmin/. Verifica che Apache sia avviato e che phpMyAdmin sia installato.',
        ],
    ];
    $lang = vxost_lang();
    return $s[$lang][$key] ?? $s['en'][$key] ?? $key;
}

/**
 * Stato dell'embedding: reachable + framing consentito.
 *
 * @return array{online: bool, framable: bool}
 */
function pma_status(): array
{
    // ⚠️ The scheme comes from the request. On the SSL virtual host
    // SERVER_PORT is 443, and "http://127.0.0.1:443/" talks plain HTTP to a
    // TLS listener: the probe failed and the page said phpMyAdmin was down
    // while it was answering.
    //
    // Over https the probe accepts one certificate only: the one Apache is
    // configured to serve, etc/ssl.crt/server.crt, pinned by its SHA-256
    // fingerprint read from the file at request time. Not a CA check: the
    // stack's certificate is self-signed on a fresh install, or signed by a
    // local CA (mkcert) PHP has never heard of, and cafile pointing at the
    // leaf fails on the second case. The fingerprint is checked whatever
    // verify_peer says, tried both ways: the wrong one is refused.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    $port = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
    $default = $https ? 443 : 80;
    $host = $https ? 'virtualhost' : '127.0.0.1';
    $url = ($https ? 'https' : 'http') . '://' . $host
         . ($port !== $default ? ':' . $port : '') . '/phpmyadmin/';

    $options = ['http' => ['method' => 'HEAD', 'timeout' => 2, 'ignore_errors' => true]];
    if ($https) {
        $served = @file_get_contents(dirname(__DIR__, 2) . '/etc/ssl.crt/server.crt');
        $fingerprint = $served ? @openssl_x509_fingerprint($served, 'sha256') : false;
        if (!$fingerprint) {
            // No certificate to pin against: nothing to trust, so no probe.
            return ['online' => false, 'framable' => false];
        }
        $options['ssl'] = [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'peer_fingerprint' => ['sha256' => $fingerprint],
        ];
    }
    $headers = @get_headers($url, true, stream_context_create($options));

    if (!$headers) {
        return ['online' => false, 'framable' => false];
    }

    // ⚠️ Any reply used to count as online, a 500 included. The status lines
    // sit under numeric keys, one per hop when there is a redirect: the last
    // one is the answer. 2xx and 3xx mean phpMyAdmin answers; 401 is its own
    // login prompt, so it answers too. Anything else is not "online".
    $status = 0;
    foreach ($headers as $key => $value) {
        if (is_int($key) && preg_match('/\s(\d{3})\s/', (string) $value, $m)) {
            $status = (int) $m[1];
        }
    }
    if (!(($status >= 200 && $status < 400) || $status === 401)) {
        return ['online' => false, 'framable' => false];
    }

    $xfo = $headers['X-Frame-Options'] ?? $headers['x-frame-options'] ?? '';
    if (is_array($xfo)) {
        $xfo = end($xfo);
    }
    $xfo = strtoupper(trim((string) $xfo));
    $framable = $xfo === '' || $xfo === 'SAMEORIGIN';

    // A Content-Security-Policy with frame-ancestors overrides X-Frame-Options
    // in every current browser: when it is there, it is the one that counts.
    // Every policy header applies (a browser enforces all of them, and the
    // frame is allowed only if each one allows it), and the sources are
    // matched against THIS page's origin, which is where the frame lives.
    $reason = '';
    $csp = $headers['Content-Security-Policy'] ?? $headers['content-security-policy'] ?? [];
    $policies = is_array($csp) ? $csp : [$csp];
    $pageHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? $host)));
    $verdict = pma_csp_allows_frame($policies, $https ? 'https' : 'http', $pageHost, $port);
    if ($verdict === false) {
        $framable = false;
    } elseif ($verdict === null) {
        // A policy is there and cannot be read for certain: no invented
        // diagnosis. The page says why instead of a confident "blocked".
        $framable = false;
        $reason = 'csp_unknown';
    }

    // The pin proves identity, not validity: a certificate that is the right
    // one but expired is refused by the browser, and the frame stays empty.
    if ($https && !empty($served)) {
        $parsed = @openssl_x509_parse($served);
        $now = time();
        if (is_array($parsed) && (($parsed['validTo_time_t'] ?? 0) < $now
                                  || ($parsed['validFrom_time_t'] ?? 0) > $now)) {
            $framable = false;
            $reason = 'cert_expired';
        }
    }

    return ['online' => true, 'framable' => $framable, 'reason' => $reason];
}

/**
 * Does every Content-Security-Policy allow this page to frame phpMyAdmin?
 *
 * true when no policy restricts it or all of them allow it, false when one
 * forbids it, null when a policy has a frame-ancestors this code cannot
 * evaluate. Only the source forms that matter for a local dashboard are
 * understood: 'none', 'self', *, a scheme, a host with optional scheme, port
 * and leading wildcard. Anything else in a directive makes that directive
 * unevaluable rather than silently allowed.
 *
 * ⚠️ A wildcard is not a universal yes: https://*.example.org allows
 * example.org's subdomains, not virtualhost. The first version matched any
 * asterisk anywhere and said "framable" to that.
 */
function pma_csp_allows_frame(array $policies, string $scheme, string $host, int $port): ?bool
{
    $defaultPort = $scheme === 'https' ? 443 : 80;
    $unknown = false;
    $restricted = false;
    foreach ($policies as $policy) {
        foreach (explode(';', (string) $policy) as $directive) {
            $directive = trim($directive);
            if (stripos($directive, 'frame-ancestors') !== 0) {
                continue;
            }
            $restricted = true;
            $tokens = preg_split('/\s+/', trim(substr($directive, strlen('frame-ancestors'))), -1, PREG_SPLIT_NO_EMPTY);
            $allowed = false;
            $evaluable = true;
            foreach ($tokens as $token) {
                $t = strtolower($token);
                if ($t === "'none'") {
                    $allowed = false;
                    $evaluable = true;
                    break;
                }
                if ($t === "'self'" || $t === '*') {
                    $allowed = true;
                    continue;
                }
                if (preg_match('/^[a-z][a-z0-9+.-]*:$/', $t)) {          // scheme-source
                    if (rtrim($t, ':') === $scheme) {
                        $allowed = true;
                    }
                    continue;
                }
                if (preg_match('#^(?:([a-z][a-z0-9+.-]*)://)?(\*\.)?([a-z0-9.-]+)(?::(\d+|\*))?$#', $t, $m)) {
                    $schemeOk = $m[1] === '' || $m[1] === $scheme;
                    $hostOk = $m[2] === ''
                        ? $m[3] === $host
                        : (strlen($host) > strlen($m[3]) + 1 && substr($host, -strlen($m[3]) - 1) === '.' . $m[3]);
                    $tokenPort = $m[4] ?? '';
                    $portOk = $tokenPort === '' ? $port === $defaultPort : ($tokenPort === '*' || (int) $tokenPort === $port);
                    if ($schemeOk && $hostOk && $portOk) {
                        $allowed = true;
                    }
                    continue;
                }
                $evaluable = false;                                       // nonces, hashes, anything else
            }
            if (!$allowed) {
                if (!$evaluable) {
                    $unknown = true;
                    continue;
                }
                return false;                                             // one policy forbids: the browser does too
            }
        }
    }
    if ($unknown) {
        return null;
    }
    return true;
}

$status = pma_status();

vxost_header('VXOST, ' . db_t('title'), 'database', $status['online'] && $status['framable']);
?>

      <?php if ($status['online'] && $status['framable']): ?>

      <div class="embed-bar">
        <div class="embed-bar__inner">
          <span class="badge badge--ok"><span class="dot dot--pulse"></span> phpMyAdmin</span>
          <span class="muted mono" dir="ltr">127.0.0.1:3306</span>
          <div class="embed-bar__actions">
            <button type="button" class="btn btn--ghost btn--sm" id="pma-reload">
              <?php echo vxost_icon('refresh', 16); ?><?php echo h(db_t('reload')); ?>
            </button>
            <a class="btn btn--ghost btn--sm" href="/phpmyadmin/" target="_blank" rel="noopener">
              <?php echo vxost_icon('external', 16); ?><?php echo h(db_t('fullscreen')); ?>
            </a>
          </div>
        </div>
      </div>

      <iframe id="pma-frame" class="embed-frame" src="/phpmyadmin/"
              title="phpMyAdmin" loading="eager"></iframe>

      <script>
        document.getElementById('pma-reload').addEventListener('click', function () {
          var frame = document.getElementById('pma-frame');
          frame.src = frame.src;
        });
      </script>

      <?php else: ?>

      <section class="hero">
        <div class="row">
          <div class="large-8 columns" data-reveal>
            <p class="eyebrow"><?php echo h(db_t('eyebrow')); ?></p>
            <h1><?php echo h(db_t('title')); ?> <span><?php echo h(db_t('subtitle')); ?></span></h1>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="row">
          <div class="large-8 columns" data-reveal>
            <?php if (!$status['online']): ?>
            <div class="admonitionblock important">
              <h3 style="font-size:1.05rem"><?php echo h(db_t('offline_t')); ?></h3>
              <p style="margin-bottom:0"><?php echo h(db_t('offline_p')); ?></p>
            </div>
            <?php else: ?>
            <div class="admonitionblock warning">
              <h3 style="font-size:1.05rem"><?php echo h(db_t('blocked_t')); ?></h3>
              <?php if (!empty($status['reason'])): ?>
              <p style="margin-bottom:0"><?php echo h(db_t($status['reason'])); ?></p>
              <?php else: ?>
              <p><?php echo h(db_t('blocked_p')); ?></p>
              <pre dir="ltr">$cfg['AllowThirdPartyFraming'] = 'sameorigin';</pre>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <a class="btn btn--primary" href="/phpmyadmin/" target="_blank" rel="noopener">
              <?php echo vxost_icon('external', 18); ?><?php echo h(db_t('open')); ?>
            </a>
          </div>
        </div>
      </section>

      <?php endif; ?>

<?php vxost_footer(); ?>
