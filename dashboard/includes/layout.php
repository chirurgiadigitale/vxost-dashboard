<?php
/**
 * VXOST Dashboard v2, shared layout for the PHP pages
 *
 * Exposes vxost_header() and vxost_footer() so that the dashboard, the
 * projects page, the ports page and phpMyAdmin all carry the same header and
 * footer. Strings come in English and Italian; the language is picked from
 * ?lang=, from the cookie, or from the Accept-Language header.
 */

declare(strict_types=1);

/**
 * Percorsi e comandi dell'installazione VXOST, rilevati a runtime.
 *
 * The root is worked out by walking up from this file
 * (www/dashboard/includes),
 * quindi funziona ovunque VXOST sia installato:
 *   macOS   /Applications/VXOST/vxostfiles
 *   Linux   /opt/lampp
 *   Windows C:\vxost
 *
 * @return array{os: string, root: string, www: string, projects: string,
 *               httpd: string, vhosts: string, restart: string, security: string}
 */
function vxost_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $root = dirname(__DIR__, 3);              // .../includes -> dashboard -> www -> radice
    $windows = DIRECTORY_SEPARATOR === '\\' || stripos(PHP_OS_FAMILY, 'win') === 0;
    $os = $windows ? 'windows' : (PHP_OS_FAMILY === 'Darwin' ? 'macos' : 'linux');

    // Su Windows i file di Apache stanno sotto apache\conf, altrove sotto etc
    $confDir = $windows ? $root . '/apache/conf' : $root . '/etc';

    $restart = match ($os) {
        'windows' => 'Riavvia Apache dal Control Panel di VXOST',
        'linux'   => 'sudo ' . $root . '/lampp restartapache',
        default   => 'sudo ' . $root . '/vxost restartapache',
    };
    $security = match ($os) {
        'windows' => 'Imposta le password dal Control Panel di VXOST',
        'linux'   => 'sudo ' . $root . '/lampp security',
        default   => 'sudo ' . $root . '/vxost security',
    };

    return $env = [
        'os'       => $os,
        'root'     => $root,
        // La radice web si chiama www dal 14/08: la chiave si chiamava htdocs
        // e puntava a una cartella che non esiste piu'. Nessuno la legge, per
        // questo non si era rotto niente, ma il primo che la usa la trova giusta.
        'www'      => $root . '/www',
        'projects' => $root . '/www/projects',
        'httpd'    => $confDir . '/httpd.conf',
        'vhosts'   => $confDir . '/extra/httpd-vhosts.conf',
        'restart'  => $restart,
        'security' => $security,
    ];
}

/** Strings for the tool pages. Any other language falls back to English. */
function vxost_strings(): array
{
    static $all = [
        'en' => [
            'skip' => 'Skip to content', 'dash' => 'Dashboard', 'faq' => 'FAQs',
            'howto' => 'HOW-TO Guides', 'projects' => 'Projects', 'ports' => 'Ports & IPs',
            'database' => 'Database', 'nav' => 'Navigation', 'project' => 'Project',
            'top' => 'Back to top',
            'license' => 'Equipe Digitale, VXOST is released under the GNU General Public License.',
        ],
        'it' => [
            'skip' => 'Vai al contenuto', 'dash' => 'Dashboard', 'faq' => 'Domande frequenti',
            'howto' => 'Guide HOW-TO', 'projects' => 'Progetti', 'ports' => 'Porte e IP',
            'database' => 'Database', 'nav' => 'Navigazione', 'project' => 'Progetto',
            'top' => 'Torna su',
            'license' => 'Equipe Digitale, VXOST è distribuito con licenza GNU General Public License.',
        ],
    ];
    return $all;
}

