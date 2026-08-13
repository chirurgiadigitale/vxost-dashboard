<?php
/**
 * VXOST Dashboard v2, phpMyAdmin dentro il guscio della dashboard
 *
 * phpMyAdmin resta l'applicazione originale, non modificata (cosi' resta
 * aggiornabile): viene incorniciata dall'header e dal footer della dashboard,
 * che restano sempre visibili. Perche' l'iframe funzioni serve
 * $cfg['AllowThirdPartyFraming'] = 'sameorigin'; in phpmyadmin/config.inc.php.
 */

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

/** Etichette della pagina. */
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
    $context = stream_context_create([
        'http' => ['method' => 'HEAD', 'timeout' => 2, 'ignore_errors' => true],
    ]);
    $headers = @get_headers('http://127.0.0.1' . ($_SERVER['SERVER_PORT'] != 80 ? ':' . $_SERVER['SERVER_PORT'] : '') . '/phpmyadmin/', true, $context);

    if (!$headers) {
        return ['online' => false, 'framable' => false];
    }

    $xfo = $headers['X-Frame-Options'] ?? $headers['x-frame-options'] ?? '';
    if (is_array($xfo)) {
        $xfo = end($xfo);
    }
    $xfo = strtoupper(trim((string) $xfo));

    return [
        'online'   => true,
        'framable' => $xfo === '' || $xfo === 'SAMEORIGIN',
    ];
}

$status = pma_status();

vxost_header('VXOST, ' . db_t('title'), 'database', $status['online'] && $status['framable']);
?>

      <?php if ($status['online'] && $status['framable']): ?>

      <div class="embed-bar">
        <div class="embed-bar__inner">
          <span class="badge badge--ok"><span class="dot dot--pulse"></span> phpMyAdmin</span>
          <span class="muted mono" dir="ltr">localhost:3306</span>
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
              <p><?php echo h(db_t('blocked_p')); ?></p>
              <pre dir="ltr">$cfg['AllowThirdPartyFraming'] = 'sameorigin';</pre>
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
