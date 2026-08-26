<?php use FilamentManager\Core\View; ?>
<section class="empty"><h1><?= View::e($status ?? 500) ?></h1><p><?= View::e($message ?? 'Error') ?></p><p><a class="button" href="./">FilamentManager</a></p></section>
