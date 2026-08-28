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
<title><?= View::e($title ?? View::t('app_name')) ?></title><link rel="icon" type="image/png" href="<?= View::e($basePath ?? '') ?>/assets/app-icon.png"><link rel="stylesheet" href="<?= View::e($basePath ?? '') ?>/assets/app.css">
</head><body>
<?php if ($user): ?><header class="topbar"><a class="brand" href="<?= View::e($basePath ?? '') ?>/"><img class="brand-icon" src="<?= View::e($basePath ?? '') ?>/assets/app-icon.png" alt=""><span>FilamentManager</span></a><nav>
<a class="nav-button<?= $requestPath==='/'||str_starts_with($requestPath,'/printers')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/"><?= View::t('dashboard') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/spools')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/spools"><?= View::t('spools') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/materials')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/materials"><?= View::t('materials') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/locations')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/locations"><?= View::t('warehouse') ?></a>
<?php if ($user['role'] === 'admin'): ?><a class="nav-button<?= str_starts_with($requestPath,'/admin/users')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/admin/users"><?= View::t('users') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/admin/audit')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/admin/audit"><?= View::t('audit') ?></a><a class="nav-button<?= str_starts_with($requestPath,'/admin/settings')?' active':'' ?>" href="<?= View::e($basePath ?? '') ?>/admin/settings"><?= View::t('settings') ?></a><?php endif; ?>
</nav><div class="account"><span class="account-name"><?= View::t('signed_in_as') ?> <strong><?= View::e($user['display_name']?:$user['username']) ?></strong> · <?= View::t('role_'.$user['role']) ?></span><form method="post" action="<?= View::e($basePath ?? '') ?>/logout"><?= View::csrf() ?><button class="danger-button action-button"><?= View::t('logout') ?></button></form></div></header><?php endif; ?>
<main class="container"><?php if ($flash): ?><div class="notice success"><?= View::e($flash) ?></div><?php endif; ?><?php if ($flashError): ?><div class="notice error"><?= View::e($flashError) ?></div><?php endif; ?><?= $content ?></main>
<footer><div class="footer-row"><span>FilamentManager Server <?= View::e(trim((string) @file_get_contents(FM_ROOT . '/VERSION'))) ?></span><a href="https://github.com/pihrt-com/filamentmanager-server" rel="noopener">GitHub</a><span>Martin Pihrt · <a href="https://www.pihrt.com" rel="noopener">pihrt.com</a></span></div><div class="footer-row"><span><?= View::t('mobile_app') ?>:</span><a href="https://github.com/pihrt-com/filamentmanager-mobile-app" rel="noopener">GitHub</a><a href="https://play.google.com/store/apps/details?id=com.pihrt.filamentmanager.mobile" rel="noopener">Google Play</a></div></footer>
</body></html>