/** Lingua attiva: parametro esplicito, poi cookie, poi browser, poi inglese. */
function vxost_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $available = array_keys(vxost_strings());

    if (isset($_GET['lang']) && in_array($_GET['lang'], $available, true)) {
        $lang = $_GET['lang'];
        setcookie('vxost_lang', $lang, time() + 31536000, '/');
        return $lang;
    }
    if (isset($_COOKIE['vxost_lang']) && in_array($_COOKIE['vxost_lang'], $available, true)) {
        return $lang = $_COOKIE['vxost_lang'];
    }

    $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    foreach ($available as $code) {
        if ($code !== 'en' && str_contains($accept, $code)) {
            return $lang = $code;
        }
    }
    return $lang = 'en';
}

/** Looks a key up, falling back to English. */
function t(string $key): string
{
    $all = vxost_strings();
    $lang = vxost_lang();
    return $all[$lang][$key] ?? $all['en'][$key] ?? $key;
}

/** Escape HTML, sempre. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Path prefix for the translated dashboard pages. */
function vxost_base(): string
{
    return vxost_lang() === 'it' ? '/dashboard/it/' : '/dashboard/';
}

/** Icone SVG condivise (stroke 1.6, viewBox 24). */
function vxost_icon(string $name, int $size = 18): string
{
    static $paths = [
        'home'     => '<path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z"/><path d="M9.5 20.5v-6h5v6"/>',
        'folder'   => '<path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h3.2c.6 0 1.1.3 1.5.7l1 1.3h7.3A2.5 2.5 0 0 1 21 10.5v7A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5z"/>',
        'database' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/>',
        'faq'      => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 1 1 4.2 2.7c-.8.4-1.3 1.1-1.3 2M12 17h.01"/>',
        'guide'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H19v3H6.5A2.5 2.5 0 0 1 4 20.5z"/><path d="M8 7.5h7M8 11h5"/>',
        'ports'    => '<rect x="3" y="8" width="18" height="12" rx="2.5"/><path d="M7 8V5.5M12 8V4M17 8V5.5M7.5 14h.01M12 14h.01M16.5 14h.01"/>',
        'external' => '<path d="M14 4h6v6M20 4l-8.5 8.5"/><path d="M18 14v4.5A1.5 1.5 0 0 1 16.5 20h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>',
        'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'wp'       => '<circle cx="12" cy="12" r="9"/><path d="M4 8.5h5M14 8.5h6M9.5 8.5 12.5 19l3-7.5M6 8.5 9.5 19l2-5"/>',
        'laravel'  => '<path d="M3 7.5 8 5l5 2.5v5L8 15l-5-2.5z"/><path d="m13 12.5 4-2 4 2v4l-4 2-4-2z"/>',
        'node'     => '<path d="M12 3 4.5 7.2v9.6L12 21l7.5-4.2V7.2z"/><path d="M9.5 14.5c0 1 .8 1.6 2.2 1.6 1.5 0 2.4-.6 2.4-1.7 0-2.2-4.4-1-4.4-3 0-1 .9-1.6 2.2-1.6 1.3 0 2.1.5 2.2 1.5"/>',
        'code'     => '<path d="m9 8-5 4 5 4M15 8l5 4-5 4"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3.5 9h17M3.5 15h17M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
        'refresh'  => '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 4v5h-5"/>',
        'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];

    $d = $paths[$name] ?? $paths['info'];
    return sprintf(
        '<svg viewBox="0 0 24 24" width="%d" height="%d" fill="none" stroke="currentColor" '
        . 'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" '
        . 'focusable="false">%s</svg>',
        $size,
        $size,
        $d
    );
}

/**
 * Intestazione completa: <head>, header sticky e apertura di <main>.
 *
 * @param string $title   page title
 * @param string $active  voce di menu attiva: dashboard|faq|howto|projects|ports|database|phpinfo
 * @param bool   $full    true for the full-height layout (database page)
 */
function vxost_header(string $title, string $active = '', bool $full = false): void
{
    $lang = vxost_lang();
    $base = vxost_base();
    $uid  = 'lay' . substr(md5($title), 0, 6);

    // Same order as the static pages: the navigation never moves
    $items = [
        'dashboard' => [$base . 'index.html',      t('dash'),     'home'],
        'projects'  => ['/projects/',              t('projects'), 'folder'],
        'database'  => ['/dashboard/database.php', t('database'), 'database'],
        'ports'     => ['/dashboard/ports.php',    t('ports'),    'ports'],
        'phpinfo'   => ['/dashboard/phpinfo.php',  'PHPInfo',     'info'],
        'faq'       => [$base . 'faq.html',        t('faq'),      'faq'],
        'howto'     => [$base . 'how-to-guides.html',      t('howto'),    'guide'],
    ];
    ?>
<!doctype html>
<html lang="<?php echo h($lang); ?>">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title><?php echo h($title); ?></title>
    <meta name="robots" content="noindex, nofollow" />

    <link href="/dashboard/stylesheets/normalize.css" rel="stylesheet" type="text/css" />
    <link href="/dashboard/stylesheets/all.css" rel="stylesheet" type="text/css" />
    <meta name="color-scheme" content="dark light" />
    <meta name="theme-color" content="#070B16" />
<link href="/dashboard/images/favicon.png" rel="icon" type="image/png" />
  </head>

  <body class="php-page<?php echo $full ? ' page-full' : ''; ?>">
    <a class="skip-link" href="#main"><?php echo h(t('skip')); ?></a>

    <header class="header contain-to-grid">
      <nav class="top-bar" data-topbar>
        <ul class="title-area">
          <li class="name">
            <h1><a class="has-mark" href="<?php echo h($base); ?>index.html">
              <img class="brand-mark" src="/dashboard/images/vxost-logo.svg" width="30" height="30" alt="VXOST" />
              <span class="brand-text">VXOST <span class="muted mono" style="font-weight:400;font-size:.78rem">virtualhost</span></span>
            </a></h1>
          </li>
          <li class="toggle-topbar menu-icon">
            <a href="#"><span>Menu</span></a>
          </li>
        </ul>

        <section class="top-bar-section">
          <ul class="left">
            <?php foreach ($items as $key => [$href, $label, $icon]): ?>
            <li class="item<?php echo $key === $active ? ' active' : ''; ?>">
              <a href="<?php echo h($href); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
                <?php echo vxost_icon($icon); ?><?php echo h($label); ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </section>
      </nav>
    </header>

    <main id="main" class="wrapper">
<?php
}

/**
 * Stack and product versions, shown in the footer.
 *
 * VXOST_VERSION is the stack (lib/VERSION says 8.2.4-0): Apache, MariaDB, PHP
 * and Perl as built upstream. DASHBOARD_VERSION is the VXOST release, the same
 * number the app and vxost.com declare. They are two different things and
 * must not be aligned to each other.
 *
 * The static pages carry the same number by hand, in the footer and in the
 * release notes link: there is no generator in this repository, so a version
 * bump is a search across dashboard/ for the previous number.
 */
const VXOST_VERSION = '8.2.4';
const DASHBOARD_VERSION = '9.26.1';

/** Closes <main> and prints the same footer as the static pages. */
function vxost_footer(): void
{
    $base = vxost_base();
    $isIt = vxost_lang() === 'it';

    $about = $isIt
        ? 'Una distribuzione Apache gratuita che installa un server web completo sul tuo computer: Apache, il database MariaDB, PHP e Perl, pronti per sviluppare e collaudare in locale senza toccare un server di produzione.'
        : 'A free Apache distribution that installs a complete web server on your computer: Apache, the MariaDB database, PHP and Perl, ready to develop and test locally without touching a production server.';

    $credits = $isIt
        ? 'VXOST è pubblicato da %s: app nativa per Apple Silicon, dashboard ricostruita e quindici lingue. Software libero, sotto licenza GNU GPL v2.'
        : 'VXOST is published by %s: a native Apple Silicon app, a rebuilt dashboard and fifteen languages. Free software under the GNU GPL v2.';

    $cd = '<a href="https://www.chirurgiadigitale.it" target="_blank" rel="noopener">Equipe Digitale</a>';
    ?>
    </main>

    <footer class="footer">
      <div class="row footer-main">
        <div class="large-3 columns footer-brand">
          <p class="footer-logo">
            <img src="/dashboard/images/vxost-logo.svg" width="26" height="26" alt="" />
            <span>VXOST</span>
          </p>
          <p class="muted">Apache · MariaDB · PHP · Perl</p>
          <ul class="social">
            <li class="github"><a href="https://github.com/chirurgiadigitale/vxost-dashboard" aria-label="GitHub">GitHub</a></li>
          </ul>
        </div>


        <div class="large-2 columns">
          <h4 class="footer-title"><?php echo h(t('nav')); ?></h4>
          <ul class="footer_links footer_links--stack">
            <li><a href="<?php echo h($base); ?>index.html"><?php echo h(t('dash')); ?></a></li>
            <li><a href="/projects/"><?php echo h(t('projects')); ?></a></li>
            <li><a href="/dashboard/database.php"><?php echo h(t('database')); ?></a></li>
            <li><a href="/dashboard/ports.php"><?php echo h(t('ports')); ?></a></li>
            <li><a href="/dashboard/phpinfo.php">PHPInfo</a></li>
            <li><a href="<?php echo h($base); ?>faq.html"><?php echo h(t('faq')); ?></a></li>
          </ul>
        </div>

        <div class="large-2 columns">
          <h4 class="footer-title"><?php echo h(t('project')); ?></h4>
          <ul class="footer_links footer_links--stack">
            <li><a href="https://www.vxost.com/" target="_blank" rel="noopener">VXOST</a></li>
            <li><a href="https://github.com/chirurgiadigitale/vxost-dashboard" target="_blank" rel="noopener">GitHub · vxost-dashboard</a></li>
            <li><a href="https://github.com/chirurgiadigitale" target="_blank" rel="noopener">GitHub · Equipe Digitale</a></li>
            <li><a href="https://github.com/topics/vxost" target="_blank" rel="noopener">GitHub · topic vxost</a></li>
            <li><a href="https://httpd.apache.org/" target="_blank" rel="noopener">Apache HTTP Server</a></li>
            <li><a href="https://www.apache.org/" target="_blank" rel="noopener">Apache Software Foundation</a></li>
            <li><a href="https://github.com/chirurgiadigitale/vxost-dashboard/issues" target="_blank" rel="noopener">Community forum</a></li>
          </ul>
        </div>

        <div class="large-3 columns footer-about">
          <h4 class="footer-title"><?php echo $isIt ? "Cos'è VXOST" : 'What VXOST is'; ?></h4>
          <p class="muted"><?php echo h($about); ?></p>
          <p class="footer-version">
            <span><?php echo $isIt ? 'Stack' : 'Stack'; ?> <?php echo VXOST_VERSION; ?></span> ·
            <strong>VXOST v<?php echo DASHBOARD_VERSION; ?></strong>
          </p>
          <a href="https://vxost.com/assets/release-notes-<?php echo DASHBOARD_VERSION; ?>.txt" target="_blank" rel="noopener"
             style="display:inline-block;margin-top:var(--s-2);font-size:.78rem"><?php
             echo $isIt ? 'Note di versione' : 'Release notes'; ?> ↗</a>
        </div>

        <div class="large-2 columns footer-credits">
          <h4 class="footer-title"><?php echo $isIt ? 'Crediti' : 'Credits'; ?></h4>
          <p class="muted"><?php printf($credits, $cd); ?></p>
        </div>
      </div>

      <div class="row footer-bottom">
        <p class="footer_copyright">© <?php echo date('Y'); ?> <?php echo h(t('license')); ?></p>
        <a class="footer-top" href="#main"><?php echo h(t('top')); ?> ↑</a>
      </div>
    </footer>

    <script src="/dashboard/javascripts/all.js" type="text/javascript"></script>
  </body>
</html>
<?php
}
