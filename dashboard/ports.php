<?php
/**
 * VXOST Dashboard v2, Porte in ascolto e indirizzi IP
 *
 * Elenca le porte TCP in ascolto sulla macchina, il processo che le occupa e,
 * quando riconoscibile, il progetto a cui appartengono (dal percorso di lavoro
 * del processo). Utile per ritrovare i dev server aperti su localhost:3000,
 * :8000, :5173 e simili.
 *
 * Sola lettura: nessun comando modifica lo stato del sistema.
 */

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

/** Etichette della pagina. */
function p_t(string $key): string
{
    static $s = [
        'en' => [
            'title' => 'Ports & IPs', 'eyebrow' => 'Network overview',
            'subtitle' => 'TCP ports currently listening on this machine, with the process and project behind each one',
            'refresh' => 'Refresh', 'open' => 'Open', 'port' => 'Port', 'process' => 'Process',
            'project' => 'Project', 'address' => 'Address', 'pid' => 'PID', 'path' => 'Working directory',
            'web' => 'Web services', 'other' => 'Other listening ports', 'ips' => 'Addresses of this machine',
            'vhosts' => 'Apache virtual hosts', 'none' => 'No listening port detected.',
            'novhost' => 'No virtual host configured in httpd-vhosts.conf.',
            'unavailable' => 'Port scanning is unavailable: the lsof command cannot be executed by PHP.',
            'hint' => 'Ports serving HTTP are clickable. The project name is derived from the process working directory.',
            'loopback' => 'Loopback', 'lan' => 'Local network', 'count' => 'listening ports',
            'auto' => 'Auto refresh every 30s',
            'howto_t' => 'How to open a new port',
            'howto_p' => 'A port is opened in two steps: Apache must listen on it, and a VirtualHost must say which folder to serve. Both files live in the VXOST configuration folder.',
            'step1' => 'Add the port to httpd.conf',
            'step2' => 'Add the VirtualHost in extra/httpd-vhosts.conf',
            'step3' => 'Restart Apache and open the address',
            'aliases' => 'localhost and 127.0.0.1 are the same machine: localhost is the name, 127.0.0.1 the loopback address it resolves to. Both reach the ports below. Use the LAN address to open the site from another device on the same network.',
            'modified' => 'Modified', 'never' => 'unknown', 'conf' => 'Configuration files',
            'disabled' => 'not active', 'nodedicated' => 'Projects without a dedicated port',
            'nodedicated_p' => 'These are served by Apache on port 80, as a subfolder of the web root. They do not need a VirtualHost: the address is enough.',
        ],
        'it' => [
            'title' => 'Porte e IP', 'eyebrow' => 'Panoramica di rete',
            'subtitle' => 'Porte TCP in ascolto su questa macchina, con il processo e il progetto che le occupa',
            'refresh' => 'Aggiorna', 'open' => 'Apri', 'port' => 'Porta', 'process' => 'Processo',
            'project' => 'Progetto', 'address' => 'Indirizzo', 'pid' => 'PID', 'path' => 'Cartella di lavoro',
            'web' => 'Servizi web', 'other' => 'Altre porte in ascolto', 'ips' => 'Indirizzi di questa macchina',
            'vhosts' => 'Virtual host Apache', 'none' => 'Nessuna porta in ascolto rilevata.',
            'novhost' => 'Nessun virtual host configurato in httpd-vhosts.conf.',
            'unavailable' => 'Scansione non disponibile: PHP non può eseguire il comando lsof.',
            'hint' => 'Le porte che servono HTTP sono cliccabili. Il nome del progetto è dedotto dalla cartella di lavoro del processo.',
            'loopback' => 'Loopback', 'lan' => 'Rete locale', 'count' => 'porte in ascolto',
            'auto' => 'Aggiornamento automatico ogni 30s',
            'howto_t' => 'Come si apre una nuova porta',
            'howto_p' => 'Aprire una porta richiede due passaggi: Apache deve mettersi in ascolto su quella porta e un VirtualHost deve indicare quale cartella servire. Entrambi i file stanno nella cartella di configurazione di VXOST.',
            'step1' => 'Aggiungi la porta in httpd.conf',
            'step2' => 'Aggiungi il VirtualHost in extra/httpd-vhosts.conf',
            'step3' => 'Riavvia Apache e apri l\'indirizzo',
            'aliases' => 'localhost e 127.0.0.1 sono la stessa macchina: localhost è il nome, 127.0.0.1 l\'indirizzo di loopback a cui viene risolto. Entrambi raggiungono le porte qui sotto. Usa invece l\'indirizzo di rete locale per aprire il sito da un altro dispositivo collegato alla stessa rete.',
            'modified' => 'Modificato', 'never' => 'sconosciuto', 'conf' => 'File di configurazione',
            'disabled' => 'non attivo', 'nodedicated' => 'Progetti senza porta dedicata',
            'nodedicated_p' => 'Sono serviti da Apache sulla porta 80, come sottocartella della radice web: non hanno bisogno di un VirtualHost, basta l\'indirizzo.',
        ],
    ];
    $lang = vxost_lang();
    return $s[$lang][$key] ?? $s['en'][$key] ?? $key;
}

