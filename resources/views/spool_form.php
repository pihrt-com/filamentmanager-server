<?php use FilamentManager\Core\View; ?>
<div class="toolbar"><h1><?= View::e($title) ?></h1><a href="<?= View::e($basePath) ?>/spools"><?= View::t('cancel') ?></a></div>
<form class="card" method="post" action="<?= View::e($basePath) ?><?= $spool ? '/spools/'.View::e($spool['id']) : '/spools' ?>">
<?= View::csrf() ?>
<?php if ($spool): ?><input type="hidden" name="version" value="<?= View::e($spool['version']) ?>"><?php endif; ?>
<label><?= View::t('material') ?><select name="material_id" required><option value="">—</option><?php foreach ($materials as $m): ?><option value="<?= View::e($m['id']) ?>" <?= ($spool['material_id'] ?? '') === $m['id'] ? 'selected' : '' ?>><?= View::e($m['material_type'].' · '.$m['color_name'].' · '.($m['commercial_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
<div class="grid"><label><?= View::t('original_weight') ?> (g)<input name="original_net_weight_g" type="number" step="0.01" min="0" required value="<?= View::e($spool['original_net_weight_g'] ?? 1000) ?>"></label><label><?= View::t('remaining') ?> (g)<input name="current_net_weight_g" type="number" step="0.01" min="0" required value="<?= View::e($spool['current_net_weight_g'] ?? 1000) ?>"></label></div>
<div class="grid"><label><?= View::t('tare_weight') ?> (g)<input name="tare_weight_g" type="number" step="0.01" min="0" value="<?= View::e($spool['tare_weight_g'] ?? '') ?>"></label><label><?= View::t('purchase_date') ?><input name="purchase_date" type="date" value="<?= View::e($spool['purchase_date'] ?? '') ?>"></label></div>
<div class="grid"><label>NFC UID<input name="tag_uid" value="<?= View::e($spool['tag_uid'] ?? '') ?>"></label><label>OpenPrintTag ID<input name="openprinttag_id" value="<?= View::e($spool['openprinttag_id'] ?? '') ?>"></label></div>
<label><?= View::t('batch') ?><input name="batch_number" value="<?= View::e($spool['batch_number'] ?? '') ?>"></label><label><?= View::t('notes') ?><textarea name="notes"><?= View::e($spool['notes'] ?? '') ?></textarea></label><button><?= View::t('save') ?></button>
</form>
<?php if ($spool): ?><details><summary><?= View::t('delete') ?></summary><form method="post" action="<?= View::e($basePath) ?>/spools/<?= View::e($spool['id']) ?>/delete"><?= View::csrf() ?><button class="danger-button"><?= View::t('delete') ?></button></form></details><?php endif; ?>
