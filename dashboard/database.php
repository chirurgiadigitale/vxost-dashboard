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
            'blocked_xfo' => 'phpMyAdmin is answering with X-Frame-Options, so it refuses to be shown inside a frame. Add this line to phpmyadmin/config.inc.php and reload the page:',
            'open' => 'Open phpMyAdmin directly',
            'csp_blocked' => 'phpMyAdmin is answering with a Content-Security-Policy whose frame-ancestors does not list this page, so the browser refuses to show it inside a frame. Add this line to phpmyadmin/config.inc.php and reload the page:',
            'csp_unknown' => 'phpMyAdmin sends a Content-Security-Policy with a frame-ancestors this page cannot evaluate: it may or may not allow the frame. Open it directly, or read the header with curl -I.',
            'cert_expired' => 'The certificate in etc/ssl.crt is the right one but it is not valid today (expired, or not yet valid): the browser will refuse the https frame. Regenerate it, as an administrator, with sudo /Applications/VXOST/vxostfiles/bin/vxost-ssl-init, then restart Apache.',
            'offline_t' => 'phpMyAdmin is not reachable',
            'offline_p' => 'The server did not answer on /phpmyadmin/. Check that Apache is running and that phpMyAdmin is installed.',
        ],
        'it' => [
            'title' => 'Database', 'eyebrow' => 'MariaDB / MySQL',
            'subtitle' => 'phpMyAdmin all\'interno della dashboard',
            'fullscreen' => 'Apri a schermo intero', 'reload' => 'Ricarica',
            'blocked_t' => 'phpMyAdmin non può essere incorporato',
            'blocked_xfo' => 'phpMyAdmin risponde con X-Frame-Options e rifiuta di essere mostrato dentro un frame. Aggiungi questa riga a phpmyadmin/config.inc.php e ricarica la pagina:',
            'open' => 'Apri phpMyAdmin direttamente',
            'csp_blocked' => 'phpMyAdmin risponde con una Content-Security-Policy il cui frame-ancestors non comprende questa pagina, quindi il browser rifiuta di mostrarlo dentro un frame. Aggiungi questa riga a phpmyadmin/config.inc.php e ricarica la pagina:',
            'csp_unknown' => 'phpMyAdmin manda una Content-Security-Policy con un frame-ancestors che questa pagina non sa valutare: potrebbe consentire il frame o no. Aprilo direttamente, o leggi l\'header con curl -I.',
            'cert_expired' => 'Il certificato in etc/ssl.crt è quello giusto ma oggi non è valido (scaduto, o non ancora valido): il browser rifiuterà il frame https. Rigeneralo da amministratore con sudo /Applications/VXOST/vxostfiles/bin/vxost-ssl-init, poi riavvia Apache.',
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

    // ⚠️ I nomi degli header non hanno una grafia sola. Cercarne due
    // (X-Frame-Options e x-frame-options) lasciava passare qualunque altra
    // capitalizzazione: con "CoNtEnT-SeCuRiTy-PoLiCy" la policy spariva e la
    // pagina dichiarava il frame consentito. Il confronto va fatto su tutte
    // le chiavi, senza distinzione fra maiuscole e minuscole.
    $xfoValues = pma_header_values($headers, 'X-Frame-Options');
    $xfoBlocks = false;
    foreach ($xfoValues as $value) {
        if (strtoupper(trim($value)) !== 'SAMEORIGIN') {
            $xfoBlocks = true;
        }
    }

    // Una Content-Security-Policy con frame-ancestors SOSTITUISCE
    // X-Frame-Options in ogni browser attuale: quando c'e', decide lei, e il
    // verdetto di XFO non deve sopravviverle in nessuno dei due sensi.
    // Ogni header di policy vale (il browser li applica tutti, e il frame
    // passa solo se ognuno lo consente); le sorgenti si confrontano con
    // l'origine di QUESTA pagina, che e' dove il frame vive.
    $reason = '';
    $pageHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? $host)));
    $verdict = pma_csp_frame_verdict(
        pma_header_values($headers, 'Content-Security-Policy'),
        $https ? 'https' : 'http',
        $pageHost,
        $port
    );

    switch ($verdict) {
        case 'absent':                       // nessun frame-ancestors: decide XFO
            $framable = !$xfoBlocks;
            if ($xfoBlocks) {
                $reason = 'blocked_xfo';
            }
            break;
        case 'allow':
            $framable = true;
            break;
        case 'block':
            // ⚠️ Qui il motivo va detto. Finche' restava vuoto, la pagina
            // ripiegava sul testo di XFO e affermava che phpMyAdmin aveva
            // mandato "X-Frame-Options: DENY" anche quando l'header ricevuto
            // era soltanto una CSP: una falsita' visibile in pagina.
            $framable = false;
            $reason = 'csp_blocked';
            break;
        default:                             // 'unknown'
            $framable = false;
            $reason = 'csp_unknown';
            break;
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
 * Tutti i valori di un header, qualunque sia la grafia del nome.
 *
 * @return list<string>
 */
function pma_header_values(array $headers, string $name): array
{
    $out = [];
    $cercato = strtolower($name);
    foreach ($headers as $key => $value) {
        if (!is_string($key) || strtolower($key) !== $cercato) {
            continue;
        }
        foreach ((array) $value as $v) {
            $out[] = (string) $v;
        }
    }
    return $out;
}

/**
 * Lo schema scritto nella policy copre quello della pagina?
 *
 * ⚠️ Non e' un confronto di uguaglianza. La specifica ammette l'upgrade: una
 * sorgente "http:" vale anche per una pagina https, perche' salire di
 * sicurezza non e' un permesso in piu'. Il contrario no. Confrontando le due
 * stringhe, "frame-ancestors http:" su una pagina https risultava bloccato e
 * la dashboard mandava a cercare un guasto che non c'era.
 */
function pma_schema_combacia(string $sorgente, string $pagina): bool
{
    if ($sorgente === $pagina) {
        return true;
    }
    // Gli abbinamenti della specifica, tutti e quattro. ⚠️ Ne mancavano tre:
    // una policy con ws: o wss: faceva dire "bloccato" a una pagina che il
    // browser incornicia, e la dashboard mandava a cercare un guasto
    // inesistente.
    $sale = [
        'http' => ['https'],
        'ws'   => ['wss', 'http', 'https'],
        'wss'  => ['https'],
    ];
    return in_array($pagina, $sale[$sorgente] ?? [], true);
}

/**
 * Il verdetto di frame-ancestors, letto come lo legge un browser.
 *
 * 'absent'   nessuna policy contiene la direttiva: decide X-Frame-Options;
 * 'allow'    ogni policy consente a questa pagina di incorniciare;
 * 'block'    almeno una la vieta;
 * 'unknown'  una policy ha sorgenti che qui non si sanno valutare.
 *
 * Distinguere 'absent' da 'allow' non e' pedanteria: nel primo caso XFO
 * conta ancora, nel secondo no. Confonderli e' il motivo per cui una CSP
 * permissiva non riusciva a scavalcare un X-Frame-Options: DENY.
 *
 * Sono comprese solo le forme di sorgente che contano per una dashboard
 * locale: 'none', 'self', *, uno schema, un host con schema, porta e
 * asterisco iniziale facoltativi. Tutto il resto rende la direttiva non
 * valutabile invece che silenziosamente permissiva.
 *
 * ⚠️ Un asterisco non e' un si' universale: https://*.example.org ammette i
 * sottodomini di example.org, non virtualhost.
 */
function pma_csp_frame_verdict(array $policies, string $scheme, string $host, int $port): string
{
    $defaultPort = $scheme === 'https' ? 443 : 80;
    $present = false;
    $unknown = false;

    // ⚠️ Un solo header puo' contenere PIU' policy, separate da virgola: e' la
    // forma in cui due header identici si uniscono lungo la strada. Dividendo
    // solo sui punti e virgola,
    //
    //   frame-ancestors *; default-src 'self', frame-ancestors 'none'
    //
    // veniva letto come una policy sola e la pagina diceva "si incornicia"
    // dove il browser blocca. Prima si separano le policy, poi le direttive.
    $singole = [];
    foreach ($policies as $policy) {
        foreach (explode(',', (string) $policy) as $pezzo) {
            if (trim($pezzo) !== '') {
                $singole[] = $pezzo;
            }
        }
    }

    foreach ($singole as $policy) {
        // ⚠️ Dentro UNA policy vale la PRIMA occorrenza della direttiva: il
        // browser ignora i duplicati successivi. Scorrendole tutte,
        // "frame-ancestors 'self'; frame-ancestors 'none'" veniva letto come
        // un blocco, mentre il browser incornicia.
        $tokens = null;
        foreach (explode(';', (string) $policy) as $directive) {
            $parti = preg_split('/\s+/', trim($directive), -1, PREG_SPLIT_NO_EMPTY);
            // Confronto sul nome intero: con un prefisso, una direttiva
            // inventata che cominci per frame-ancestors verrebbe applicata.
            if (!$parti || strtolower($parti[0]) !== 'frame-ancestors') {
                continue;
            }
            $tokens = array_slice($parti, 1);
            break;
        }
        if ($tokens === null) {
            continue;
        }
        $present = true;

        // 'none' vale solo da sola: la grammatica della CSP non ammette di
        // affiancarla ad altre sorgenti, e il browser la scarta come token
        // sconosciuto. "frame-ancestors 'none' 'self'" consente, non vieta.
        // Una lista vuota non ammette nessuno.
        if ($tokens === []) {
            return 'block';
        }
        if (count($tokens) === 1 && strtolower($tokens[0]) === "'none'") {
            return 'block';
        }

        $allowed = false;
        $evaluable = true;
        foreach ($tokens as $token) {
            $t = strtolower($token);
            if ($t === "'none'") {
                continue;                                            // scartata
            }
            // 'self' e' l'origine di phpMyAdmin, che e' la stessa di questa
            // pagina: stesso Apache, stesso host, stessa porta.
            if ($t === "'self'" || $t === '*') {
                $allowed = true;
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9+.-]*:$/', $t)) {          // scheme-source
                if (pma_schema_combacia(rtrim($t, ':'), $scheme)) {
                    $allowed = true;
                }
                continue;
            }
            if (preg_match('#^(?:([a-z][a-z0-9+.-]*)://)?(\*\.)?([a-z0-9.-]+)(?::(\d+|\*))?$#', $t, $m)) {
                $schemeOk = $m[1] === '' || pma_schema_combacia($m[1], $scheme);
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
            $evaluable = false;                                      // nonce, hash, altro
        }

        if ($allowed) {
            continue;                                                // questa policy consente
        }
        if (!$evaluable) {
            $unknown = true;
            continue;
        }
        return 'block';                                              // una vieta: il browser vieta
    }

    if (!$present) {
        return 'absent';
    }
    return $unknown ? 'unknown' : 'allow';
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
              <?php
              // ⚠️ Nessun ripiego. Prima, un motivo vuoto faceva stampare il
              // testo di X-Frame-Options: la pagina affermava un header che
              // poteva non essere mai arrivato. Ora il motivo c'e' sempre, e
              // la riga di configurazione compare solo dove e' davvero il
              // rimedio, cioe' quando e' phpMyAdmin a rifiutare il frame.
              $motivo = $status['reason'] !== '' ? $status['reason'] : 'blocked_xfo';
              ?>
              <p<?php echo in_array($motivo, ['blocked_xfo', 'csp_blocked'], true) ? '' : ' style="margin-bottom:0"'; ?>><?php echo h(db_t($motivo)); ?></p>
              <?php if (in_array($motivo, ['blocked_xfo', 'csp_blocked'], true)): ?>
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