/** Esegue un comando in sola lettura, restituendo l'output o null. */
function run(string $cmd): ?string
{
    if (!function_exists('shell_exec')) {
        return null;
    }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (in_array('shell_exec', $disabled, true)) {
        return null;
    }
    $out = @shell_exec($cmd . ' 2>/dev/null');
    return is_string($out) && $out !== '' ? $out : null;
}

/**
 * Comando completo e cartella di lavoro di tutti i processi, in due sole
 * chiamate di sistema (una per PID sarebbe decine di fork: troppo lento).
 *
 * @return array<int, array{command: string, cwd: string}>
 */
function process_table(array $pids = []): array
{
    static $table = null;
    if ($table !== null) {
        return $table;
    }
    $table = [];

    // Riga per riga: "<pid> <comando completo>"
    $ps = run('ps -axo pid=,command=');
    foreach (explode("\n", (string) $ps) as $line) {
        if (preg_match('/^\s*(\d+)\s+(.*)$/', $line, $m)) {
            $table[(int) $m[1]] = ['command' => trim($m[2]), 'cwd' => ''];
        }
    }

    // Cartelle di lavoro: solo per i PID che interessano, altrimenti lsof
    // dovrebbe attraversare tutti i processi della macchina (secondi di attesa).
    $pids = array_values(array_unique(array_filter($pids)));
    if ($pids) {
        $list = implode(',', array_map('intval', array_slice($pids, 0, 200)));
        $cwds = run('lsof -a -p ' . $list . ' -d cwd -Fpn -w');
        $pid = 0;
        foreach (explode("\n", (string) $cwds) as $line) {
            if ($line === '') {
                continue;
            }
            if ($line[0] === 'p') {
                $pid = (int) substr($line, 1);
            } elseif ($line[0] === 'n' && $pid > 0 && isset($table[$pid])) {
                $table[$pid]['cwd'] = substr($line, 1);
            }
        }
    }

    return $table;
}

/** Comando e cartella di lavoro di un singolo processo. */
function process_info(int $pid): array
{
    $table = process_table();
    return $table[$pid] ?? ['command' => '', 'cwd' => ''];
}

/**
 * Deduce il nome del progetto dal percorso di lavoro o dal comando:
 * la prima cartella sotto htdocs, Sites, www, Projects o simili.
 */
