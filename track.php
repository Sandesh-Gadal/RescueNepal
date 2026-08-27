<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/auth.php';
$adminPreview=!empty($_GET['admin_preview']);
$previewAdmin=$adminPreview?require_admin():null;
$code=strtoupper(trim($_GET['code']??''));
if(!$adminPreview && $code!==''){
    $reqPath=(string)(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)??'');
    $basePath=rtrim((string)(parse_url(app_config()['base_url']??'',PHP_URL_PATH)??''),'/');
    if(rtrim($reqPath,'/')===$basePath.'/track') redirect(base_url('track.php?code='.urlencode($code)),302);
}
$case=null;$loc=null;$updates=[];$latestGps=null;$hasAnyUpdates=false;$rescuedDetail=null;$deceasedDetail=null;
$publicStatus='open';$publicCondition=null;$publicLocation=null;$publicWhereFound=null;$publicShiftedTo=null;$publicNote=null;$publicUpdatedAt=null;
if($adminPreview){
 $previewId=(int)($_GET['id']??0);
 $stmt=db()->prepare('SELECT * FROM cases WHERE id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$previewId]);$case=$stmt->fetch();
 if($case) $code=$case['case_code'];
}
if($code){
 if(!$adminPreview){$stmt=db()->prepare('SELECT * FROM cases WHERE case_code=? AND deleted_at IS NULL AND is_public=1 LIMIT 1');$stmt->execute([$code]);$case=$stmt->fetch();}
 if($case){
   $cnt=db()->prepare('SELECT COUNT(*) FROM case_updates WHERE case_id=?');$cnt->execute([$case['id']]);$hasAnyUpdates=(int)$cnt->fetchColumn()>0;
   $u=db()->prepare('SELECT u.*,a.name admin_name,a.office_name FROM case_updates u LEFT JOIN admins a ON a.id=u.admin_id WHERE u.case_id=? AND u.public_visible=1 ORDER BY u.created_at DESC,u.id DESC');$u->execute([$case['id']]);$updates=$u->fetchAll();
   $publicStatus=$case['status'];$publicLocation=$case['last_seen_address'];$publicUpdatedAt=$case['created_at'];
   if($updates){$publicStatus=$updates[0]['status_after'];$publicUpdatedAt=$updates[0]['created_at'];foreach($updates as $pu){if($publicCondition===null&&!empty($pu['condition_after']))$publicCondition=$pu['condition_after'];if($publicLocation===$case['last_seen_address']&&!empty($pu['current_location']))$publicLocation=$pu['current_location'];if($publicWhereFound===null&&!empty($pu['where_found']))$publicWhereFound=$pu['where_found'];if($publicShiftedTo===null&&!empty($pu['shifted_to']))$publicShiftedTo=$pu['shifted_to'];if($publicNote===null&&!empty($pu['note']))$publicNote=$pu['note'];}}
   foreach($updates as $x){if($x['latitude']!==null&&$x['longitude']!==null){$latestGps=['lat'=>(float)$x['latitude'],'lng'=>(float)$x['longitude'],'label'=>$x['current_location']?:($x['where_found']?:'Latest case location')];break;}}
   if($case['type']==='rescue_waiting'){$s=db()->prepare('SELECT * FROM rescue_locations WHERE case_id=? ORDER BY id DESC LIMIT 1');$s->execute([$case['id']]);$loc=$s->fetch(); if(!$latestGps&&$loc)$latestGps=['lat'=>(float)$loc['latitude'],'lng'=>(float)$loc['longitude'],'label'=>$loc['address']?:'Reported rescue location'];}
   elseif($case['type']==='rescued'){$s=db()->prepare('SELECT * FROM rescued_person_details WHERE case_id=?');$s->execute([$case['id']]);$rescuedDetail=$s->fetch();if($rescuedDetail){$publicLocation=$rescuedDetail['current_institution_name']?:($rescuedDetail['current_institution_address']?:$rescuedDetail['rescue_location']);if(!$latestGps&&$rescuedDetail['current_latitude']!==null&&$rescuedDetail['current_longitude']!==null)$latestGps=['lat'=>(float)$rescuedDetail['current_latitude'],'lng'=>(float)$rescuedDetail['current_longitude'],'label'=>$rescuedDetail['current_institution_name']?:'Current location'];elseif(!$latestGps&&$rescuedDetail['rescue_latitude']!==null&&$rescuedDetail['rescue_longitude']!==null)$latestGps=['lat'=>(float)$rescuedDetail['rescue_latitude'],'lng'=>(float)$rescuedDetail['rescue_longitude'],'label'=>$rescuedDetail['rescue_location']];}}
   elseif($case['type']==='deceased'){$s=db()->prepare('SELECT * FROM deceased_details WHERE case_id=?');$s->execute([$case['id']]);$deceasedDetail=$s->fetch();if($deceasedDetail){$publicLocation=$deceasedDetail['recovery_location'];if(!$latestGps&&$deceasedDetail['recovery_latitude']!==null&&$deceasedDetail['recovery_longitude']!==null)$latestGps=['lat'=>(float)$deceasedDetail['recovery_latitude'],'lng'=>(float)$deceasedDetail['recovery_longitude'],'label'=>$deceasedDetail['recovery_location']];}}
 }
}
page_header($adminPreview?'Public Case Preview':'Track Case',$adminPreview);
?>
<?php if($adminPreview):?><div class="alert alert-info"><b>Admin public-view preview.</b> This page shows only information that would be visible to the public.</div><?php else:?><section class="track-hero"><div><h1><?=render_lang('Track Rescue Nepal Case','रेस्क्यु नेपाल केस ट्र्याक गर्नुहोस्')?></h1><p><?=render_lang('Enter the Case ID to see the latest public status and verified updates.','पछिल्लो सार्वजनिक स्थिति र प्रमाणित अपडेट हेर्न केस आईडी राख्नुहोस्।')?></p></div></section>
<form class="track-search" method="get" action="<?=e(base_url('track.php'))?>"><input name="code" value="<?=e($code)?>" placeholder="CASE-2026-000001" autocomplete="off" required><button class="btn btn-primary btn-lg">Search / खोज्नुहोस्</button></form><?php endif;?>
<?php if(!$adminPreview&&$code&&!$case): ?><div class="alert alert-danger track-message"><b>Case not found / केस फेला परेन</b><br><span class="small">Check the Case ID and try again.</span></div><?php endif; ?>
<?php if($case):
$displayLocation=$publicLocation?:($publicWhereFound?:$case['last_seen_address']);
$publicTypeLabel=case_type_label($case['type']);
$publicName=$case['name'];$showPhoto=!empty($case['thumb_url']);$publicContact=$case['family_contact_phone'];$publicSub='';
if($case['type']==='rescued'&&$rescuedDetail){if($rescuedDetail['identity_status']==='unknown'||$publicName==='')$publicName='Unknown Rescued Person / पहिचान नखुलेको उद्धार व्यक्ति';$showPhoto=$showPhoto&&!empty($rescuedDetail['public_photo_allowed']);$publicContact=$rescuedDetail['institution_office_phone']?:($rescuedDetail['institution_contact_phone']?:'');$publicSub=trim(($rescuedDetail['estimated_age_min']||$rescuedDetail['estimated_age_max']?'Approx. age '.($rescuedDetail['estimated_age_min']?:'?').'–'.($rescuedDetail['estimated_age_max']?:'?'):'').(!empty($case['gender'])?' · '.$case['gender']:''));}
elseif($case['type']==='deceased'&&$deceasedDetail){$showPhoto=false;$publicContact='';$publicName=($deceasedDetail['identity_status']==='confirmed'&&!empty($deceasedDetail['public_identity_release'])&&!empty($deceasedDetail['official_identity_name']))?$deceasedDetail['official_identity_name']:'Unidentified Deceased Person / पहिचान नखुलेको शव';$publicSub=trim(($deceasedDetail['estimated_age_min']||$deceasedDetail['estimated_age_max']?'Approx. age '.($deceasedDetail['estimated_age_min']?:'?').'–'.($deceasedDetail['estimated_age_max']?:'?'):'').(!empty($case['gender'])?' · '.$case['gender']:''));}
?>
<div class="public-case-head">
 <div class="public-case-id"><span><?=e(strtoupper($publicTypeLabel))?></span><strong><?=e($case['case_code'])?></strong><small>Registered <?=e($case['created_at'])?></small></div>
 <div class="public-status-badge status-<?=e($publicStatus)?>"><div><small>Current Status / हालको स्थिति</small><b><?=public_status_html($publicStatus)?></b></div></div>
</div>

<div class="public-summary-grid">
 <div class="public-person-card card">
   <div class="public-person-top"><?php if($showPhoto):?><img src="<?=e(base_url($case['thumb_url']))?>" alt="Case photo"><?php else:?><div class="person-placeholder"><?= $case['type']==='deceased'?'Restricted':'No photo' ?></div><?php endif;?><div><div class="card-kicker">PERSON / व्यक्ति</div><h2><?=e($publicName)?></h2><?php if($case['type']==='missing'&&$case['age']):?><div class="muted">Age <?=e($case['age'])?><?=!empty($case['gender'])?' · '.e($case['gender']):''?></div><?php elseif($publicSub):?><div class="muted"><?=e($publicSub)?></div><?php endif;?></div></div>
   <?php if($publicContact):?><div class="public-contact-line"><div><small><?= $case['type']==='rescued'?'Institution Contact / संस्था सम्पर्क':'Contact / सम्पर्क' ?></small><b><?=e($publicContact)?></b></div></div><?php elseif($case['type']==='deceased'):?><div class="alert alert-info small">Graphic photographs, forensic records, personal officer contact details, DNA and post-mortem information are not shown publicly.</div><?php endif;?>
 </div>
 <div class="latest-state-card card">
   <h2>Current Situation / हालको अवस्था</h2>
   <div class="latest-state-list">
    <div><span>Condition / अवस्था</span><b><?= $publicCondition?public_condition_html($publicCondition):'Not confirmed / पुष्टि छैन' ?></b></div>
    <div><span>Current Location / हालको स्थान</span><b><?=e($displayLocation?:'Not yet updated / अद्यावधिक छैन')?></b></div>
    <?php if($publicWhereFound):?><div><span>Where Found / कहाँ फेला पर्यो</span><b><?=e($publicWhereFound)?></b></div><?php endif;?>
    <?php if($publicShiftedTo):?><div class="important-row"><span>Shifted To / कहाँ सारियो</span><b><?=e($publicShiftedTo)?></b></div><?php endif;?>
    <div><span>Last Updated / पछिल्लो अपडेट</span><b><?=e($publicUpdatedAt?:$case['created_at'])?></b></div>
   </div>
   <?php if($publicNote):?><div class="latest-public-note"><?=nl2br(e($publicNote))?></div><?php endif;?>
 </div>
</div>

<?php if($latestGps):$mapQ=rawurlencode($latestGps['lat'].','.$latestGps['lng'].($latestGps['label']?' ('.$latestGps['label'].')':''));?><div class="card public-map-card"><div class="section-title compact-title"><div><h2>Latest Known Location / पछिल्लो स्थान</h2></div><a class="btn btn-secondary btn-sm" target="_blank" href="https://www.google.com/maps?q=<?=e($latestGps['lat'].','.$latestGps['lng'])?>">Open Directions</a></div><iframe class="map" src="https://www.google.com/maps?q=<?=e($mapQ)?>&z=15&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><div class="small muted map-caption">GPS coordinates are based on the latest public case update or original rescue report.</div></div><?php endif;?>

<div class="card public-timeline-card"><div class="section-title compact-title"><div><h2>Status Timeline / स्थिति समयरेखा</h2><div class="muted small">Verified updates posted by the rescue/missing-person operations team.</div></div></div>
 <div class="timeline public-timeline">
 <?php foreach($updates as $u):?><div class="timeline-item"><div class="timeline-dot status-dot-<?=e($u['status_after'])?>"></div><div class="timeline-content"><div class="timeline-head"><div><b><?=public_status_html($u['status_after'])?></b><?php if($u['condition_after']):?><span class="condition condition-<?=e($u['condition_after'])?>"><?=public_condition_html($u['condition_after'])?></span><?php endif;?></div><time><?=e($u['created_at'])?></time></div>
 <?php if($u['where_found']||$u['current_location']||$u['shifted_to']):?><div class="timeline-location-grid"><?php if($u['where_found']):?><div><span>Found/Rescued at</span><b><?=e($u['where_found'])?></b></div><?php endif;?><?php if($u['current_location']):?><div><span>Current location</span><b><?=e($u['current_location'])?></b></div><?php endif;?><?php if($u['shifted_to']):?><div><span>Shifted to</span><b><?=e($u['shifted_to'])?></b></div><?php endif;?></div><?php endif;?>
 <?php if($u['note']):?><div class="timeline-note"><?=nl2br(e($u['note']))?></div><?php endif;?>
 <div class="verified-by">Verified update<?=!empty($u['office_name'])?' · '.e($u['office_name']):''?></div></div></div><?php endforeach;?>
 <div class="timeline-item"><div class="timeline-dot initial-dot"></div><div class="timeline-content"><div class="timeline-head"><b><?=render_lang('Case Registered','केस दर्ता')?></b><time><?=e($case['created_at'])?></time></div><div class="timeline-note initial-note"><?php if($case['type']==='rescue_waiting'):?><?=render_lang('Rescue request received. Initial reported location: ','उद्धार अनुरोध प्राप्त भयो। प्रारम्भिक स्थान: ')?><?=e($case['last_seen_address'])?><?php elseif($case['type']==='rescued'):?><?=render_lang('Rescued-person record received. Rescue location: ','उद्धार गरिएको व्यक्तिको अभिलेख दर्ता भयो। उद्धार स्थान: ')?><?=e($rescuedDetail['rescue_location']??$case['last_seen_address'])?><?php elseif($case['type']==='deceased'):?><?=render_lang('Recovered/deceased DVI record registered. Recovery location: ','फेला परेको शव/DVI अभिलेख दर्ता भयो। फेला परेको स्थान: ')?><?=e($deceasedDetail['recovery_location']??$case['last_seen_address'])?><?php else:?><?=render_lang('Missing person report received. Last contacted at: ','हराइरहेको व्यक्तिको रिपोर्ट प्राप्त भयो। अन्तिम सम्पर्क स्थान: ')?><?=e($case['last_seen_address'])?><?php endif;?></div></div></div>
 </div>
</div>
<div class="track-help-banner"><div><b><?=render_lang('Could this be your family member?','के यो तपाईंको परिवारको सदस्य हुन सक्छ?')?></b><p><?=render_lang('Submit a family match request. Rescue Nepal staff will verify the information before any identification or handover.','परिवार मिलान अनुरोध पठाउनुहोस्। कुनै पहिचान वा हस्तान्तरण अघि रेस्क्यु नेपालका अधिकृत कर्मचारीले जानकारी प्रमाणित गर्नेछन्।')?></p></div><a class="btn btn-primary" href="<?=e(base_url('family-match/'.$case['id']))?>">Family Match Request</a></div>
<?php endif; ?>
<?php page_footer(); ?>
