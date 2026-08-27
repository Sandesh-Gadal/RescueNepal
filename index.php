<?php
require_once __DIR__.'/includes/layout.php';
page_header('Home');
?>
<section class="rescue-home-hero">
  <div class="home-hero-copy"><div class="card-kicker">RESCUE NEPAL</div><h1><?=render_lang('Find, report and reconnect after a disaster.','विपद्पछि व्यक्ति खोज्नुहोस् र परिवारसँग पुनर्मिलन गराउनुहोस्।')?></h1><p><?=render_lang('One public place to report, search and track cases.','दर्ता, खोजी र केस ट्र्याकिङका लागि एउटै ठाउँ।')?></p></div>
  <a class="home-primary-find" href="<?=e(base_url('public_search.php'))?>"><span class="home-action-icon">⌕</span><div><strong><?=render_lang('Find My Family Member','आफ्नो परिवारको सदस्य खोज्नुहोस्')?></strong><small><?=render_lang('Search public rescue & DVI records.','उद्धार र DVI रेकर्ड खोज्नुहोस्।')?></small></div><b>→</b></a>
</section>

<section class="home-action-section"><div class="section-title compact-title"><h2><?=render_lang('What do you want to report?','के जानकारी दिन चाहनुहुन्छ?')?></h2></div>
<div class="home-action-grid-v23">
  <a class="home-action-card action-rescue" href="<?=e(base_url('rescue-request'))?>"><span>🚨</span><div><b><?=render_lang('Need Rescue','उद्धार चाहिएको छ')?></b><small><?=render_lang('Waiting for rescue now. GPS required.','अहिले उद्धारको प्रतीक्षामा। GPS आवश्यक।')?></small></div></a>
  <a class="home-action-card action-missing" href="<?=e(base_url('missing-person'))?>"><span>👤</span><div><b><?=render_lang('Report Missing Person','बेपत्ता व्यक्ति दर्ता')?></b><small><?=render_lang('Cannot be contacted or located.','सम्पर्क वा स्थान थाहा छैन।')?></small></div></a>
  <a class="home-action-card action-track" href="<?=e(base_url('track'))?>"><span>🔎</span><div><b><?=render_lang('Track a Case','केस ट्र्याक गर्नुहोस्')?></b><small><?=render_lang('Check updates with a Case ID.','केस आईडीले अपडेट हेर्नुहोस्।')?></small></div></a>
</div>
<p class="home-safety-note"><?=render_lang('Rescued-person and deceased records are registered by authorized staff only; public search shows limited, non-graphic details.','उद्धार व्यक्ति र मृतकको विवरण अधिकृत कर्मचारीले मात्र दर्ता गर्छन्; सार्वजनिक खोजीमा सीमित विवरण मात्र देखिन्छ।')?></p>
</section>

<section class="home-how"><h2><?=render_lang('How it works','यसरी काम गर्छ')?></h2><div class="home-flow"><div><b>1</b><span><?=render_lang('Report / Search','दर्ता / खोजी')?></span></div><i>→</i><div><b>2</b><span><?=render_lang('Compare Records','रेकर्ड तुलना')?></span></div><i>→</i><div><b>3</b><span><?=render_lang('Verification','पुष्टि')?></span></div><i>→</i><div><b>4</b><span><?=render_lang('Reunification','पुनर्मिलन')?></span></div></div></section>
<?php page_footer(); ?>