function guess_project(string $cwd, string $command): string
{
    $haystack = $cwd !== '' ? $cwd : $command;
    $markers = ['/htdocs/progetti/', '/htdocs/', '/Sites/', '/www/', '/Projects/', '/Progetti/', '/dev/'];

    foreach ($markers as $marker) {
        $pos = stripos($haystack, $marker);
        if ($pos !== false) {
            $rest = substr($haystack, $pos + strlen($marker));
            $name = strtok($rest, '/ ');
            // "htdocs" da solo e' la radice del server, non un progetto
            if (is_string($name) && $name !== '' && $name[0] !== '.' && $name !== 'htdocs') {
                return $name;
            }
        }
    }

    // Fallback: ultima cartella significativa del percorso di lavoro
    if ($cwd !== '' && $cwd !== '/') {
        $name = basename($cwd);
        if ($name !== '' && !in_array($name, ['/', 'root', 'bin', 'usr', 'tmp', 'var', 'htdocs'], true)) {
            return $name;
        }
    }
    return '';
}

/** Porte note dei servizi di sistema. */
function well_known(int $port): string
{
    static $map = [
        21 => 'FTP', 22 => 'SSH', 25 => 'SMTP', 53 => 'DNS', 80 => 'Apache (HTTP)',
        143 => 'IMAP', 443 => 'Apache (HTTPS)', 631 => 'CUPS', 3306 => 'MariaDB / MySQL',
        5000 => 'macOS ControlCenter', 5432 => 'PostgreSQL', 6379 => 'Redis',
        7000 => 'macOS ControlCenter', 8080 => 'HTTP alternativa', 8443 => 'HTTPS alternativa',
        9000 => 'PHP-FPM / Xdebug', 11211 => 'Memcached', 27017 => 'MongoDB',
    ];
    return $map[$port] ?? '';
}

/**
 * Elenco delle porte TCP in ascolto.
 *
 * La fonte primaria e' netstat: lsof mostra soltanto i processi dell'utente
 * che esegue PHP, quindi da solo nasconde tutto cio' che gira come root —
 * Apache compreso. lsof resta utile per arricchire le righe con il processo,
 * dove i permessi lo consentono.
 */
