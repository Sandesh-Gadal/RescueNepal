<?php
require_once __DIR__.'/includes/layout.php';
$q=trim((string)($_GET['q']??''));$type=$_GET['type']??'';$gender=$_GET['gender']??'';$age=($_GET['age']??'')!==''?(int)$_GET['age']:null;$location=trim((string)($_GET['location']??''));
if(!in_array($type,['','rescued','deceased'],true))$type='';
$results=[];
$hasSearch=$q!==''||$type!==''||$gender!==''||$age!==null||$location!=='';
if($hasSearch){
 $where=['c.deleted_at IS NULL','c.is_public=1',"c.status<>'draft'","c.type IN ('rescued','deceased')"];$args=[];
 if($type!==''){$where[]='c.type=?';$args[]=$type;}
 if(in_array($gender,['Male','Female','Other','Unknown'],true)){$where[]='c.gender=?';$args[]=$gender;}
 if($location!==''){$like='%'.$location.'%';$where[]='(rd.rescue_location LIKE ? OR rd.current_institution_name LIKE ? OR dd.recovery_location LIKE ? OR dd.current_mortuary LIKE ?)';for($i=0;$i<4;$i++)$args[]=$like;}
 $sql='SELECT c.*,rd.identity_status rescued_identity,rd.person_phone rescued_person_phone,rd.estimated_age_min r_age_min,rd.estimated_age_max r_age_max,rd.rescue_location,rd.current_institution_name,rd.institution_contact_phone,rd.condition_level rescued_condition,rd.identity_document_type r_doc_type,rd.identity_document_number r_doc_no,rd.documents_found r_docs,rd.workplace,rd.destination,rd.clothing r_clothing,rd.distinguishing_marks r_marks,rd.public_photo_allowed,dd.body_id,dd.identity_status deceased_identity,dd.estimated_age_min d_age_min,dd.estimated_age_max d_age_max,dd.recovery_location,dd.current_mortuary,dd.documents_found d_docs,dd.clothing d_clothing,dd.tattoos,dd.scars,dd.birthmarks,dd.body_condition,dd.public_identity_release,dd.official_identity_name FROM cases c LEFT JOIN rescued_person_details rd ON rd.case_id=c.id LEFT JOIN deceased_details dd ON dd.case_id=c.id WHERE '.implode(' AND ',$where).' ORDER BY c.updated_at DESC LIMIT 3000';
 $st=db()->prepare($sql);$st->execute($args);$rows=$st->fetchAll();
 foreach($rows as $r){
   $score=50.0;
   if($q!==''){
     $values=$r['type']==='rescued'?
       [$r['case_code'],$r['name'],$r['rescued_person_phone'],$r['r_doc_type'],$r['r_doc_no'],$r['r_docs'],$r['workplace'],$r['destination'],$r['rescue_location'],$r['current_institution_name'],$r['r_clothing'],$r['r_marks']]:
       [$r['case_code'],$r['body_id'],$r['d_docs'],$r['d_clothing'],$r['tattoos'],$r['scars'],$r['birthmarks'],$r['recovery_location'],$r['current_mortuary']];
     $score=0.0;foreach($values as $v)$score=max($score,text_similarity($q,(string)$v),token_overlap_score($q,(string)$v));
     if($score<32)continue;
   }
   if($age!==null){$amin=$r['type']==='rescued'?($r['age']!==null?$r['age']:$r['r_age_min']):$r['d_age_min'];$amax=$r['type']==='rescued'?($r['age']!==null?$r['age']:$r['r_age_max']):$r['d_age_max'];if($amin!==null&&$amax!==null){if($age<(int)$amin-4||$age>(int)$amax+4)continue;$score+=8;}}
   $r['_score']=$score;$results[]=$r;
 }
 usort($results,fn($a,$b)=>$b['_score']<=>$a['_score']);$results=array_slice($results,0,80);
}
page_header('Universal Search');
?>
<section class="track-hero"><div><h1><?=render_lang('Universal Search: Rescued Person or Dead Body','सार्वजनिक खोजी: उद्धार व्यक्ति वा शव')?></h1><p><?=render_lang('Families can search rescued persons by name, mobile number or document clues, and unidentified deceased persons by appearance, document clues or Body Trace ID.','परिवारले उद्धार व्यक्तिलाई नाम, मोबाइल वा कागजातको संकेतबाट र पहिचान नखुलेको शवलाई हुलिया, कागजातको संकेत वा Body Trace ID बाट खोज्न सक्छन्।')?></p></div></section>

