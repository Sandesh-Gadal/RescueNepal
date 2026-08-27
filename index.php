<?php
require_once __DIR__.'/includes/layout.php';

$stats=db()->query("SELECT
  SUM(is_public=1 AND status<>'draft') total,
  SUM(is_public=1 AND status<>'draft' AND type='missing' AND status NOT IN ('reunited','handed_over','closed')) active_missing,
  SUM(is_public=1 AND status<>'draft' AND type IN ('rescue_waiting','rescued')) rescued_total,
  SUM(is_public=1 AND status<>'draft' AND status IN ('reunited','handed_over','closed')) resolved_total
  FROM cases WHERE deleted_at IS NULL")->fetch();

$recentSql='SELECT '.public_case_select_sql().' FROM cases c '.public_case_join_sql()." WHERE c.deleted_at IS NULL AND c.is_public=1 AND c.status<>'draft' ORDER BY c.updated_at DESC LIMIT 4";
$recentCards=array_map('derive_public_case_card',db()->query($recentSql)->fetchAll());

page_header('Home');
?>
<section class="rescue-home-hero">
  <div class="home-hero-copy"><div class="card-kicker">RESCUE NEPAL · NATIONAL REGISTRY</div><h1><?=render_lang('Find, report and reconnect after a disaster.','विपद्पछि व्यक्ति खोज्नुहोस् र परिवारसँग पुनर्मिलन गराउनुहोस्।')?></h1><p><?=render_lang('One public place to report, search and track cases.','दर्ता, खोजी र केस ट्र्याकिङका लागि एउटै ठाउँ।')?></p></div>
  <a class="home-primary-find" href="<?=e(base_url('public_search.php'))?>"><span class="home-action-icon">⌕</span><div><strong><?=render_lang('Find My Family Member','आफ्नो परिवारको सदस्य खोज्नुहोस्')?></strong><small><?=render_lang('Search public rescue & DVI records.','उद्धार र DVI रेकर्ड खोज्नुहोस्।')?></small></div><b>→</b></a>
</section>

<div class="kpi-grid home-kpi-grid">
  <div class="kpi"><div class="value"><?=e((int)$stats['total'])?></div><div class="label"><?=render_lang('Total Public Cases','कुल सार्वजनिक केस')?></div></div>
  <div class="kpi"><div class="value"><?=e((int)$stats['active_missing'])?></div><div class="label"><?=render_lang('Active Missing','सक्रिय बेपत्ता')?></div></div>
  <div class="kpi"><div class="value"><?=e((int)$stats['rescued_total'])?></div><div class="label"><?=render_lang('Rescued / Waiting','उद्धार / प्रतीक्षामा')?></div></div>
  <div class="kpi"><div class="value"><?=e((int)$stats['resolved_total'])?></div><div class="label"><?=render_lang('Reunited / Closed','पुनर्मिलन / बन्द')?></div></div>
</div>

<section class="home-action-section"><div class="section-title compact-title"><h2><?=render_lang('What do you want to report?','के जानकारी दिन चाहनुहुन्छ?')?></h2></div>
<div class="home-action-grid-v23">
  <a class="home-action-card action-rescue" href="<?=e(base_url('rescue-request'))?>"><span>🚨</span><div><b><?=render_lang('Need Rescue','उद्धार चाहिएको छ')?></b><small><?=render_lang('Waiting for rescue now. GPS required.','अहिले उद्धारको प्रतीक्षामा। GPS आवश्यक।')?></small></div></a>
  <a class="home-action-card action-missing" href="<?=e(base_url('missing-person'))?>"><span>👤</span><div><b><?=render_lang('Report Missing Person','बेपत्ता व्यक्ति दर्ता')?></b><small><?=render_lang('Cannot be contacted or located.','सम्पर्क वा स्थान थाहा छैन।')?></small></div></a>
  <a class="home-action-card action-track" href="<?=e(base_url('track'))?>"><span>🔎</span><div><b><?=render_lang('Track a Case','केस ट्र्याक गर्नुहोस्')?></b><small><?=render_lang('Check updates with a Case ID.','केस आईडीले अपडेट हेर्नुहोस्।')?></small></div></a>
  <a class="home-action-card action-browse" href="<?=e(base_url('cases'))?>"><span>📋</span><div><b><?=render_lang('Browse Cases','केसहरू हेर्नुहोस्')?></b><small><?=render_lang('See all published cases.','सबै सार्वजनिक केसहरू हेर्नुहोस्।')?></small></div></a>
</div>
<p class="home-safety-note"><?=render_lang('Rescued-person and deceased records are registered by authorized staff only; public search shows limited, non-graphic details.','उद्धार व्यक्ति र मृतकको विवरण अधिकृत कर्मचारीले मात्र दर्ता गर्छन्; सार्वजनिक खोजीमा सीमित विवरण मात्र देखिन्छ।')?></p>
</section>

<?php if($recentCards):?>
<section class="home-recent-section">
<div class="section-title compact-title"><h2><?=render_lang('Recently Reported','भर्खरै दर्ता भएका')?></h2><a class="btn btn-ghost btn-sm" href="<?=e(base_url('cases'))?>">View All →</a></div>
<div class="cb-grid">
<?php foreach($recentCards as $card) render_public_case_card($card);?>
</div>
</section>
<?php endif;?>

<section class="home-how"><h2><?=render_lang('How it works','यसरी काम गर्छ')?></h2><div class="home-flow"><div><b>1</b><span><?=render_lang('Report / Search','दर्ता / खोजी')?></span></div><i>→</i><div><b>2</b><span><?=render_lang('Compare Records','रेकर्ड तुलना')?></span></div><i>→</i><div><b>3</b><span><?=render_lang('Verification','पुष्टि')?></span></div><i>→</i><div><b>4</b><span><?=render_lang('Reunification','पुनर्मिलन')?></span></div></div></section>
<?php page_footer(); ?>
