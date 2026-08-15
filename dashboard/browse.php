<?php
/**
 * VXOST Dashboard v2, folder listing
 *
 * Replaces the listing Apache generates. That one is a table it has produced
 * the same way since 1996, and it can only be styled: the navigation, the
 * heading and the footer of the dashboard cannot be put around it, because
 * HeaderName and mod_autoindex disagree about who owns the markup.
 *
 * This page renders the same information through the dashboard layout, so a
 * folder without an index looks like the rest of the application instead of
 * like a directory listing from another decade.
 *
 * Reached through DirectoryIndex as the last fallback: a folder with its own
 * index.html or index.php still serves that.
 */

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

/** Page strings. */
function br_t(string $key): string
{
    static $s = [
        'en' => [
            'eyebrow' => 'Local workspace', 'empty' => 'This folder is empty',
            'name' => 'Name', 'size' => 'Size', 'modified' => 'Last modified',
            'parent' => 'Parent folder', 'items' => 'items', 'item' => 'item',
            'sub' => 'Files and folders served from',
        ],
        'it' => [
            'eyebrow' => 'Area di lavoro locale', 'empty' => 'Questa cartella è vuota',
            'name' => 'Nome', 'size' => 'Dimensione', 'modified' => 'Ultima modifica',
            'parent' => 'Cartella superiore', 'items' => 'elementi', 'item' => 'elemento',
            'sub' => 'File e cartelle serviti da',
        ],
    ];
    $lang = vxost_lang();
    return $s[$lang][$key] ?? $s['en'][$key] ?? $key;
}

/**
 * The folder being listed, resolved from the request rather than from the
 * script location: this file lives in dashboard/ but renders any folder.
 *
 * Anything outside the web root is refused. Without that check a crafted
 * path would list the whole disk.
 */
function browse_dir(): ?array
{
    $docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);
    $path = realpath($docroot . $uri);

    if ($docroot === false || $path === false || !is_dir($path)) {
        return null;
    }
    if ($path !== $docroot && !str_starts_with($path, $docroot . DIRECTORY_SEPARATOR)) {
        return null;                       // path traversal: nothing is listed
    }
    return ['path' => $path, 'uri' => rtrim($uri, '/') . '/'];
}

/** Human readable size. Folders have none worth showing. */
function br_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1073741824, 1) . ' GB';
}

/**
 * Entries in the folder, directories first, then files, both A to Z.
 *
 * The same files the .htaccess refuses to serve are left out of the listing.
 * Showing a dump.sql that answers 403 tells a visitor it is there, which is
 * half of what they wanted to know.
 */
function br_entries(string $dir): array
{
    $hidden = '/\.(env|sql|sqlite|sqlite3|db|dump|pem|key|bak)$/i';
    $out = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }
        if (preg_match($hidden, $entry)) {
            continue;
        }
        $full = $dir . '/' . $entry;
        $out[] = [
            'name'     => $entry,
            'dir'      => is_dir($full),
            'size'     => is_dir($full) ? null : (int) @filesize($full),
            'modified' => (int) @filemtime($full),
        ];
    }
    usort($out, static function (array $a, array $b): int {
        if ($a['dir'] !== $b['dir']) {
            return $a['dir'] ? -1 : 1;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

$target = browse_dir();
if ($target === null) {
    http_response_code(404);
    exit('Not found');
}

$entries = br_entries($target['path']);
$uri     = $target['uri'];
$crumbs  = array_values(array_filter(explode('/', trim($uri, '/'))));
$here    = $crumbs ? end($crumbs) : 'virtualhost';

vxost_header(h($here) . ', VXOST', 'projects');
?>

      <section class="hero">
        <div class="row">
          <div class="large-9 columns" data-reveal>
            <p class="eyebrow"><?php echo h(br_t('eyebrow')); ?></p>
            <h1><?php echo h($here); ?> <span class="mono" dir="ltr"><?php echo h(br_t('sub')); ?> <?php echo h($uri); ?></span></h1>
            <nav class="crumbs" aria-label="Breadcrumb">
              <ol>
                <li><a href="/dashboard/index.html">Dashboard</a></li>
                <?php
                $walk = '';
                foreach ($crumbs as $i => $c) {
                    $walk .= '/' . rawurlencode($c);
                    $last = $i === count($crumbs) - 1;
                    echo $last
                        ? '<li><span aria-current="page">' . h($c) . '</span></li>'
                        : '<li><a href="' . h($walk) . '/">' . h($c) . '</a></li>';
                }
                ?>
              </ol>
            </nav>
          </div>
        </div>
      </section>

      <section class="section section--tight">
        <div class="row">
          <div class="large-12 columns" data-reveal>
            <?php if (!$entries): ?>
            <div class="card empty-state">
              <span class="card-icon"><?php echo vxost_icon('folder', 22); ?></span>
              <h3><?php echo h(br_t('empty')); ?></h3>
            </div>
            <?php else: ?>
            <div class="table-wrap">
              <table class="browse-table">
                <thead>
                  <tr>
                    <th scope="col" colspan="2"><?php echo h(br_t('name')); ?></th>
                    <th scope="col" class="num"><?php echo h(br_t('size')); ?></th>
                    <th scope="col" class="num"><?php echo h(br_t('modified')); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (trim($uri, '/') !== ''): ?>
                  <tr>
                    <td class="ico"><span class="browse-dot browse-dot--up"></span></td>
                    <td colspan="3"><a href="../"><?php echo h(br_t('parent')); ?></a></td>
                  </tr>
                  <?php endif; ?>
                  <?php foreach ($entries as $e): ?>
                  <tr>
                    <td class="ico"><span class="browse-dot<?php echo $e['dir'] ? ' browse-dot--dir' : ''; ?>"></span></td>
                    <td class="nm">
                      <a href="<?php echo h(rawurlencode($e['name'])) . ($e['dir'] ? '/' : ''); ?>"><?php
                        echo h($e['name']) . ($e['dir'] ? '/' : ''); ?></a>
                    </td>
                    <td class="num"><?php echo $e['dir'] ? '—' : h(br_size((int) $e['size'])); ?></td>
                    <td class="num"><time datetime="<?php echo date('c', $e['modified']); ?>"><?php
                      echo date('d/m/Y H:i', $e['modified']); ?></time></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <p class="muted" style="margin-top:var(--s-3);font-size:.86rem">
              <?php echo count($entries) . ' ' . h(count($entries) === 1 ? br_t('item') : br_t('items')); ?>
            </p>
            <?php endif; ?>
          </div>
        </div>
      </section>

<?php vxost_footer(); ?>
