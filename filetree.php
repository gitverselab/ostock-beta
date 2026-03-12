<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * File Tree – single-file PHP app (Visual + Text views)
 * - Change BASE_DIR if you want to lock browsing to a specific folder.
 * - Toggle hidden files via ?hidden=1
 * - Switch views via ?view=visual (default) or ?view=text
 * - Text tree can include sizes/dates via ?meta=1
 * - Export text tree via ?export=text
 */

define('BASE_DIR', __DIR__);               // ← lock to this folder (change if needed)
$showHidden = (isset($_GET['hidden']) && $_GET['hidden'] === '1');
$view       = $_GET['view'] ?? 'visual';
$includeMeta= (isset($_GET['meta']) && $_GET['meta'] === '1');

/* ---------- helpers ---------- */

function normalize_path(string $p): string {
    return str_replace('\\', '/', $p);
}
function is_within(string $base, string $path): bool {
    $base = rtrim(normalize_path(realpath($base) ?: $base), '/') . '/';
    $path = rtrim(normalize_path(realpath($path) ?: $path), '/') . '/';
    return strncmp($path, $base, strlen($base)) === 0;
}
function human_size(int $bytes): string {
    $u = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($u)-1) { $bytes /= 1024; $i++; }
    return $i === 0 ? sprintf('%d %s', $bytes, $u[$i]) : sprintf('%.2f %s', $bytes, $u[$i]);
}
function svg_icon(string $type, string $ext=''): string {
    $base = 'class="w-4 h-4 text-gray-600" aria-hidden="true"';
    if ($type === 'folder') {
        return '<svg '.$base.' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
    }
    $icon = '<svg '.$base.' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>';
    $ext = strtolower($ext);
    $badges = ['php','jpg','jpeg','png','gif','pdf','txt','css','js','html','mp4','mp3','zip','rar','7z','sql','csv'];
    if (in_array($ext, $badges, true)) {
        $label = strtoupper($ext);
        return '<span class="relative inline-flex items-center">'.$icon.'<span class="absolute -right-2 -top-2 text-[9px] leading-none px-1 py-[1px] bg-gray-800 text-white rounded">'.$label.'</span></span>';
    }
    return $icon;
}

/* ---------- routing: resolve current dir ---------- */

$reqPath = $_GET['path'] ?? '';
$reqPath = preg_replace('#[\\\\]+#', '/', (string)$reqPath);
$reqPath = ltrim($reqPath, '/');
$absPath = realpath(BASE_DIR . DIRECTORY_SEPARATOR . $reqPath);

if ($absPath === false || !is_dir($absPath) || !is_within(BASE_DIR, $absPath)) {
    $absPath = BASE_DIR;
    $reqPath = '';
}

/* ---------- common listing (dirs first, case-insensitive) ---------- */

function sorted_items(string $dir, bool $showHidden): array {
    $items = @scandir($dir) ?: [];
    $items = array_values(array_diff($items, ['.','..']));
    if (!$showHidden) {
        $items = array_values(array_filter($items, fn($i) => $i !== '' && $i[0] !== '.'));
    }
    usort($items, function($a,$b) use ($dir) {
        $fa = $dir . DIRECTORY_SEPARATOR . $a;
        $fb = $dir . DIRECTORY_SEPARATOR . $b;
        $da = is_dir($fa);
        $db = is_dir($fb);
        if ($da !== $db) return $da ? -1 : 1;
        return strcasecmp($a, $b);
    });
    return $items;
}

/* ---------- download handler (binary files) ---------- */

