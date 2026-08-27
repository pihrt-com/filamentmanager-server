<?php
use FilamentManager\Core\Session;
use FilamentManager\Core\View;
$user = $app?->auth()->user();
$flash = Session::pullFlash('success');
$flashError = Session::pullFlash('error');
$requestPath = FilamentManager\Core\Request::capture()->path();
$basePath ??= FilamentManager\Core\Request::capture()->basePath();
?>
<!doctype html><html lang="<?= View::e($user['locale'] ?? $app?->config('locale', 'cs')) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark">
<title><?= View::e($title ?? View::t('app_name')) ?></title><link rel="stylesheet" href="<?= View::e($basePath ?? '') ?>/assets/app.css">
</head><body>
<?php if ($user): ?><header class="topbar"><a class="brand" href="<?= View::e($basePath ?? '') ?>/">◉ FilamentManager</a><nav>
<a class="nav-button<?= $requestPath==='/'||str_starts_with($requestPath,'/printers')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/"><?= View::t('dashboard') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/spools')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/spools"><?= View::t('spools') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/materials')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/materials"><?= View::t('materials') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/locations')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/locations"><?= View::t('warehouse') ?></a>
<?php if ($user['role'] === 'admin'): ?><a class="nav-button<?= str_starts_with($requestPath,'/admin/users')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/admin/users"><?= View::t('users') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/admin/audit')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/admin/audit"><?= View::t('audit') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/admin/settings')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/admin/settings"><?= View::t('settings') ?></a><?php endif; ?>
</nav><form method="post" action="<?= View::e($basePath ?? '') ?>/logout"><?= View::csrf() ?><button class="link-button"><?= View::t('logout') ?></button></form></header><?php endif; ?>
<main class="container"><?php if ($flash): ?><div class="notice success"><?= View::e($flash) ?></div><?php endif; ?><?php if ($flashError): ?><div class="notice error"><?= View::e($flashError) ?></div><?php endif; ?><?= $content ?></main>
<footer><span>FilamentManager Server <?= View::e(trim((string) @file_get_contents(FM_ROOT . '/VERSION'))) ?></span><a href="https://github.com/pihrt-com/filamentmanager-server" rel="noopener">GitHub</a><span>Martin Pihrt · <a href="https://www.pihrt.com" rel="noopener">pihrt.com</a></span></footer>
</body></html>