function listening_ports(): ?array
{
    $netstat = run('netstat -an -p tcp');
    $raw = run('lsof -nP -iTCP -sTCP:LISTEN');

    if ($netstat === null && $raw === null) {
        return null;
    }

    // Porte viste da netstat: elenco completo, senza dettaglio di processo
    $fromNetstat = [];
    foreach (explode("\n", (string) $netstat) as $line) {
        if (!str_contains($line, 'LISTEN')) {
            continue;
        }
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 3) {
            continue;
        }

        // L'indirizzo locale sta in colonne diverse a seconda del sistema:
        // BSD/macOS lo mette in posizione 3, Windows in posizione 1. Si cerca
        // la prima colonna che ha la forma indirizzo + porta.
        $m = null;
        foreach ([$cols[3] ?? '', $cols[1] ?? '', $cols[2] ?? ''] as $candidate) {
            if ($candidate !== '' && preg_match('/^(.*)[.:](\d+)$/', $candidate, $found)) {
                $m = $found;
                break;
            }
        }
        if ($m === null) {
            continue;
        }
        $port = (int) $m[2];
        $address = $m[1] === '*' ? '0.0.0.0' : $m[1];
        $fromNetstat[$port] = [
            'port'    => $port,
            'address' => $address,
            'proto'   => str_contains($cols[0], '6') ? 'IPv6' : 'IPv4',
        ];
    }

    if ($raw === null) {
        $raw = '';
    }

    // Prima passata: raccoglie i PID, cosi' le cartelle di lavoro si leggono
    // con una sola invocazione di lsof mirata.
    $pids = [];
    foreach (explode("\n", $raw) as $line) {
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) > 1 && ctype_digit($cols[1])) {
            $pids[] = (int) $cols[1];
        }
    }
    process_table($pids);

    $ports = [];
    foreach (explode("\n", $raw) as $line) {
        if ($line === '' || str_starts_with($line, 'COMMAND')) {
            continue;
        }
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 9) {
            continue;
        }

        $name = end($cols);
        if (str_ends_with($name, '(LISTEN)')) {
            $name = trim($cols[count($cols) - 2]);
        }
        if (!preg_match('/^(.*):(\d+)$/', $name, $m)) {
            continue;
        }

        $address = $m[1] === '*' ? '0.0.0.0' : $m[1];
        $port    = (int) $m[2];
        $pid     = (int) $cols[1];
        $key     = $port . '@' . $address;

        if (isset($ports[$key])) {
            continue;
        }

        $info = process_info($pid);
        $project = guess_project($info['cwd'], $info['command']);
        $path = $info['cwd'];

        // Le porte servite da Apache prendono nome e percorso dal VirtualHost:
        // la cartella di lavoro del processo httpd non dice nulla sul progetto.
        $vh = vhosts();
        if (isset($vh[$port]) && $vh[$port]['root'] !== '') {
            $project = $vh[$port]['project'] !== '' ? $vh[$port]['project'] : $project;
            $path = $vh[$port]['root'];
        }

        $ports[$key] = [
            'port'     => $port,
            'address'  => $address,
            'command'  => $cols[0],
            'pid'      => $pid,
            'user'     => $cols[2],
            'proto'    => $cols[4] === 'IPv6' ? 'IPv6' : 'IPv4',
            'cwd'      => $path,
            'full'     => $info['command'],
            'project'  => $project,
            'service'  => well_known($port),
            'modified' => $path !== '' && is_dir($path) ? (int) @filemtime($path) : 0,
        ];
    }

    // Completa con le porte che solo netstat riesce a vedere (servizi di root,
    // Apache incluso): senza dettaglio di processo, ma con il progetto ricavato
    // dai VirtualHost.
    $known = [];
    foreach ($ports as $p) {
        $known[$p['port']] = true;
    }

    $vh = vhosts();
    foreach ($fromNetstat as $port => $info) {
        if (isset($known[$port])) {
            continue;
        }
        $root = $vh[$port]['root'] ?? '';
        $ports['n' . $port] = [
            'port'     => $port,
            'address'  => $info['address'],
            'command'  => isset($vh[$port]) ? 'httpd' : '',
            'pid'      => 0,
            'user'     => '',
            'proto'    => $info['proto'],
            'cwd'      => $root,
            'full'     => '',
            'project'  => $vh[$port]['project'] ?? '',
            'service'  => well_known($port),
            'modified' => $root !== '' && is_dir($root) ? (int) @filemtime($root) : 0,
        ];
    }

    uasort($ports, static fn(array $a, array $b): int => $a['port'] <=> $b['port']);
    return $ports;
}

/**
 * Progetti raggiungibili senza una porta dedicata, cioe' serviti da Apache
 * sulla porta 80 come sottocartella di htdocs/progetti.
 *
 * @param array<int, array{project: string}> $vh virtual host gia' letti
 * @return array<int, array{name: string, url: string, modified: int}>
 */
function path_served_projects(array $vh): array
{
    $base = vxost_env()['projects'];
    if (!is_dir($base)) {
        return [];
    }

    // Nomi gia' coperti da un VirtualHost: non vanno ripetuti
    $withPort = [];
    foreach ($vh as $entry) {
        if ($entry['project'] !== '') {
            $withPort[strtolower($entry['project'])] = true;
        }
    }

    $out = [];
    foreach (scandir($base) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }
        $dir = $base . '/' . $entry;
        if (!is_dir($dir) || isset($withPort[strtolower($entry)])) {
            continue;
        }
        $out[] = [
            'name'     => $entry,
            'url'      => '/progetti/' . rawurlencode($entry) . '/',
            'modified' => (int) @filemtime($dir),
        ];
    }

    usort($out, static fn(array $a, array $b): int => $b['modified'] <=> $a['modified']);
    return $out;
}

/**
 * Verifica in parallelo quali porte rispondono a una richiesta HTTP.
 * Un controllo sequenziale su decine di porte costerebbe secondi: qui i socket
 * sono non bloccanti e vengono attesi tutti insieme.
 *
 * @param  int[] $ports
 * @return array<int, bool>
 */
