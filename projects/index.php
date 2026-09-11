<?php
/**
 * VXOST Dashboard v2, index of local projects
 *
 * Lists the folders under www/projects, works out what each one is built
 * with (WordPress, Laravel, Node, plain HTML and so on) and links it to the
 * VirtualHost that serves it, where there is one.
 *
 * The folder ships empty: on a fresh install this page simply shows its
 * starting state.
 */

declare(strict_types=1);

$layout = dirname(__DIR__) . '/dashboard/includes/layout.php';
if (!is_readable($layout)) {
    http_response_code(500);
    exit('Dashboard layout not found: ' . htmlspecialchars($layout));
}
require $layout;

/** Page strings. */
function pr_t(string $key): string
{
    static $s = [
        'en' => [
            'title' => 'Projects', 'eyebrow' => 'Local workspace',
            'subtitle' => 'Every site and application served from www/projects',
            'search' => 'Filter projects…', 'open' => 'Open', 'count' => 'projects',
            'empty_t' => 'No project yet',
            'empty_p' => 'Drop a folder into www/projects and it will show up here automatically. Each subfolder becomes an entry with its detected technology.',
            'files' => 'files', 'modified' => 'updated', 'vhost' => 'Dedicated port',
            'nomatch' => 'No project matches this filter.', 'all' => 'All',
            'view_grid' => 'Grid view', 'view_list' => 'List view',
            'name' => 'Name', 'stack' => 'Stack', 'port' => 'Port', 'date' => 'Last change',
            'sort_az' => 'A → Z', 'sort_date' => 'Most recent',
        ],
        'it' => [
            'title' => 'Progetti', 'eyebrow' => 'Area di lavoro locale',
            'subtitle' => 'Tutti i siti e le applicazioni serviti da www/projects',
            'search' => 'Filtra i progetti…', 'open' => 'Apri', 'count' => 'progetti',
            'empty_t' => 'Nessun progetto',
            'empty_p' => 'Aggiungi una cartella dentro www/projects e comparirà qui automaticamente, con il rilevamento della tecnologia usata.',
            'files' => 'file', 'modified' => 'aggiornato', 'vhost' => 'Porta dedicata',
            'nomatch' => 'Nessun progetto corrisponde al filtro.', 'all' => 'Tutti',
            'view_grid' => 'Vista a griglia', 'view_list' => 'Vista a elenco',
            'name' => 'Nome', 'stack' => 'Tecnologia', 'port' => 'Porta', 'date' => 'Ultima modifica',
            'sort_az' => 'A → Z', 'sort_date' => 'Più recenti',
        ],
    ];
    $lang = vxost_lang();
    return $s[$lang][$key] ?? $s['en'][$key] ?? $key;
}

/**
 * Works out what a project is built with, from the files that give it away.
 *
 * @return array{label: string, icon: string, color: string}
 */
function detect_stack(string $dir): array
{
    $has = static fn(string $file): bool => file_exists($dir . '/' . $file);

    if ($has('wp-config.php') || $has('wp-load.php') || is_dir($dir . '/wp-content')) {
        return ['label' => 'WordPress', 'icon' => 'wp', 'color' => 'cyan'];
    }
    if ($has('artisan')) {
        return ['label' => 'Laravel', 'icon' => 'laravel', 'color' => 'amber'];
    }
    if ($has('composer.json')) {
        return ['label' => 'PHP / Composer', 'icon' => 'code', 'color' => 'violet'];
    }
    if ($has('next.config.js') || $has('next.config.mjs') || $has('nuxt.config.ts')) {
        return ['label' => 'Next / Nuxt', 'icon' => 'node', 'color' => ''];
    }
    if ($has('package.json')) {
        return ['label' => 'Node', 'icon' => 'node', 'color' => ''];
    }
    if ($has('index.php')) {
        return ['label' => 'PHP', 'icon' => 'code', 'color' => 'violet'];
    }
    if ($has('index.html') || $has('index.htm')) {
        return ['label' => 'HTML', 'icon' => 'globe', 'color' => ''];
    }
    return ['label' => '', 'icon' => 'folder', 'color' => ''];
}

/**
 * Same, looking one level deeper as well. Plenty of folders are containers,
 * project/version/, and hold nothing recognisable at the top level.
 *
 * @return array{label: string, icon: string, color: string, nested: bool}
 */
function detect_stack_deep(string $dir): array
{
    $stack = detect_stack($dir);
    if ($stack['label'] !== '') {
        return $stack + ['nested' => false];
    }

    $children = array_slice(array_filter(
        scandir($dir) ?: [],
        static fn(string $e): bool => $e[0] !== '.' && is_dir($dir . '/' . $e)
    ), 0, 12);

    foreach ($children as $child) {
        $found = detect_stack($dir . '/' . $child);
        if ($found['label'] !== '') {
            return $found + ['nested' => true];
        }
    }

    return ['label' => '—', 'icon' => 'folder', 'color' => '', 'nested' => false];
}

