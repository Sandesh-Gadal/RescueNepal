<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/auth.php';
$staffRoute=!empty($_GET['staff']);
$existingAdmin=current_admin();
if($staffRoute){$admin=require_admin();}else{if($existingAdmin) redirect(base_url('success.php?staff=1&token='.urlencode((string)($_GET['token']??''))));$admin=null;}
$token=$_GET['token']??'';
$stmt=db()->prepare('SELECT c.*,(SELECT latitude FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) latitude,(SELECT longitude FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) longitude,(SELECT accuracy FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) gps_accuracy FROM cases c WHERE c.public_token=? AND c.deleted_at IS NULL LIMIT 1');
$stmt->execute([$token]);$case=$stmt->fetch();
if(!$case){http_response_code(404);exit('Case not found');}
$isRescue=$case['type']==='rescue_waiting';$isRescued=$case['type']==='rescued';$rescuedDetail=null;if($isRescued){$rs=db()->prepare('SELECT * FROM rescued_person_details WHERE case_id=?');$rs->execute([$case['id']]);$rescuedDetail=$rs->fetch();}
page_header('Submitted',$staffRoute);
?>
<div class="success-hero"><div><h1><?=render_lang('Report Submitted Successfully','विवरण सफलतापूर्वक पेश भयो')?></h1><p><?=render_lang('Save the Case ID and keep the contact phone reachable.','Case ID सुरक्षित राख्नुहोस् र सम्पर्क फोन उपलब्ध राख्नुहोस्।')?></p></div></div>

<div class="receipt receipt-enhanced" id="receiptCard">
  <div class="receipt-top"><div><div class="small muted">CASE ID / केस ID</div><div class="case-code"><?=e($case['case_code'])?></div></div><span class="case-type-badge <?=e($case['type'])?>"><?=e(case_type_label($case['type']))?></span></div>
  <div class="receipt-grid">
    <div class="receipt-field"><span>Name / नाम</span><b><?=e($case['name'])?></b></div>
    <div class="receipt-field"><span>Mobile / मोबाइल</span><b><?=e($case['family_contact_phone'])?></b></div>
    <?php if($isRescue):?>
      <div class="receipt-field"><span>Total Persons / जम्मा व्यक्ति</span><b><?=e($case['total_persons']??'')?></b></div>
      <div class="receipt-field"><span>Address / ठेगाना</span><b><?=e($case['last_seen_address'])?></b></div>
      <div class="receipt-field full"><span>Situation / अवस्था</span><b><?=nl2br(e($case['rescue_description']))?></b></div>
      <div class="receipt-field"><span>GPS / स्थान</span><b><?=e($case['latitude'])?>, <?=e($case['longitude'])?></b></div>
      <div class="receipt-field"><span>GPS Accuracy</span><b><?=$case['gps_accuracy']!==null?e(round((float)$case['gps_accuracy']).' m'):'—'?></b></div>
    <?php elseif($isRescued):?>
      <div class="receipt-field"><span>Identity / पहिचान</span><b><?=e(ucwords(str_replace('_',' ',(string)($rescuedDetail['identity_status']??'unknown'))))?></b></div>
      <div class="receipt-field"><span>Condition / अवस्था</span><b><?=e(case_condition_label($case['current_condition']?:'unknown'))?></b></div>
      <div class="receipt-field full"><span>Rescue Location / उद्धार स्थान</span><b><?=e($rescuedDetail['rescue_location']??$case['where_found'])?></b></div>
      <div class="receipt-field full"><span>Current Institution / हालको संस्था</span><b><?=e($rescuedDetail['current_institution_name']??$case['current_location'])?></b></div>
      <div class="receipt-field"><span>Institution Contact</span><b><?=e($rescuedDetail['institution_contact_name']??$case['family_contact_name'])?></b></div>
      <div class="receipt-field"><span>Institution Phone</span><b><?=e($rescuedDetail['institution_contact_phone']??$case['family_contact_phone'])?></b></div>
    <?php else:?>
      <div class="receipt-field"><span>Age / उमेर</span><b><?=e($case['age']??'—')?></b></div>
      <div class="receipt-field"><span>Gender / लिङ्ग</span><b><?=e($case['gender'])?></b></div>
      <div class="receipt-field"><span>Home / घर</span><b><?=e($case['from_location']?:'—')?></b></div>
      <div class="receipt-field"><span>Vehicle / सवारी</span><b><?=e($case['vehicle_no']?:'—')?></b></div>
      <div class="receipt-field"><span>Last Contact BS / अन्तिम सम्पर्क</span><b><?=e($case['last_contacted_bs'])?> <?=e(substr((string)$case['last_contacted_time'],0,5))?></b></div>
      <div class="receipt-field"><span>Last Contact Address</span><b><?=e($case['last_seen_address'])?></b></div>
      <div class="receipt-field"><span>Family Contact</span><b><?=e($case['family_contact_name'])?></b></div>
      <div class="receipt-field"><span>Other family also missing?</span><b><?=!empty($case['other_family_members_missing'])?'Yes / हो':'No / होइन'?></b></div>
    <?php endif;?>
    <?php if(trim((string)$case['additional_notes'])!==''):?><div class="receipt-field full"><span>Additional Note / थप नोट</span><b><?=nl2br(e($case['additional_notes']))?></b></div><?php endif;?>
  </div>
  <div class="receipt-footer-line"><span>Submission saved / विवरण सुरक्षित</span><span>Status: <?=e(str_replace('_',' ',$case['status']))?></span></div>