function http_ports(array $ports): array
{
    $result = array_fill_keys($ports, false);
    $sockets = [];

    foreach ($ports as $port) {
        $sock = @stream_socket_client(
            'tcp://127.0.0.1:' . $port,
            $errno,
            $errstr,
            1,
            STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT
        );
        if ($sock) {
            stream_set_blocking($sock, false);
            $sockets[$port] = $sock;
        }
    }
    if (!$sockets) {
        return $result;
    }

    $request = "HEAD / HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n";
    $pending = $sockets;   // in attesa di connessione + invio
    $waiting = [];         // richiesta inviata, in attesa di risposta
    $deadline = microtime(true) + 1.8;   // un progetto pesante puo' rispondere in ~1s

    while (($pending || $waiting) && microtime(true) < $deadline) {
        // 1. Socket appena connessi: invia la richiesta
        if ($pending) {
            $write = $pending;
            $read = $except = null;
            if (@stream_select($read, $write, $except, 0, 150000) > 0) {
                foreach ($write as $sock) {
                    $port = array_search($sock, $sockets, true);
                    if (@fwrite($sock, $request) !== false) {
                        $waiting[$port] = $sock;
                    } else {
                        @fclose($sock);
                    }
                    unset($pending[$port]);
                }
            }
        }

        // 2. Socket con risposta pronta: leggi solo quelli segnalati
        if ($waiting) {
            $read = $waiting;
            $write = $except = null;
            if (@stream_select($read, $write, $except, 0, 150000) > 0) {
                foreach ($read as $sock) {
                    $port = array_search($sock, $sockets, true);
                    $head = (string) @fread($sock, 16);
                    $result[$port] = str_starts_with($head, 'HTTP/');
                    @fclose($sock);
                    unset($waiting[$port]);
                }
            }
        }
    }

    foreach (array_merge($pending, $waiting) as $sock) {
        @fclose($sock);
    }

    return $result;
}

/** Indirizzi IP della macchina. */
function local_ips(): array
{
    $ips = ['loopback' => ['127.0.0.1', '::1'], 'lan' => []];

    if (function_exists('net_get_interfaces')) {
        $interfaces = @net_get_interfaces() ?: [];
        foreach ($interfaces as $iface => $data) {
            foreach ($data['unicast'] ?? [] as $unicast) {
                $addr = $unicast['address'] ?? '';
                if ($addr === '' || str_starts_with($addr, '127.') || str_starts_with($addr, 'fe80')
                    || $addr === '::1' || str_contains($addr, ':')) {
                    continue;
                }
                $ips['lan'][$addr] = $iface;
            }
        }
    }
    return $ips;
}

/**
 * Virtual host attivi, indicizzati per porta.
 * I blocchi commentati (#) vengono ignorati: Apache non li carica.
 *
 * @return array<int, array{port:int, name:string, root:string, project:string}>
 */
