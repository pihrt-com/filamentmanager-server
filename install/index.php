<?php

declare(strict_types=1);

use FilamentManager\Core\Migrator;
use FilamentManager\Core\Uuid;

define('FM_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'FilamentManager\\';
    if (str_starts_with($class, $prefix)) {
        $path = FM_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) require $path;
    }
});

if (is_file(FM_ROOT . '/storage/installed.lock')) {
    http_response_code(403);
    exit('FilamentManager Server is already installed.');
}

session_name('filamentmanager_installer');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
FilamentManager\Core\SecurityHeaders::send(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

$_SESSION['install_csrf'] ??= bin2hex(random_bytes(32));
$errors = [];
$success = false;
$assetPath = '../assets/app.css';

$requirements = [
    'PHP >= 8.4' => version_compare(PHP_VERSION, '8.4.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'mbstring' => extension_loaded('mbstring'),
    'JSON' => extension_loaded('json'),
    'fileinfo' => extension_loaded('fileinfo'),
    'ZIP' => extension_loaded('zip'),
    'Writable config' => is_writable(FM_ROOT . '/config'),
    'Writable storage' => is_writable(FM_ROOT . '/storage'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['install_csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'The installer security token expired. Reload the page.';
    }
    foreach ($requirements as $name => $ok) if (!$ok) $errors[] = "Requirement not met: {$name}";

    $host = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $port = max(1, min(65535, (int) ($_POST['db_port'] ?? 3306)));
    $name = trim((string) ($_POST['db_name'] ?? ''));
    $username = trim((string) ($_POST['db_username'] ?? ''));
    $password = (string) ($_POST['db_password'] ?? '');
    $adminUsername = trim((string) ($_POST['admin_username'] ?? 'admin'));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $workspaceName = trim((string) ($_POST['workspace_name'] ?? '3D Print Farm'));
    $locale = in_array($_POST['locale'] ?? 'cs', ['cs', 'en'], true) ? (string) $_POST['locale'] : 'cs';
    $timezone = in_array($_POST['timezone'] ?? 'Europe/Prague', timezone_identifiers_list(), true) ? (string) $_POST['timezone'] : 'Europe/Prague';
    $appUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');

    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) $errors[] = 'Invalid database name.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $adminUsername)) $errors[] = 'Invalid administrator username.';
    if (strlen($adminPassword) < 12) $errors[] = 'Administrator password must contain at least 12 characters.';
    if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid administrator email address.';
    if ($appUrl === '' || !filter_var($appUrl, FILTER_VALIDATE_URL)) $errors[] = 'Invalid application URL.';
    $urlHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
    if (parse_url($appUrl, PHP_URL_SCHEME) !== 'https' && !in_array($urlHost, ['localhost', '127.0.0.1', '::1'], true)) $errors[] = 'HTTPS is required for production installations.';

    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            Migrator::run($pdo);
            $workspaceId = Uuid::v4();
            $userId = Uuid::v4();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO workspaces (id, name, locale, timezone) VALUES (?, ?, ?, ?)');
            $stmt->execute([$workspaceId, $workspaceName, $locale, $timezone]);
            $stmt = $pdo->prepare('INSERT INTO users (id, workspace_id, username, email, display_name, password_hash, role, locale) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $workspaceId, $adminUsername, $adminEmail ?: null, $adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin', $locale]);
            $pdo->commit();

            $config = [
                'app_url' => $appUrl,
                'app_key' => bin2hex(random_bytes(32)),
                'locale' => $locale,
                'timezone' => $timezone,
                'database' => ['host' => $host, 'port' => $port, 'name' => $name, 'username' => $username, 'password' => $password, 'charset' => 'utf8mb4'],
            ];
            $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
            $tmp = FM_ROOT . '/config/local.php.tmp';
            if (file_put_contents($tmp, $php, LOCK_EX) === false || !rename($tmp, FM_ROOT . '/config/local.php')) throw new RuntimeException('Cannot write local configuration.');
            file_put_contents(FM_ROOT . '/storage/installed.lock', json_encode(['installedAt' => gmdate('c'), 'version' => trim((string) file_get_contents(FM_ROOT . '/VERSION'))], JSON_PRETTY_PRINT), LOCK_EX);
            (new FilamentManager\Services\FileSecurityService())->harden();
            $success = true;
            session_destroy();
            register_shutdown_function(static function (): void {
                $remove = static function (string $path) use (&$remove): void {
                    if (!is_dir($path)) return;
                    foreach (scandir($path) ?: [] as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        $item = $path . DIRECTORY_SEPARATOR . $entry;
                        is_dir($item) ? $remove($item) : @unlink($item);
                    }
                    @rmdir($path);
                };
                $remove(FM_ROOT . '/public/install');
                $remove(FM_ROOT . '/install');
            });
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FilamentManager Server Installer</title>
    <link rel="stylesheet" href="<?= h($assetPath) ?>">
</head>
<body class="installer"><main class="install-card">
    <h1>FilamentManager Server</h1><p class="muted">Secure installation</p>
    <?php if ($success): ?>
        <div class="notice success"><strong>Installation completed.</strong> The installer is now locked.</div>
        <p><a class="button" href="../">Open FilamentManager</a></p>
        <p class="muted">For defense in depth, delete the <code>install</code> directory from the server. The application remains protected by <code>storage/installed.lock</code> if deletion is not permitted.</p>
    <?php else: ?>
        <?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
        <section><h2>Environment</h2><ul class="checklist"><?php foreach ($requirements as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✕' ?> <?= h($label) ?></li><?php endforeach; ?></ul></section>
        <form method="post" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= h($_SESSION['install_csrf']) ?>">
            <fieldset><legend>Server</legend>
                <label>Application URL<input name="app_url" type="url" required value="<?= h((string) ($_POST['app_url'] ?? ((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')))) ?>"></label>
                <label>Workspace name<input name="workspace_name" required maxlength="120" value="<?= h((string) ($_POST['workspace_name'] ?? '3D Print Farm')) ?>"></label>
                <label>Language<select name="locale"><option value="cs">Čeština</option><option value="en">English</option></select></label>
                <label>Time zone<input name="timezone" required value="<?= h((string) ($_POST['timezone'] ?? 'Europe/Prague')) ?>"></label>
            </fieldset>
            <fieldset><legend>Database</legend>
                <div class="grid"><label>Host<input name="db_host" required value="<?= h((string) ($_POST['db_host'] ?? 'localhost')) ?>"></label><label>Port<input name="db_port" type="number" required value="<?= h((string) ($_POST['db_port'] ?? '3306')) ?>"></label></div>
                <label>Database name<input name="db_name" required value="<?= h((string) ($_POST['db_name'] ?? '')) ?>"></label>
                <label>Database user<input name="db_username" required value="<?= h((string) ($_POST['db_username'] ?? '')) ?>"></label>
                <label>Database password<input name="db_password" type="password" value=""></label>
            </fieldset>
            <fieldset><legend>First administrator</legend>
                <label>Username<input name="admin_username" required minlength="3" maxlength="80" value="<?= h((string) ($_POST['admin_username'] ?? 'admin')) ?>"></label>
                <label>Email<input name="admin_email" type="email" value="<?= h((string) ($_POST['admin_email'] ?? '')) ?>"></label>
                <label>Password<input name="admin_password" type="password" required minlength="12" autocomplete="new-password"><small>At least 12 characters.</small></label>
            </fieldset>
            <button type="submit">Install server</button>
        </form>
    <?php endif; ?>
</main></body></html>