</div>

<div class="receipt-actions no-print">
  <a class="btn btn-primary" href="<?=e(base_url('receipt.php?'.http_build_query(array_filter(['staff'=>$staffRoute?1:null,'token'=>$token]))))?>">Screenshot View / स्क्रिनसट दृश्य</a>
  <button class="btn btn-secondary" type="button" id="copyCase">Copy Case ID</button>
  <?php if($staffRoute):?><a class="btn btn-ghost" href="<?=e(base_url('admin/case.php?id='.$case['id']))?>">Open Admin Case</a><?php else:?><a class="btn btn-ghost" href="<?=e(base_url('track.php?code='.urlencode($case['case_code'])))?>">Track Case</a><?php endif;?>
  <button class="btn btn-ghost" onclick="window.print()">Print / Save PDF</button>
  <?php if($admin):?><a class="btn btn-success" href="<?=e(base_url('admin/operator_help.php'))?>">Enter Another Report</a><?php else:?><a class="btn btn-success" href="<?=e(base_url())?>">New Report</a><?php endif;?>
</div>
<div class="screenshot-note no-print"><b><?=render_lang('After submitting:','पेश गरेपछि:')?></b> <?=render_lang('Open Screenshot View, then use your phone/computer screenshot button. The clean page contains the Case ID and submitted details.','Screenshot View खोल्नुहोस् र मोबाइल/कम्प्युटरको screenshot प्रयोग गर्नुहोस्। सफा पृष्ठमा Case ID र पेश गरिएको विवरण देखिन्छ।')?></div>
<?php if($isRescue):?><div class="alert alert-warning"><?=render_lang('Do not rely only on this registry for urgent rescue. Continue contacting local authorities and emergency responders.','तत्काल उद्धारका लागि यो रजिष्ट्रिमा मात्र निर्भर नहुनुहोस्। स्थानीय निकाय र आपतकालीन उद्धार सेवासँग पनि सम्पर्क जारी राख्नुहोस्।')?></div><?php endif;?>
<script>document.getElementById('copyCase')?.addEventListener('click',async function(){try{await navigator.clipboard.writeText('<?=e($case['case_code'])?>');this.textContent='Copied';}catch(e){prompt('Copy Case ID','<?=e($case['case_code'])?>');}});</script>
<?php page_footer(); ?>