function vhosts(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $out = [];

    $file = vxost_env()['vhosts'];
    if (!is_readable($file)) {
        return $out;
    }

    // I blocchi commentati restano nel file ma Apache non li carica: li si
    // legge comunque, per poterli mostrare come "configurato ma non attivo".
    $active = [];
    $disabled = [];
    foreach (explode("\n", (string) file_get_contents($file)) as $line) {
        if (str_starts_with(ltrim($line), '#')) {
            $disabled[] = ltrim(ltrim($line), '# ');
        } else {
            $active[] = $line;
        }
    }
    $conf = implode("\n", $active);
    $off = implode("\n", $disabled);

    if (preg_match_all('/<VirtualHost\s+([^>]+)>(.*?)<\/VirtualHost>/is', $conf, $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $block) {
            preg_match('/ServerName\s+(\S+)/i', $block[2], $name);
            preg_match('/DocumentRoot\s+"?([^"\n]+?)"?\s*$/im', $block[2], $root);
            $port = 80;
            if (preg_match('/:(\d+)\s*$/', trim($block[1]), $bind)) {
                $port = (int) $bind[1];
            }

            $docroot = trim($root[1] ?? '');
            $out[$port] = [
                'port'     => $port,
                'name'     => $name[1] ?? 'localhost',
                'root'     => $docroot,
                'project'  => guess_project($docroot, ''),
                'modified' => is_dir($docroot) ? (int) @filemtime($docroot) : 0,
                'enabled'  => true,
            ];
        }
    }

    // Stessi dati per i blocchi commentati, marcati come non attivi
    if (preg_match_all('/<VirtualHost\s+([^>]+)>(.*?)<\/VirtualHost>/is', $off, $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $block) {
            preg_match('/ServerName\s+(\S+)/i', $block[2], $name);
            preg_match('/DocumentRoot\s+"?([^"\n]+?)"?\s*$/im', $block[2], $root);
            $port = 0;
            if (preg_match('/:(\d+)\s*$/', trim($block[1]), $bind)) {
                $port = (int) $bind[1];
            }
            if ($port === 0 || isset($out[$port])) {
                continue;
            }

            $docroot = trim($root[1] ?? '');
            $out[$port] = [
                'port'     => $port,
                'name'     => $name[1] ?? 'localhost',
                'root'     => $docroot,
                'project'  => guess_project($docroot, ''),
                'modified' => is_dir($docroot) ? (int) @filemtime($docroot) : 0,
                'enabled'  => false,
            ];
        }
    }

    ksort($out);
    return $out;
}

$ports = listening_ports();
$ips   = local_ips();
$vh    = vhosts();

// Separa i servizi web (raggiungibili via HTTP) dal resto
$web = $other = [];
if ($ports !== null) {
    $candidates = [];
    foreach ($ports as $p) {
        if ($p['port'] >= 1024 || in_array($p['port'], [80, 443], true)) {
            $candidates[$p['port']] = $p['port'];
        }
    }
    $isHttp = http_ports(array_values($candidates));
    $vhPorts = vhosts();

    foreach ($ports as $p) {
        // Un VirtualHost e' per definizione un servizio web: non dipende dal probe
        if (!empty($isHttp[$p['port']]) || isset($vhPorts[$p['port']])) {
            $web[] = $p;
        } else {
            $other[] = $p;
        }
    }
}

