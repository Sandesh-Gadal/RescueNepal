<?php
require_once __DIR__.'/includes/layout.php';
page_header('Home');
?>
<section class="rescue-home-hero">
  <div class="home-hero-copy"><div class="card-kicker">RESCUE NEPAL</div><h1><?=render_lang('Find, report and reconnect people after disasters.','विपद्पछि व्यक्ति खोज्नुहोस्, विवरण दिनुहोस् र परिवारसँग पुनर्मिलन गराउनुहोस्।')?></h1><p><?=render_lang('One public entry point for missing-person reports, rescue requests, rescued-person registration, rescued/dead-body family search and case tracking.','बेपत्ता व्यक्ति दर्ता, उद्धार अनुरोध, उद्धार व्यक्ति दर्ता, उद्धार व्यक्ति/शव परिवार खोजी र केस ट्र्याकिङका लागि एउटै सार्वजनिक प्रवेशद्वार।')?></p></div>
  <a class="home-primary-find" href="<?=e(base_url('public_search.php'))?>"><span class="home-action-icon">⌕</span><div><strong><?=render_lang('Find My Family Member','आफ्नो परिवारको सदस्य खोज्नुहोस्')?></strong><small><?=render_lang('Search rescued persons and public non-graphic dead-body trace records.','उद्धार व्यक्ति र सार्वजनिक गैर-ग्राफिक शव ट्रेस रेकर्ड खोज्नुहोस्।')?></small></div><b>→</b></a>
</section>

<section class="home-action-section"><div class="section-title compact-title"><div><h2><?=render_lang('What do you want to report?','के जानकारी दिन चाहनुहुन्छ?')?></h2><div class="muted"><?=render_lang('Choose the situation that best matches what is happening now.','अहिलेको अवस्थासँग मिल्ने विकल्प छान्नुहोस्।')?></div></div></div>
<div class="home-action-grid-v23">
  <a class="home-action-card action-rescue" href="<?=e(base_url('rescue-request'))?>"><span>🚨</span><div><b><?=render_lang('Need Rescue','उद्धार चाहिएको छ')?></b><small><?=render_lang('Someone is currently waiting for rescue. GPS required.','कोही अहिले उद्धारको प्रतीक्षामा छन्। GPS आवश्यक।')?></small></div></a>
  <a class="home-action-card action-missing" href="<?=e(base_url('missing-person'))?>"><span>👤</span><div><b><?=render_lang('Report Missing Person','बेपत्ता व्यक्ति दर्ता')?></b><small><?=render_lang('A person cannot be contacted or located.','व्यक्तिसँग सम्पर्क हुन सकेको छैन वा स्थान थाहा छैन।')?></small></div></a>
  <a class="home-action-card action-rescued" href="<?=e(base_url('rescued-person'))?>"><span>✅</span><div><b><?=render_lang('Report a Rescued Person','उद्धार गरिएको व्यक्ति दर्ता')?></b><small><?=render_lang('For officials, rescuers, hospitals and shelter houses: register a person already rescued.','अधिकारी, उद्धारकर्ता, अस्पताल वा आश्रयस्थलले उद्धार भइसकेका व्यक्ति दर्ता गर्न।')?></small></div></a>
  <a class="home-action-card action-track" href="<?=e(base_url('track'))?>"><span>🔎</span><div><b><?=render_lang('Track a Case','केस ट्र्याक गर्नुहोस्')?></b><small><?=render_lang('Use a Rescue Nepal Case ID to see verified public updates.','Rescue Nepal केस आईडीबाट प्रमाणित सार्वजनिक अपडेट हेर्नुहोस्।')?></small></div></a>
</div></section>

<section class="card home-safety-note"><div><b><?=render_lang('Recovered deceased / DVI records','फेला परेको मृतक / DVI रेकर्ड')?></b><p><?=render_lang('Recovered deceased persons are registered only by authorized staff. Public family search shows only limited, non-graphic identifying information. DNA, fingerprints, post-mortem details and graphic photographs remain restricted.','फेला परेका मृतक व्यक्तिको विवरण अधिकृत कर्मचारीले मात्र दर्ता गर्छन्। सार्वजनिक परिवार खोजीमा सीमित, गैर-ग्राफिक पहिचान विवरण मात्र देखाइन्छ। DNA, फिंगरप्रिन्ट, पोस्टमार्टम र ग्राफिक फोटो प्रतिबन्धित रहन्छन्।')?></p></div></section>

<section class="home-how"><h2><?=render_lang('How the system connects cases','प्रणालीले केसहरू कसरी जोड्छ')?></h2><div class="home-flow"><div><b>1</b><span><?=render_lang('Report / Search','दर्ता / खोजी')?></span></div><i>→</i><div><b>2</b><span><?=render_lang('Compare Records','रेकर्ड तुलना')?></span></div><i>→</i><div><b>3</b><span><?=render_lang('Authority Verification','अधिकृत पुष्टि')?></span></div><i>→</i><div><b>4</b><span><?=render_lang('Reunification / Handover','पुनर्मिलन / हस्तान्तरण')?></span></div></div></section>
<?php page_footer(); ?>