<div class="search-mode-grid">
<a class="search-mode-card <?=$type==='rescued'?'active':''?>" href="<?=e(base_url('find?type=rescued'))?>"><b>✅ <?=render_lang('Rescued Person','उद्धार गरिएको व्यक्ति')?></b><small><?=render_lang('Name · mobile · document details','नाम · मोबाइल · कागजात विवरण')?></small></a>
<a class="search-mode-card <?=$type==='deceased'?'active':''?>" href="<?=e(base_url('find?type=deceased'))?>"><b>⚫ <?=render_lang('Dead Body / Unidentified Deceased','शव / पहिचान नखुलेको मृतक')?></b><small><?=render_lang('Body Trace ID · appearance · document details','Body Trace ID · हुलिया · कागजात विवरण')?></small></a>
</div>

<form class="card family-search-form practical-search" method="get" action="<?=e(base_url('find'))?>" id="universalSearchForm">
<div class="grid">
<div class="col-4"><label><?=render_lang('Search Type','खोजी प्रकार')?><select name="type" id="searchType"><option value="" <?=$type===''?'selected':''?>>Both / दुवै</option><option value="rescued" <?=$type==='rescued'?'selected':''?>>Rescued Person / उद्धार व्यक्ति</option><option value="deceased" <?=$type==='deceased'?'selected':''?>>Dead Body / शव</option></select></label></div>
<div class="col-8"><label><?=render_lang('Search','खोज्नुहोस्')?><input name="q" id="universalQ" value="<?=e($q)?>" placeholder="Name / mobile / document / Body Trace ID / clothes / tattoo..."><span class="hint" id="searchHint"><?=render_lang('You can enter partial information. Exact spelling is not required for names.','आंशिक जानकारी पनि राख्न सकिन्छ। नामको हिज्जे ठ्याक्कै मिल्न आवश्यक छैन।')?></span></label></div>
<div class="col-3"><label><?=render_lang('Gender','लिङ्ग')?><select name="gender"><option value="">Any / जुनसुकै</option><option value="Male" <?=$gender==='Male'?'selected':''?>>Male / पुरुष</option><option value="Female" <?=$gender==='Female'?'selected':''?>>Female / महिला</option><option value="Other" <?=$gender==='Other'?'selected':''?>>Other / अन्य</option></select></label></div>
<div class="col-2"><label><?=render_lang('Approx. Age','अनुमानित उमेर')?><input type="number" name="age" min="0" max="120" value="<?=e($age??'')?>"></label></div>
<div class="col-7"><label><?=render_lang('Found / Rescue Location','फेला / उद्धार स्थान')?><input name="location" value="<?=e($location)?>" placeholder="River, village, hospital, district, landmark"></label></div>
</div>
<div class="form-actions"><button class="btn btn-primary btn-lg">Search / खोज्नुहोस्</button><a class="btn btn-ghost" href="<?=e(base_url('find'))?>">Clear</a></div>
</form>