vxost_header('VXOST, ' . p_t('title'), 'ports');
?>

      <section class="hero">
        <div class="row">
          <div class="large-9 columns" data-reveal>
            <p class="eyebrow"><?php echo h(p_t('eyebrow')); ?></p>
            <h1><?php echo h(p_t('title')); ?> <span><?php echo h(p_t('subtitle')); ?></span></h1>
            <div class="hero-actions">
              <a class="btn btn--primary" href="?ts=<?php echo time(); ?>">
                <?php echo vxost_icon('refresh'); ?><?php echo h(p_t('refresh')); ?>
              </a>
              <span class="badge badge--ok">
                <span class="dot dot--pulse"></span>
                <?php echo $ports === null ? '—' : count($ports); ?> <?php echo h(p_t('count')); ?>
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- INDIRIZZI -->
      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow"><?php echo h(p_t('ips')); ?></p>
                <h2><?php echo h(p_t('address')); ?></h2>
              </div>
            </div>
            <p class="muted" style="max-width:80ch"><?php echo h(p_t('aliases')); ?></p>
            <div class="status-grid">
              <?php foreach ($ips['loopback'] as $ip): ?>
              <div class="stat">
                <span class="dot" style="color:var(--cyan)"></span>
                <span>
                  <span class="stat-label"><?php echo h(p_t('loopback')); ?></span><br>
                  <span class="stat-value mono"><?php echo h($ip); ?></span>
                </span>
              </div>
              <?php endforeach; ?>
              <?php foreach ($ips['lan'] as $ip => $iface): ?>
              <div class="stat">
                <span class="dot dot--pulse" style="color:var(--accent)"></span>
                <span>
                  <span class="stat-label"><?php echo h(p_t('lan')); ?> · <?php echo h($iface); ?></span><br>
                  <span class="stat-value mono"><?php echo h($ip); ?></span>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <?php if ($ports === null): ?>
      <section class="section">
        <div class="row">
          <div class="large-8 columns">
            <div class="admonitionblock warning">
              <p style="margin:0"><?php echo h(p_t('unavailable')); ?></p>
            </div>
          </div>
        </div>
      </section>
      <?php else: ?>

      <!-- SERVIZI WEB -->
      <section class="section">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow"><?php echo h(p_t('web')); ?></p>
                <h2>HTTP</h2>
              </div>
              <p class="muted"><?php echo h(p_t('hint')); ?></p>
            </div>

            <?php if (!$web): ?>
              <p class="muted"><?php echo h(p_t('none')); ?></p>
            <?php else: ?>
            <div class="bento bento--2">
              <?php foreach ($web as $p): ?>
              <a class="card port-card" href="http://localhost:<?php echo (int) $p['port']; ?>/" target="_blank" rel="noopener">
                <span class="port-number mono"><?php echo (int) $p['port']; ?></span>
                <div class="port-body">
                  <h3><?php echo h($p['project'] !== '' ? $p['project'] : ($p['service'] !== '' ? $p['service'] : $p['command'])); ?></h3>
                  <p class="mono" dir="ltr">
                    <?php echo h($p['command']); ?> · PID <?php echo (int) $p['pid']; ?>
                    <?php if ($p['modified']): ?>
                    · <?php echo h(p_t('modified')); ?> <?php echo date('d/m/Y H:i', $p['modified']); ?>
                    <?php endif; ?>
                  </p>
                  <?php if ($p['cwd'] !== ''): ?>
                  <p class="mono port-path" dir="ltr" title="<?php echo h($p['cwd']); ?>"><?php echo h($p['cwd']); ?></p>
                  <?php endif; ?>
                </div>
                <span class="card-link"><?php echo h(p_t('open')); ?> <?php echo vxost_icon('external', 16); ?></span>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- ALTRE PORTE -->
      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow"><?php echo h(p_t('other')); ?></p>
                <h2>TCP</h2>
              </div>
            </div>
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th><?php echo h(p_t('port')); ?></th>
                    <th><?php echo h(p_t('address')); ?></th>
                    <th><?php echo h(p_t('process')); ?></th>
                    <th><?php echo h(p_t('pid')); ?></th>
                    <th><?php echo h(p_t('project')); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($other as $p): ?>
                  <tr>
                    <td class="mono"><strong><?php echo (int) $p['port']; ?></strong></td>
                    <td class="mono" dir="ltr"><?php echo h($p['address']); ?> <span class="muted"><?php echo h($p['proto']); ?></span></td>
                    <td class="mono" dir="ltr"><?php echo h($p['command']); ?></td>
                    <td class="mono"><?php echo (int) $p['pid']; ?></td>
                    <td><?php echo h($p['service'] !== '' ? $p['service'] : $p['project']); ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- VIRTUAL HOST -->
      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow"><?php echo h(p_t('vhosts')); ?></p>
                <h2>Apache</h2>
              </div>
            </div>
            <?php if (!$vh): ?>
              <p class="muted"><?php echo h(p_t('novhost')); ?></p>
            <?php else: ?>
            <div class="bento">
              <?php foreach ($vh as $v): ?>
              <a class="card<?php echo $v['enabled'] ? '' : ' card--off'; ?>"
                 href="http://<?php echo h($v['name']); ?>:<?php echo (int) $v['port']; ?>/" target="_blank" rel="noopener">
                <span class="card-icon card-icon--violet"><?php echo vxost_icon('globe', 22); ?></span>
                <h3><?php echo h($v['project'] !== '' ? $v['project'] : $v['name']); ?>
                  <?php if (!$v['enabled']): ?><span class="badge badge--warn"><?php echo h(p_t('disabled')); ?></span><?php endif; ?>
                </h3>
                <p class="mono" dir="ltr"><?php echo h($v['name']); ?>:<?php echo (int) $v['port']; ?></p>
                <p class="mono port-path" dir="ltr" title="<?php echo h($v['root']); ?>"><?php echo h($v['root']); ?></p>
                <?php if ($v['modified']): ?>
                <p class="muted" style="font-size:.78rem"><?php echo h(p_t('modified')); ?> <?php echo date('d/m/Y H:i', $v['modified']); ?></p>
                <?php endif; ?>
                <span class="card-link"><?php echo h(p_t('open')); ?> <?php echo vxost_icon('external', 16); ?></span>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- PROGETTI SENZA PORTA DEDICATA -->
      <?php $pathProjects = path_served_projects($vh); if ($pathProjects): ?>
      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow"><?php echo h(p_t('nodedicated')); ?></p>
                <h2><span class="mono" dir="ltr">localhost/progetti/…</span></h2>
              </div>
              <p class="muted"><?php echo count($pathProjects); ?></p>
            </div>
            <p class="muted" style="max-width:80ch"><?php echo h(p_t('nodedicated_p')); ?></p>

            <div class="bento howto-grid">
              <?php foreach ($pathProjects as $p): ?>
              <a class="card" href="<?php echo h($p['url']); ?>" target="_blank" rel="noopener">
                <span class="card-icon"><?php echo vxost_icon('folder', 20); ?></span>
                <h3><?php echo h($p['name']); ?></h3>
                <?php if ($p['modified']): ?>
                <span class="badge"><?php echo date('d/m/Y', $p['modified']); ?></span>
                <?php endif; ?>
                <span class="card-link"><?php echo vxost_icon('external', 16); ?></span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- COME SI APRE UNA PORTA -->
      <section class="section">
        <div class="row">
          <div class="large-8 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow"><?php echo h(p_t('conf')); ?></p>
                <h2><?php echo h(p_t('howto_t')); ?></h2>
              </div>
            </div>
            <p><?php echo h(p_t('howto_p')); ?></p>

            <ol class="steps">
              <li>
                <strong><?php echo h(p_t('step1')); ?></strong>
                <p class="mono muted" dir="ltr"><?php echo h(vxost_env()['httpd']); ?></p>
                <pre dir="ltr">Listen 4010</pre>
              </li>
              <li>
                <strong><?php echo h(p_t('step2')); ?></strong>
                <p class="mono muted" dir="ltr"><?php echo h(vxost_env()['vhosts']); ?></p>
                <pre dir="ltr">&lt;VirtualHost *:4010&gt;
    DocumentRoot "<?php echo h(vxost_env()['projects']); ?>/nome-progetto"
    ServerName localhost
    &lt;Directory "<?php echo h(vxost_env()['projects']); ?>/nome-progetto"&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;</pre>
              </li>
              <li>
                <strong><?php echo h(p_t('step3')); ?></strong>
                <pre dir="ltr"><?php echo h(vxost_env()['restart']); ?>

http://localhost:4010   →   http://127.0.0.1:4010<?php
                foreach (array_keys($ips['lan']) as $lanIp) {
                    echo "\n" . str_pad('', 24) . '→   http://' . h($lanIp) . ':4010';
                    break;
                }
?></pre>
              </li>
            </ol>
          </div>

          <div class="large-4 columns" data-reveal data-reveal-delay="100">
            <div class="card">
              <span class="card-icon card-icon--cyan"><?php echo vxost_icon('ports', 22); ?></span>
              <h3><?php echo h(p_t('conf')); ?></h3>
              <?php
              $files = [vxost_env()['httpd'], vxost_env()['vhosts']];
              foreach ($files as $file):
                  $time = is_readable($file) ? (int) @filemtime($file) : 0;
              ?>
              <p class="mono" style="font-size:.78rem;margin:0" dir="ltr"><?php echo h(basename($file)); ?></p>
              <p class="muted" style="font-size:.75rem;margin:0 0 var(--s-2)">
                <?php echo $time ? h(p_t('modified')) . ' ' . date('d/m/Y H:i', $time) : h(p_t('never')); ?>
              </p>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

<?php vxost_footer(); ?>
