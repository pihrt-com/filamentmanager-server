<?php use FilamentManager\Core\View; ?>
<h1><?= View::t('help') ?></h1>
<div class="cards help-cards">
<section class="card help-intro"><h2><?= View::t('help_how_it_works') ?></h2><p><?= View::t('help_intro') ?></p><p class="help-workflow"><strong><?= View::t('help_workflow') ?></strong></p></section>
<?php foreach (range(1, 7) as $step): ?>
<section class="card help-step"><span class="help-step-number" aria-hidden="true"><?= $step ?></span><h2><?= View::t('help_step_'.$step.'_title') ?></h2><p><?= View::t('help_step_'.$step.'_text') ?></p></section>
<?php endforeach; ?>
<section class="card help-concepts"><h2><?= View::t('help_terms') ?></h2><dl><dt><?= View::t('material') ?></dt><dd><?= View::t('help_material_definition') ?></dd><dt><?= View::t('spool') ?></dt><dd><?= View::t('help_spool_definition') ?></dd><dt><?= View::t('storage_location') ?></dt><dd><?= View::t('help_location_definition') ?></dd><dt><?= View::t('slot') ?></dt><dd><?= View::t('help_slot_definition') ?></dd></dl></section>
<section class="card help-future"><h2><?= View::t('help_future') ?></h2><p><?= View::t('help_future_gcode') ?></p><p><?= View::t('help_openprinttag') ?></p></section>
</div>
