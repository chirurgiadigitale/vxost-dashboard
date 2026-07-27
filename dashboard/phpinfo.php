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

/**
 * L'output nativo e' un elenco piatto di 96 tabelle: scorrerlo e' scomodo.
 * Ogni sezione <h2> diventa un blocco richiudibile e alimenta il sommario,
 * senza toccare i dati che contiene.
 *
 * @return array{html: string, sections: array<int, array{id: string, title: string}>}
 */
function group_sections(string $body): array
{
    // Divide l'output sui titoli di sezione, mantenendoli
    $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/is', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($parts) < 3) {
        return ['html' => $body, 'sections' => []];
    }

    $html = trim($parts[0]);       // intestazione generale, resta sempre visibile
    $sections = [];
    $index = 0;

    for ($i = 1; $i < count($parts); $i += 2) {
        $title = trim(strip_tags($parts[$i]));
        $content = $parts[$i + 1] ?? '';
        if ($title === '') {
            continue;
        }

        $index++;
        $id = 'sec-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
        $sections[] = ['id' => $id, 'title' => $title];

        // Le prime due sezioni restano aperte: sono quelle che si consultano
        $open = $index <= 2 ? ' open' : '';
        $html .= sprintf(
            '<details class="info-section" id="%s"%s data-title="%s">'
            . '<summary><span>%s</span></summary>'
            . '<div class="info-section__body">%s</div>'
            . '</details>',
            htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
            $open,
            htmlspecialchars(mb_strtolower($title), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $content
        );
    }

    return ['html' => $html, 'sections' => $sections];
}

$grouped = group_sections($body);
$body = $grouped['html'];
$sections = $grouped['sections'];

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
              <p class="muted"><?php echo count($sections); ?> <?php echo $isIt ? 'sezioni' : 'sections'; ?></p>
            </div>

            <?php if ($sections): ?>
            <div class="toolbar" style="margin-bottom:var(--s-4)">
              <label class="search-field">
                <span class="visually-hidden"><?php echo $isIt ? 'Cerca una sezione' : 'Search a section'; ?></span>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                <input type="search" id="info-filter" autocomplete="off" spellcheck="false"
                       placeholder="<?php echo $isIt ? 'Filtra le sezioni…' : 'Filter sections…'; ?>">
              </label>
              <div class="chip-list">
                <button type="button" class="chip chip--filter" id="info-expand"><?php echo $isIt ? 'Espandi tutto' : 'Expand all'; ?></button>
                <button type="button" class="chip chip--filter" id="info-collapse"><?php echo $isIt ? 'Chiudi tutto' : 'Collapse all'; ?></button>
              </div>
            </div>

            <nav class="chip-list info-toc" aria-label="<?php echo $isIt ? 'Sommario' : 'Table of contents'; ?>">
              <?php foreach ($sections as $s): ?>
              <a class="chip" href="#<?php echo h($s['id']); ?>"><?php echo h($s['title']); ?></a>
              <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <div class="phpinfo"><?php echo $body; ?></div>

            <script>
              /* Sommario e filtro delle sezioni di phpinfo() */
              (function () {
                var sections = Array.prototype.slice.call(document.querySelectorAll('.info-section'));
                var filter = document.getElementById('info-filter');
                if (!sections.length || !filter) return;

                filter.addEventListener('input', function () {
                  var q = filter.value.trim().toLowerCase();
                  sections.forEach(function (s) {
                    var match = !q || s.getAttribute('data-title').indexOf(q) !== -1;
                    s.classList.toggle('hide', !match);
                    if (q && match) s.open = true;
                  });
                });

                document.getElementById('info-expand').addEventListener('click', function () {
                  sections.forEach(function (s) { s.open = true; });
                });
                document.getElementById('info-collapse').addEventListener('click', function () {
                  sections.forEach(function (s) { s.open = false; });
                });

                /* Un link del sommario apre la sezione prima di saltarci */
                document.querySelectorAll('.info-toc a').forEach(function (link) {
                  link.addEventListener('click', function () {
                    var target = document.getElementById(link.getAttribute('href').slice(1));
                    if (target) target.open = true;
                  });
                });
              })();
            </script>
          </div>
        </div>
      </section>

<?php xampp_footer(); ?>