/** VirtualHost ports, keyed by project folder. */
function vhost_ports(): array
{
    $file = vxost_env()['vhosts'];
    if (!is_readable($file)) {
        return [];
    }

    $lines = [];
    foreach (explode("\n", (string) file_get_contents($file)) as $line) {
        if (!str_starts_with(ltrim($line), '#')) {
            $lines[] = $line;
        }
    }

    $map = [];
    if (preg_match_all('/<VirtualHost\s+([^>]+)>(.*?)<\/VirtualHost>/is', implode("\n", $lines), $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $block) {
            if (!preg_match('/:(\d+)\s*$/', trim($block[1]), $bind)) {
                continue;
            }
            if (!preg_match('/DocumentRoot\s+"?([^"\n]+?)"?\s*$/im', $block[2], $root)) {
                continue;
            }
            $path = trim($root[1]);
            $marker = '/www/projects/';
            $pos = stripos($path, $marker);
            if ($pos !== false) {
                $name = strtok(substr($path, $pos + strlen($marker)), '/');
                if (is_string($name) && $name !== '' && !isset($map[$name])) {
                    $map[$name] = (int) $bind[1];
                }
            }
        }
    }
    return $map;
}

/** The projects currently in the folder. */
function scan_projects(string $root): array
{
    $ports = vhost_ports();
    $projects = [];

    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }
        $dir = $root . '/' . $entry;
        if (!is_dir($dir)) {
            continue;
        }

        $files = @scandir($dir) ?: [];
        $stack = detect_stack_deep($dir);

        $projects[] = [
            'name'     => $entry,
            'slug'     => rawurlencode($entry),
            'stack'    => $stack['label'],
            'icon'     => $stack['icon'],
            'color'    => $stack['color'],
            'nested'   => $stack['nested'],
            'files'    => max(0, count($files) - 2),
            'modified' => (int) @filemtime($dir),
            'port'     => $ports[$entry] ?? null,
        ];
    }

    usort($projects, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    return $projects;
}

$root = __DIR__;
$projects = scan_projects($root);

// The stacks actually present, for the filter chips
$stacks = [];
foreach ($projects as $p) {
    if ($p['stack'] !== '—') {
        $stacks[$p['stack']] = true;
    }
}
ksort($stacks);