<?php if($hasSearch):?>
<div class="section-title"><div><h2><?=count($results)?> <?=render_lang('possible result(s)','सम्भावित नतिजा')?></h2><div class="muted"><?=render_lang('Search results help tracing only. Family/authority verification is required before handover or closure.','खोजी नतिजाले ट्रेस गर्न सहयोग गर्छ। हस्तान्तरण वा केस बन्द गर्न परिवार/अधिकृत पुष्टि आवश्यक हुन्छ।')?></div></div></div>
<div class="public-result-grid">
<?php foreach($results as $r):$isDead=$r['type']==='deceased';$ageText=$r['age']!==null?(string)$r['age']:(($isDead?$r['d_age_min']:$r['r_age_min'])!==null?($isDead?$r['d_age_min']:$r['r_age_min']).'–'.(($isDead?$r['d_age_max']:$r['r_age_max'])??($isDead?$r['d_age_min']:$r['r_age_min'])):'Unknown');$showPhoto=!$isDead&&!empty($r['thumb_url'])&&!empty($r['public_photo_allowed']);?>
<article class="card public-result-card <?=$isDead?'dead-result':'rescued-result'?>">
<div class="result-card-head"><?php if($showPhoto):?><img src="<?=e(base_url($r['thumb_url']))?>" alt="Rescued person photo"><?php else:?><div class="person-placeholder"><?=$isDead?'BODY TRACE':'NO PHOTO'?></div><?php endif;?><div><span class="case-type-chip type-<?=e($r['type'])?>"><?=e($isDead?'Dead Body / शव':'Rescued / उद्धार')?></span><h3><?=e($isDead?($r['body_id']?:$r['case_code']):(($r['rescued_identity']??'')==='claimed'?'Possible identity: '.preg_replace('/^Possible:\s*/','',(string)$r['name']):$r['name']))?></h3><div class="small muted"><?=e($r['case_code'])?></div></div></div>
<dl class="result-details"><div><dt><?=render_lang('Approx. Age','अनुमानित उमेर')?></dt><dd><?=e($ageText)?></dd></div><div><dt><?=render_lang('Gender','लिङ्ग')?></dt><dd><?=e($r['gender'])?></dd></div><div><dt><?=render_lang($isDead?'Found At':'Rescued At','स्थान')?></dt><dd><?=e(($isDead?$r['recovery_location']:$r['rescue_location'])?:'Not published')?></dd></div><div><dt><?=render_lang($isDead?'Shifted To':'Current Location','हालको स्थान')?></dt><dd><?=e(($isDead?$r['current_mortuary']:$r['current_institution_name'])?:'Not published')?></dd></div></dl>
<?php if(!$isDead):?><p class="small"><b><?=render_lang('Condition:','अवस्था:')?></b> <?=e(rescued_condition_label($r['rescued_condition']))?></p><?php if(!empty($r['workplace'])||!empty($r['destination'])):?><p class="small"><b><?=render_lang('Work / Destination:','काम / गन्तव्य:')?></b> <?=e(trim(($r['workplace']??'').' · '.($r['destination']??''),' ·'))?></p><?php endif;?><?php if(!empty($r['r_docs'])||!empty($r['r_doc_no'])):?><div class="document-found-chip">Document information recorded / कागजात विवरण उपलब्ध</div><?php endif;?><?php if(!empty($r['r_marks'])):?><p class="small"><b><?=render_lang('Identifying marks:','पहिचान चिन्ह:')?></b> <?=e(mb_strimwidth($r['r_marks'],0,180,'…','UTF-8'))?></p><?php endif;?>
<?php else:?><p class="small"><b><?=render_lang('Huliya / Identifying description:','हुलिया / पहिचान विवरण:')?></b> <?=e(mb_strimwidth(trim(($r['d_clothing']??'').' '.($r['birthmarks']??'').' '.($r['tattoos']??'').' '.($r['scars']??'')),0,260,'…','UTF-8')?:'Not recorded')?></p><?php if(!empty($r['d_docs'])):?><div class="document-found-chip">Document information found with body / शवसँग कागजात विवरण भेटिएको</div><?php endif;?><div class="alert alert-warning small">Dead-body photographs and full document numbers are restricted. Use the Family Match Request for verification.</div><?php endif;?>
<div class="result-actions"><?php if(!$isDead):?><a class="btn btn-secondary" href="<?=e(base_url('track/'.$r['case_code']))?>">View Status / स्थिति</a><?php endif;?><a class="btn btn-primary" href="<?=e(base_url('family-match/'.$r['id']))?>">This may be my family member / परिवार हुन सक्छ</a></div>
</article>
<?php endforeach;?>
<?php if(!$results):?><div class="card empty-search-result"><h3><?=render_lang('No matching rescued-person or dead-body record found yet.','अहिलेसम्म मिल्दो उद्धार व्यक्ति वा शव रेकर्ड भेटिएन।')?></h3><p><?=render_lang('If the person is still missing, register a missing-person case. Future rescued/deceased records can then be linked to that Case ID.','व्यक्ति अझै बेपत्ता हुनुहुन्छ भने Missing Person केस दर्ता गर्नुहोस्। पछि भेटिएका उद्धार/शव रेकर्ड त्यस केस आईडीसँग लिंक गर्न सकिन्छ।')?></p><a class="btn btn-primary" href="<?=e(base_url('missing-person'))?>">Report Missing Person / बेपत्ता दर्ता</a></div><?php endif;?>
</div>
<?php endif;?>
<div class="privacy-banner card"><b><?=render_lang('Privacy','गोपनीयता')?></b><p><?=render_lang('A document number can be used to search, but full ID numbers, private medical information and deceased photographs are not displayed publicly.','कागजात नम्बरबाट खोज्न सकिन्छ, तर पूरा परिचयपत्र नम्बर, निजी स्वास्थ्य विवरण र शवका फोटो सार्वजनिक रूपमा देखाइँदैनन्।')?></p></div>
<script>
(()=>{const t=document.getElementById('searchType'),q=document.getElementById('universalQ');function sync(){if(t.value==='rescued')q.placeholder='Name / mobile number / document number or details';else if(t.value==='deceased')q.placeholder='Body Trace ID / clothes / tattoo / birthmark / document details';else q.placeholder='Name / mobile / document / Body Trace ID / huliya';}t?.addEventListener('change',sync);sync();})();
</script>
<?php page_footer(); ?>
