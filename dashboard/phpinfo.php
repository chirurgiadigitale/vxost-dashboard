<?php
/**
 * PHPInfo — XAMPP Dashboard v2
 *
 * Incapsula l'output nativo di phpinfo() nel design system della dashboard:
 * l'informazione resta quella originale, cambia solo la presentazione.
 */

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

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
$isIt = xampp_lang() === 'it';
$summary = [
    ($isIt ? 'Versione PHP' : 'PHP version') => PHP_VERSION,
    'Zend Engine'                            => zend_version(),
    ($isIt ? 'Sistema' : 'System')           => php_uname('s') . ' ' . php_uname('r'),
    'Server API'                             => PHP_SAPI,
    'Memory limit'                           => ini_get('memory_limit'),
    'Upload max'                             => ini_get('upload_max_filesize'),
    'Post max'                               => ini_get('post_max_size'),
    'Timezone'                               => date_default_timezone_get(),
];
$extensions = get_loaded_extensions();
sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

xampp_header('XAMPP — PHPInfo', 'phpinfo');
?>

      <section class="hero">
        <div class="row">
          <div class="large-8 columns" data-reveal>
            <p class="eyebrow"><?php echo $isIt ? 'Configurazione runtime' : 'Runtime configuration'; ?></p>
            <h1>PHP <?php echo h(PHP_VERSION); ?> <span><?php echo $isIt ? 'Moduli, direttive ini, variabili d\'ambiente e opzioni di build' : 'Modules, ini directives, environment variables and build options'; ?></span></h1>
            <div class="hero-actions">
              <span class="badge badge--ok"><span class="dot dot--pulse"></span> <?php echo h(PHP_SAPI); ?></span>
              <span class="badge"><?php echo count($extensions); ?> <?php echo $isIt ? 'estensioni caricate' : 'loaded extensions'; ?></span>
              <span class="badge"><?php echo h(php_uname('s') . ' ' . php_uname('m')); ?></span>
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
                  <span class="stat-label"><?php echo h($label); ?></span><br>
                  <span class="stat-value mono"><?php echo h($value); ?></span>
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
                <p class="eyebrow"><?php echo $isIt ? 'Estensioni' : 'Extensions'; ?></p>
                <h2><?php echo $isIt ? 'Moduli caricati' : 'Loaded modules'; ?></h2>
              </div>
              <p class="muted"><?php echo count($extensions); ?> <?php echo $isIt ? 'totali' : 'total'; ?></p>
            </div>
            <div class="chip-list">
              <?php foreach ($extensions as $ext): ?>
              <span class="chip"><?php echo h($ext); ?></span>
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
                <p class="eyebrow"><?php echo $isIt ? 'Output completo' : 'Full output'; ?></p>
                <h2>phpinfo()</h2>
              </div>
              <p class="muted"><?php echo $isIt ? 'Dati nativi, presentazione della dashboard' : 'Native data, dashboard presentation'; ?></p>
            </div>
            <div class="phpinfo"><?php echo $body; ?></div>
          </div>
        </div>
      </section>

<?php xampp_footer(); ?>