vxost_header('VXOST, ' . pr_t('title'), 'projects');
?>

      <section class="hero">
        <div class="row">
          <div class="large-9 columns" data-reveal>
            <p class="eyebrow"><?php echo h(pr_t('eyebrow')); ?></p>
            <h1><?php echo h(pr_t('title')); ?> <span><?php echo h(pr_t('subtitle')); ?></span></h1>
          </div>
        </div>
      </section>

      <?php if (!$projects): ?>

      <!-- EMPTY STATE -->
      <section class="section">
        <div class="row">
          <div class="large-8 columns" data-reveal>
            <div class="card empty-state">
              <span class="card-icon"><?php echo vxost_icon('folder', 22); ?></span>
              <h3><?php echo h(pr_t('empty_t')); ?></h3>
              <p><?php echo h(pr_t('empty_p')); ?></p>
              <p class="mono muted" dir="ltr" style="margin-top:var(--s-3)"><?php echo h($root); ?></p>
            </div>
          </div>
        </div>
      </section>

      <?php else: ?>

      <!-- FILTERS -->
      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <div class="toolbar">
              <label class="search-field">
                <span class="visually-hidden"><?php echo h(pr_t('search')); ?></span>
                <?php echo vxost_icon('search'); ?>
                <input type="search" id="project-filter" placeholder="<?php echo h(pr_t('search')); ?>"
                       autocomplete="off" spellcheck="false">
              </label>
              <div class="chip-list" role="group">
                <button type="button" class="chip chip--filter is-active" data-filter="*"><?php echo h(pr_t('all')); ?></button>
                <?php foreach (array_keys($stacks) as $stack): ?>
                <button type="button" class="chip chip--filter" data-filter="<?php echo h($stack); ?>"><?php echo h($stack); ?></button>
                <?php endforeach; ?>
              </div>

              <div class="view-switch" role="group" aria-label="<?php echo h(pr_t('view_grid')); ?> / <?php echo h(pr_t('view_list')); ?>">
                <button type="button" class="view-btn is-active" data-view="grid"
                        title="<?php echo h(pr_t('view_grid')); ?>" aria-pressed="true">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>
                    <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
                  </svg>
                  <span class="visually-hidden"><?php echo h(pr_t('view_grid')); ?></span>
                </button>
                <button type="button" class="view-btn" data-view="list"
                        title="<?php echo h(pr_t('view_list')); ?>" aria-pressed="false">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 6.5h16M4 12h16M4 17.5h16"/>
                  </svg>
                  <span class="visually-hidden"><?php echo h(pr_t('view_list')); ?></span>
                </button>
              </div>

              <span class="badge" id="project-count"><?php echo count($projects); ?> <?php echo h(pr_t('count')); ?></span>
            </div>
          </div>
        </div>
      </section>

      <!-- PROJECTS -->
      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns">
            <div class="bento project-view" id="project-grid" data-view="grid">
              <?php foreach ($projects as $p): ?>
              <a class="card project-card"
                 href="<?php echo $p['port'] ? 'http://virtualhost:' . (int) $p['port'] . '/' : '/projects/' . $p['slug'] . '/'; ?>"
                 data-name="<?php echo h(mb_strtolower($p['name'])); ?>"
                 data-stack="<?php echo h($p['stack']); ?>"
                 <?php echo $p['port'] ? 'target="_blank" rel="noopener"' : ''; ?>>
                <span class="card-icon<?php echo $p['color'] ? ' card-icon--' . h($p['color']) : ''; ?>">
                  <?php echo vxost_icon($p['icon'], 22); ?>
                </span>
                <span class="project-main">
                  <h3><?php echo h($p['name']); ?></h3>
                  <span class="project-meta">
                    <span class="badge"><?php echo h($p['stack']); ?></span>
                    <?php if ($p['port']): ?>
                    <span class="badge badge--ok" title="<?php echo h(pr_t('vhost')); ?>">:<?php echo (int) $p['port']; ?></span>
                    <?php endif; ?>
                  </span>
                </span>
                <span class="project-date muted">
                  <?php if ($p['modified']): ?>
                  <span class="project-date__label"><?php echo h(pr_t('date')); ?></span>
                  <time datetime="<?php echo date('c', $p['modified']); ?>"><?php echo date('d/m/Y H:i', $p['modified']); ?></time>
                  <?php endif; ?>
                  <span class="project-files"><?php echo (int) $p['files']; ?> <?php echo h(pr_t('files')); ?></span>
                </span>
                <span class="card-link"><?php echo h(pr_t('open')); ?> <?php echo vxost_icon('arrow', 16); ?></span>
              </a>
              <?php endforeach; ?>
            </div>
            <p class="muted hide" id="project-nomatch"><?php echo h(pr_t('nomatch')); ?></p>
          </div>
        </div>
      </section>

      <script>
        /* Project filter: text and stack, entirely client side */
        (function () {
          var input = document.getElementById('project-filter');
          var cards = Array.prototype.slice.call(document.querySelectorAll('#project-grid .project-card'));
          var chips = Array.prototype.slice.call(document.querySelectorAll('.chip--filter'));
          var counter = document.getElementById('project-count');
          var nomatch = document.getElementById('project-nomatch');
          var countLabel = <?php echo json_encode(pr_t('count')); ?>;
          var stack = '*';

          function apply() {
            var query = input.value.trim().toLowerCase();
            var visible = 0;

            cards.forEach(function (card) {
              var okText = !query || card.getAttribute('data-name').indexOf(query) !== -1;
              var okStack = stack === '*' || card.getAttribute('data-stack') === stack;
              var show = okText && okStack;
              card.classList.toggle('hide', !show);
              if (show) visible++;
            });

            counter.textContent = visible + ' ' + countLabel;
            nomatch.classList.toggle('hide', visible !== 0);
          }

          input.addEventListener('input', apply);
          chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
              chips.forEach(function (c) { c.classList.remove('is-active'); });
              chip.classList.add('is-active');
              stack = chip.getAttribute('data-filter');
              apply();
            });
          });

          /* Grid or list, remembered between visits */
          var VIEW_KEY = 'vxost-projects-view';
          var grid = document.getElementById('project-grid');
          var viewButtons = Array.prototype.slice.call(document.querySelectorAll('.view-btn'));

          function setView(view) {
            grid.setAttribute('data-view', view);
            grid.classList.toggle('bento', view === 'grid');
            grid.classList.toggle('project-list', view === 'list');
            viewButtons.forEach(function (btn) {
              var on = btn.getAttribute('data-view') === view;
              btn.classList.toggle('is-active', on);
              btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            try { localStorage.setItem(VIEW_KEY, view); } catch (e) { /* noop */ }
          }

          viewButtons.forEach(function (btn) {
            btn.addEventListener('click', function () { setView(btn.getAttribute('data-view')); });
          });

          try {
            var saved = localStorage.getItem(VIEW_KEY);
            if (saved === 'list') setView('list');
          } catch (e) { /* noop */ }

          // "/" jumps to the search field
          document.addEventListener('keydown', function (e) {
            if (e.key === '/' && document.activeElement !== input) {
              e.preventDefault();
              input.focus();
            }
          });
        })();
      </script>

      <?php endif; ?>

<?php vxost_footer(); ?>
