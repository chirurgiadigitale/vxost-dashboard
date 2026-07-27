<?php
/**
 * PHPInfo — XAMPP Dashboard v2
 *
 * Incapsula l'output nativo di phpinfo() nel design system della dashboard:
 * l'informazione resta quella originale, cambia solo la presentazione.
 */

// 1. Cattura l'output nativo di phpinfo()
ob_start();
phpinfo();
$raw = (string) ob_get_clean();

// 2. Estrae il solo contenuto del body e rimuove stile e immagini di default
if (preg_match('/<body[^>]*>(.*)<\/body>/is', $raw, $matches)) {
    $body = $matches[1];
} else {
    $body = $raw;
}
$body = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $body);   // via il CSS inline
$body = preg_replace('/<img[^>]*>/i', '', $body);                   // nessuna immagine
$body = preg_replace('/<a href="http:\/\/www\.php\.net\/"[^>]*>\s*<\/a>/i', '', $body);

// 3. Dati di sintesi per le card di stato
$summary = array(
    'Versione PHP'   => PHP_VERSION,
    'Zend Engine'    => zend_version(),
    'Sistema'        => php_uname('s') . ' ' . php_uname('r'),
    'Server API'     => PHP_SAPI,
    'Memory limit'   => ini_get('memory_limit'),
    'Upload max'     => ini_get('upload_max_filesize'),
    'Post max'       => ini_get('post_max_size'),
    'Timezone'       => date_default_timezone_get(),
);
$extensions = get_loaded_extensions();
sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

$e = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>PHPInfo — XAMPP</title>
    <meta name="robots" content="noindex, nofollow" />

    <link href="/dashboard/stylesheets/normalize.css" rel="stylesheet" type="text/css" />
    <link href="/dashboard/stylesheets/all.css" rel="stylesheet" type="text/css" />
    <meta name="color-scheme" content="dark light" />
    <meta name="theme-color" content="#070B16" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link href="/dashboard/images/favicon.png" rel="icon" type="image/png" />
  </head>

  <body class="phpinfo-page">
    <a class="skip-link" href="#main">Vai al contenuto</a>

    <header class="header contain-to-grid">
      <nav class="top-bar" data-topbar>
        <ul class="title-area">
          <li class="name">
            <h1><a class="has-mark" href="/dashboard/it/index.html">
              <svg class="brand-mark" width="30" height="30" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
                <rect x="1" y="1" width="30" height="30" rx="9" fill="url(#bgPhpinfo)" fill-opacity=".18" stroke="url(#bgPhpinfo)" stroke-width="1.4"/>
                <path d="M11 20.5 8 16l3-4.5M21 11.5 24 16l-3 4.5M18.4 9.5l-4.8 13" stroke="url(#bgPhpinfo)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <defs>
                  <linearGradient id="bgPhpinfo" x1="2" y1="2" x2="30" y2="30" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#22C55E"/><stop offset=".55" stop-color="#38BDF8"/><stop offset="1" stop-color="#A78BFA"/>
                  </linearGradient>
                </defs>
              </svg>
              <span class="brand-text">XAMPP <span class="muted mono" style="font-weight:400;font-size:.78rem">localhost</span></span>
            </a></h1>
          </li>
          <li class="toggle-topbar menu-icon">
            <a href="#"><span>Menu</span></a>
          </li>
        </ul>

        <section class="top-bar-section">
          <ul class="left">
            <li class="item"><a href="/dashboard/it/index.html">Dashboard</a></li>
            <li class="item"><a href="/dashboard/it/faq.html">Domande frequenti</a></li>
            <li class="item"><a href="/dashboard/it/howto.html">Guide HOW-TO</a></li>
            <li class="item active"><a href="/dashboard/phpinfo.php">PHPInfo</a></li>
            <li class="item"><a href="/phpmyadmin/">phpMyAdmin</a></li>
            <li class="item"><a href="/progetti/">Progetti</a></li>
          </ul>
        </section>
      </nav>
    </header>

    <main id="main" class="wrapper">

      <section class="hero">
        <div class="row">
          <div class="large-8 columns" data-reveal>
            <p class="eyebrow">Configurazione runtime</p>
            <h1>PHP <?php echo $e(PHP_VERSION); ?> <span>Moduli, direttive ini, variabili d'ambiente e opzioni di build</span></h1>
            <div class="hero-actions">
              <span class="badge badge--ok"><span class="dot dot--pulse"></span> <?php echo $e(PHP_SAPI); ?></span>
              <span class="badge"><?php echo count($extensions); ?> estensioni caricate</span>
              <span class="badge"><?php echo $e(php_uname('s') . ' ' . php_uname('m')); ?></span>
            </div>
          </div>
        </div>
      </section>

      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="status-grid">
              <?php foreach ($summary as $label => $value): ?>
              <div class="stat">
                <span class="dot" style="color:var(--cyan)"></span>
                <span>
                  <span class="stat-label"><?php echo $e($label); ?></span><br>
                  <span class="stat-value mono"><?php echo $e($value); ?></span>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow">Estensioni</p>
                <h2>Moduli caricati</h2>
              </div>
              <p class="muted"><?php echo count($extensions); ?> totali</p>
            </div>
            <div class="chip-list">
              <?php foreach ($extensions as $ext): ?>
              <span class="chip"><?php echo $e($ext); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="section-head">
              <div>
                <p class="eyebrow">Output completo</p>
                <h2>phpinfo()</h2>
              </div>
              <p class="muted">Dati nativi, presentazione della dashboard</p>
            </div>
            <div class="phpinfo"><?php echo $body; ?></div>
          </div>
        </div>
      </section>

    </main>

    <footer class="footer">
      <div class="row">
        <div class="large-6 columns">
          <p class="footer_copyright">XAMPP Dashboard · PHP <?php echo $e(PHP_VERSION); ?> · <?php echo $e(date('d/m/Y H:i')); ?></p>
        </div>
        <div class="large-6 columns">
          <ul class="footer_links">
            <li><a href="/dashboard/it/index.html">Dashboard</a></li>
            <li><a href="/phpmyadmin/">phpMyAdmin</a></li>
            <li><a href="/progetti/">Progetti</a></li>
          </ul>
        </div>
      </div>
    </footer>

    <script src="/dashboard/javascripts/all.js" type="text/javascript"></script>
  </body>
</html>
