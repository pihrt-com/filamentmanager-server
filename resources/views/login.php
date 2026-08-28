<?php use FilamentManager\Core\View; ?>
<section class="login-card"><a class="login-brand" href="<?= View::e($basePath ?? '') ?>/"><img src="<?= View::e($basePath ?? '') ?>/assets/app-icon.png" alt=""><h1>FilamentManager</h1></a><p class="muted">Server</p>
<?php if (!empty($error)): ?><div class="notice error"><?= View::e($error) ?></div><?php endif; ?>
<form method="post" action="<?= View::e($basePath ?? '') ?>/login"><?= View::csrf() ?>
<label><?= View::t('username') ?><input name="username" required autocomplete="username" autofocus></label>
<label><?= View::t('password') ?><input name="password" type="password" required autocomplete="current-password"></label>
<button type="submit"><?= View::t('login') ?></button></form></section>
