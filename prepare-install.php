<?php

declare(strict_types=1);

// Upload the complete release, open this file once, delete it, and continue
// through the displayed link to the installer.
$root = __DIR__;
$lock = $root . '/storage/installed.lock';
if (is_file($lock)) {
    http_response_code(403);
    exit('FilamentManager is already installed. Delete prepare-install.php from the server.');
}

$failures = [];
$setMode = static function (string $path, int $mode) use (&$failures): void {
    if (!@chmod($path, $mode)) $failures[] = str_replace('\\', '/', substr($path, strlen(__DIR__) + 1)) ?: '.';
};

// Before installation Apache must be able to traverse the uploaded tree and
// read .htaccess, PHP, CSS and other release files.
$setMode($root, 0755);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    $path = $item->getPathname();
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    if ($item->isLink() || $relative === '.git' || str_starts_with($relative, '.git/')) continue;
    $setMode($path, $item->isDir() ? 0755 : 0644);
}

foreach ([$root . '/config', $root . '/storage'] as $writable) {
    if (is_dir($writable) && !is_writable($writable)) $failures[] = str_replace('\\', '/', substr($writable, strlen($root) + 1)) . '/ is not writable by PHP';
}

if ($failures !== []) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Some permissions could not be prepared:\n- " . implode("\n- ", array_unique($failures));
    echo "\n\nCorrect the owner or permissions in the hosting panel, then reload this page.";
    exit;
}

$base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$installerUrl = htmlspecialchars($base . '/install/', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>FilamentManager preparation</title>
<style>body{font:16px system-ui;max-width:48rem;margin:3rem auto;padding:0 1rem;color:#172033}main{border:1px solid #ccd3df;border-radius:12px;padding:2rem}.ok{color:#08783e}.button{display:inline-block;background:#3157d5;color:#fff;padding:.7rem 1rem;border-radius:7px;text-decoration:none}code{background:#eef1f6;padding:.15rem .3rem}</style>
</head><body><main><h1 class="ok">Permissions prepared</h1>
<p>Release directories are readable and traversable; release files are readable by the web server. PHP can write to <code>config/</code> and <code>storage/</code>.</p>
<p><strong>Delete <code>prepare-install.php</code> from the server now.</strong></p>
<p><a class="button" href="<?= $installerUrl ?>">Continue to installer</a></p>
</main></body></html>