if (isset($_GET['download'])) {
    $dlRel = preg_replace('#[\\\\]+#', '/', ltrim((string)$_GET['download'], '/'));
    $dlAbs = realpath(BASE_DIR . DIRECTORY_SEPARATOR . $dlRel);
    if ($dlAbs !== false && is_file($dlAbs) && is_within(BASE_DIR, $dlAbs)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($dlAbs).'"');
        header('Content-Length: '.filesize($dlAbs));
        header('X-Content-Type-Options: nosniff');
        readfile($dlAbs);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

/* ---------- TEXT TREE generator & exporter ---------- */

function text_tree_lines(string $dir, bool $showHidden, bool $includeMeta, string $prefix = ''): array {
    $lines = [];
    $items = sorted_items($dir, $showHidden);
    $count = count($items);
    foreach ($items as $idx => $name) {
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $isLast = ($idx === $count - 1);
        $branch = $isLast ? '└── ' : '├── ';
        $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');

        if (is_dir($full)) {
            $lines[] = $prefix . $branch . $name . '/';
            $lines = array_merge($lines, text_tree_lines($full, $showHidden, $includeMeta, $nextPrefix));
        } else {
            if ($includeMeta) {
                $mtime = @filemtime($full) ?: time();
                $size  = @filesize($full) ?: 0;
                $lines[] = $prefix . $branch . $name . '  (' . human_size((int)$size) . ', ' . date('Y-m-d H:i', $mtime) . ')';
            } else {
                $lines[] = $prefix . $branch . $name;
            }
        }
    }
    return $lines;
}

function build_text_tree(string $absPath, string $reqPath, bool $showHidden, bool $includeMeta): string {
    // Header like Unix `tree`: show current folder name (relative) or "."
    $rootLabel = $reqPath === '' ? '.' : $reqPath;
    $lines = [$rootLabel . '/'];
    $lines = array_merge($lines, text_tree_lines($absPath, $showHidden, $includeMeta, ''));
    return implode("\n", $lines) . "\n";
}

// Export text file
if (isset($_GET['export']) && $_GET['export'] === 'text') {
    $content = build_text_tree($absPath, $reqPath, $showHidden, $includeMeta);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="file-tree.txt"');
    header('X-Content-Type-Options: nosniff');
    echo $content;
    exit;
}

/* ---------- VISUAL tree rendering ---------- */

function breadcrumb_html(string $rel): string {
    $parts = array_values(array_filter(explode('/', $rel), fn($p) => $p !== ''));
    $crumbs = [];
    $crumbs[] = '<a href="?path=" class="text-blue-600 hover:underline">root</a>';
    $acc = [];
    foreach ($parts as $p) {
        $acc[] = $p;
        $crumbs[] = '<span class="mx-1 text-gray-400">/</span><a class="text-blue-600 hover:underline" href="?path='.rawurlencode(implode('/',$acc)).'">'.htmlspecialchars($p,ENT_QUOTES).'</a>';
    }
    return implode('', $crumbs);
}

function list_tree(string $dir, bool $showHidden, string $rootBase): string {
    $items = sorted_items($dir, $showHidden);
    $html = "<ul class=\"ml-4 border-l pl-3 space-y-1\">\n";
    foreach ($items as $name) {
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $rel  = ltrim(normalize_path(substr($full, strlen($rootBase))), '/');

        if (is_dir($full)) {
            $safe = htmlspecialchars($name, ENT_QUOTES);
            $html .= "<li>";
            $html .= "<details class=\"group\">\n";
            $html .= "<summary class=\"flex items-center gap-2 cursor-pointer select-none hover:bg-gray-100 px-2 py-1 rounded-md\">"
                   . svg_icon('folder')
                   . "<span class=\"font-medium\">{$safe}</span>"
                   . "<span class=\"ml-auto text-xs text-gray-500\">dir</span>"
                   . "</summary>\n";
            $html .= "<div class=\"mt-1\">\n" . list_tree($full, $showHidden, $rootBase) . "</div>\n";
            $html .= "</details>\n</li>\n";
        } else {
            $safe = htmlspecialchars($name, ENT_QUOTES);
            $mtime = @filemtime($full) ?: time();
            $size  = @filesize($full) ?: 0;
            $dl    = '?download=' . rawurlencode($rel);
            $html .= "<li>\n<a href=\"{$dl}\" class=\"flex items-center gap-2 px-2 py-1 rounded-md hover:bg-gray-100\">\n"
                   . svg_icon('file', pathinfo($name, PATHINFO_EXTENSION))
                   . "<span>{$safe}</span>\n"
                   . "<span class=\"ml-auto text-xs text-gray-500\">"
                   . human_size((int)$size) . " • " . date('Y-m-d H:i', $mtime)
                   . "</span>\n</a>\n</li>\n";
        }
    }
    $html .= "</ul>\n";
    return $html;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>File Tree</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
  <div class="max-w-5xl mx-auto p-6">
    <header class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight">File Tree</h1>
      <div class="mt-1 text-sm text-gray-600">
        <?= breadcrumb_html($reqPath) ?>
      </div>
    </header>

    <?php
      // Compose utility URLs preserving path & hidden/meta/view
      $baseQuery = [
        'path'   => $reqPath,
        'hidden' => $showHidden ? '1' : '0'
      ];
      $visualUrl = '?' . http_build_query(array_merge($baseQuery, ['view'=>'visual']));
      $textUrl   = '?' . http_build_query(array_merge($baseQuery, ['view'=>'text', 'meta'=>$includeMeta ? '1' : '0']));
      $toggleHiddenUrl = '?' . http_build_query(array_merge(['path'=>$reqPath, 'view'=>$view], ['hidden'=>$showHidden ? '0' : '1', 'meta'=>$includeMeta ? '1' : '0']));
      $toggleMetaUrl   = '?' . http_build_query(array_merge($baseQuery, ['view'=>'text','meta'=>$includeMeta ? '0' : '1']));
      $exportTxtUrl    = '?' . http_build_query(array_merge($baseQuery, ['view'=>'text','meta'=>$includeMeta ? '1' : '0','export'=>'text']));
    ?>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      <!-- View tabs -->
      <a href="<?= htmlspecialchars($visualUrl, ENT_QUOTES) ?>"
         class="px-3 py-1.5 rounded-lg border text-sm shadow-sm <?= $view==='visual' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-50' ?>">
        Visual view
      </a>
      <a href="<?= htmlspecialchars($textUrl, ENT_QUOTES) ?>"
         class="px-3 py-1.5 rounded-lg border text-sm shadow-sm <?= $view==='text' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-50' ?>">
        Text tree
      </a>

      <!-- Hidden toggle -->
      <a href="<?= htmlspecialchars($toggleHiddenUrl, ENT_QUOTES) ?>"
         class="px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50 shadow-sm">
        <?= $showHidden ? 'Hide dotfiles' : 'Show dotfiles' ?>
      </a>

      <!-- Visual-only controls -->
      <?php if ($view === 'visual'): ?>
        <button id="expandAll" class="px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50 shadow-sm">
          Expand all
        </button>
        <button id="collapseAll" class="px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50 shadow-sm">
          Collapse all
        </button>
        <div class="ml-auto relative">
          <input id="search" type="text" placeholder="Filter files…"
                 class="w-64 pl-9 pr-3 py-1.5 border rounded-lg bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path>
          </svg>
        </div>
      <?php else: ?>
        <!-- Text view controls -->
        <a href="<?= htmlspecialchars($toggleMetaUrl, ENT_QUOTES) ?>"
           class="px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50 shadow-sm">
          <?= $includeMeta ? 'Hide sizes & dates' : 'Show sizes & dates' ?>
        </a>
        <button id="copyText" class="px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50 shadow-sm">
          Copy
        </button>
        <a href="<?= htmlspecialchars($exportTxtUrl, ENT_QUOTES) ?>"
           class="px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50 shadow-sm">
          Download .txt
        </a>
      <?php endif; ?>
    </div>

    <?php if ($view === 'visual'): ?>
      <section class="bg-white rounded-2xl shadow p-4">
        <?= list_tree($absPath, $showHidden, normalize_path(BASE_DIR)) ?>
      </section>
    <?php else: ?>
      <?php $textTree = build_text_tree($absPath, $reqPath, $showHidden, $includeMeta); ?>
      <section class="bg-white rounded-2xl shadow p-0 overflow-hidden">
        <div class="px-4 py-2 border-b text-sm text-gray-600">Text tree (copy/paste friendly)</div>
        <pre id="treeText" class="p-4 text-sm whitespace-pre overflow-auto font-mono"><?= htmlspecialchars($textTree, ENT_QUOTES) ?></pre>
      </section>
    <?php endif; ?>

    <footer class="mt-6 text-xs text-gray-500">
      Root: <code><?= htmlspecialchars(normalize_path(BASE_DIR), ENT_QUOTES) ?></code>
    </footer>
  </div>

  <?php if ($view === 'visual'): ?>
  <script>
    // Expand / Collapse all
    document.getElementById('expandAll').addEventListener('click', () => {
      document.querySelectorAll('details').forEach(d => d.open = true);
    });
    document.getElementById('collapseAll').addEventListener('click', () => {
      document.querySelectorAll('details').forEach(d => d.open = false);
    });

    // Simple client-side filter
    const input = document.getElementById('search');
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      const items = document.querySelectorAll('section li');

      if (!q) {
        items.forEach(li => li.classList.remove('hidden'));
        return;
      }

      // Expand all so matches are visible
      document.querySelectorAll('details').forEach(d => d.open = true);

      items.forEach(li => {
        const text = li.textContent.toLowerCase();
        li.classList.toggle('hidden', !text.includes(q));
      });
    });
  </script>
  <?php else: ?>
  <script>
    // Copy text tree
    document.getElementById('copyText').addEventListener('click', async () => {
      const pre = document.getElementById('treeText');
      const text = pre.innerText;
      try {
        await navigator.clipboard.writeText(text);
        // tiny feedback
        const btn = document.getElementById('copyText');
        const old = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = old, 1200);
      } catch {
        // Fallback: select text
        const range = document.createRange();
        range.selectNodeContents(pre);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>
