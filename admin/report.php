<?php
require_once __DIR__.'/../includes/auth.php'; require_once __DIR__.'/../includes/countries.php';
$admin=require_admin();$id=(int)($_GET['id']??0);
$stmt=db()->prepare('SELECT c.*,r.latitude,r.longitude,r.address rescue_address,r.accuracy FROM cases c LEFT JOIN rescue_locations r ON r.id=(SELECT MAX(r2.id) FROM rescue_locations r2 WHERE r2.case_id=c.id) WHERE c.id=? AND c.deleted_at IS NULL');$stmt->execute([$id]);$c=$stmt->fetch();if(!$c){http_response_code(404);exit('Not found');}
$detail=null;if($c['type']==='rescued'){$s=db()->prepare('SELECT * FROM rescued_person_details WHERE case_id=?');$s->execute([$id]);$detail=$s->fetch();}elseif($c['type']==='deceased'){$s=db()->prepare('SELECT * FROM deceased_details WHERE case_id=?');$s->execute([$id]);$detail=$s->fetch();}
$us=db()->prepare('SELECT u.*,a.name admin_name FROM case_updates u LEFT JOIN admins a ON a.id=u.admin_id WHERE u.case_id=? ORDER BY u.created_at,u.id');$us->execute([$id]);$updates=$us->fetchAll();
$gallery=[];if($c['type']==='deceased'){$m=db()->prepare("SELECT id,label,is_primary FROM case_media WHERE case_id=? AND media_kind='deceased_photo' ORDER BY is_primary DESC,id ASC");$m->execute([$id]);$gallery=$m->fetchAll();}
audit($admin['id'],'print_report',$id);
$statusRows=['Status / अवस्था'=>case_status_label($c['status']).' / '.case_status_label($c['status'],'np'),'Current Condition / हालको अवस्था'=>$c['current_condition']?case_condition_label($c['current_condition']).' / '.case_condition_label($c['current_condition'],'np'):null,'Current Location / हालको स्थान'=>$c['current_location'],'Where Found / कहाँ फेला'=>$c['where_found'],'Shifted To / स्थानान्तरण'=>$c['shifted_to'],'Created / दर्ता'=>$c['created_at'],'Status Updated / अद्यावधिक'=>$c['status_updated_at'],'Close Reason / बन्द कारण'=>$c['close_reason']];
if($c['type']==='rescue_waiting'){$sections=[['Person & Situation / व्यक्ति र परिस्थिति',['Name / नाम'=>$c['name'],'Address / ठेगाना'=>$c['last_seen_address'],'Mobile No. / मोबाइल नं.'=>$c['family_contact_phone'],'Total Persons / जम्मा व्यक्ति'=>$c['total_persons'],'Situation / परिस्थिति'=>$c['rescue_description'],'Latitude'=>$c['latitude'],'Longitude'=>$c['longitude'],'GPS Accuracy'=>$c['accuracy']!==null?$c['accuracy'].' m':null,'Additional Note / थप नोट'=>$c['additional_notes']]],['Case Status / केस अवस्था',$statusRows]];}
elseif($c['type']==='rescued'&&$detail){$sections=[
 ['Identity & Condition',['Name / नाम'=>$c['name'],'Identity Status'=>$detail['identity_status'],'Condition / अवस्था'=>rescued_condition_label($detail['condition_level']),'Age'=>$c['age'],'Estimated Age'=>($detail['estimated_age_min']||$detail['estimated_age_max'])?($detail['estimated_age_min']?:'?').'–'.($detail['estimated_age_max']?:'?'):null,'Gender'=>$c['gender']]],
 ['Rescue Circumstances',['Rescue Date/Time'=>$detail['rescue_datetime_gregorian'],'Rescue Date BS'=>$detail['rescue_date_bs'],'Rescue Location'=>$detail['rescue_location'],'Rescued By'=>rescued_by_label($detail['rescued_by_type']),'Rescuer / Responsible Person'=>$detail['rescued_by_name'],'Rescuing Institution'=>$detail['rescuing_institution_name'],'Rescuer Phone'=>$detail['rescuing_institution_phone'],'Injury / Condition'=>$detail['injury_summary']]],
 ['Contact & Documents',['Person Phone'=>$detail['person_phone'],'Home Address'=>$detail['permanent_address'],'Workplace'=>$detail['workplace'],'Destination'=>$detail['destination'],'Family / Contact Person'=>$detail['emergency_contact_name'],'Family / Contact Phone'=>$detail['emergency_contact_phone'],'Documents Found'=>$detail['documents_found'],'Document Type'=>$detail['identity_document_type'],'Document Number'=>$detail['identity_document_number']]],
 ['Appearance',['Clothing / Appearance'=>$detail['clothing'],'Marks / Birthmark / Tattoo'=>$detail['distinguishing_marks'],'Belongings'=>$detail['belongings']]],
 ['Current Placement',['Current Institution'=>$detail['current_institution_name'],'Current Institution Address'=>$detail['current_institution_address'],'Institution Contact'=>$detail['institution_contact_name'],'Contact Phone'=>$detail['institution_contact_phone'],'Reunification Status'=>$detail['reunification_status']]],
 ['Case Status / केस अवस्था',$statusRows],
];}
elseif($c['type']==='deceased'&&$detail){$sections=[
 ['Identification',['Body Trace ID'=>$detail['body_id'],'Identity Status'=>$detail['identity_status'],'Official Identity'=>$detail['official_identity_name'],'Suspected Name'=>$detail['suspected_name'],'Approx. Gender'=>$c['gender'],'Approx. Age'=>$detail['estimated_age_min']]],
 ['Recovery Details',['Body Condition'=>$detail['body_condition'],'Found / Recovery Location'=>$detail['recovery_location'],'River / Waterbody'=>$detail['river_waterbody'],'Recovered By'=>deceased_recovered_by_label($detail['recovered_by_organization']),'Team / Rescuer'=>$detail['recovered_by_name'],'Recovery Date/Time'=>$detail['recovery_datetime_gregorian'],'Recovery Date BS'=>$detail['recovery_date_bs']]],
 ['Custody & Shifting',['Shifted To Type'=>$detail['shifted_to_type'],'Shifted To'=>$detail['current_mortuary'],'Storage Location'=>$detail['current_storage_location'],'Responsible Officer'=>$detail['recovery_officer_name'],'Responsible Phone'=>$detail['recovery_officer_phone']]],
 ['Physical Description / Huliya',['Clothing / Huliya'=>$detail['clothing'],'Tattoos'=>$detail['tattoos'],'Scars'=>$detail['scars'],'Birthmarks'=>$detail['birthmarks'],'Documents Found'=>$detail['documents_found'],'Other Belongings'=>$detail['other_belongings']]],
 ['Forensic References (Restricted)',['Body Bag No.'=>$detail['body_bag_no'],'Seal No.'=>$detail['seal_no'],'Fingerprint Reference'=>$detail['fingerprint_reference'],'Fingerprint Result'=>$detail['fingerprint_result'],'DNA Sample ID'=>$detail['dna_sample_id'],'DNA Lab'=>$detail['dna_lab'],'DNA Reference'=>$detail['dna_reference'],'DNA Result'=>$detail['dna_result'],'Dental Summary'=>$detail['dental_summary'],'Post-mortem Reference'=>$detail['postmortem_reference'],'Official Identification Method'=>$detail['official_identification_method'],'Forensic Notes'=>$detail['forensic_notes']]],
 ['Case Status / केस अवस्था',$statusRows],
];}
else{$sections=[
 ['Identity',['Name / नाम'=>$c['name'],'Age / उमेर'=>$c['age'],'Gender / लिङ्ग'=>$c['gender'],'Nationality / राष्ट्रियता'=>$c['nationality_name']?:'Not recorded','Person Category / हैसियत'=>missing_person_context_label($c['missing_person_context']),'Work Institution / Office / Destination / School'=>$c['associated_place_name'],'Document Type'=>$c['nationality_code']&&$c['nationality_code']!=='NP'?identity_document_type_label($c['identity_document_type']):null,'Document Number'=>$c['nationality_code']&&$c['nationality_code']!=='NP'?$c['identity_document_number']:null,'Vehicle / सवारी'=>$c['vehicle_no']]],
 ['Last Contact',['From / ठेगाना'=>$c['from_location'],'Last Contacted BS / वि.सं.'=>$c['last_contacted_bs'],'Last Contacted Time / समय'=>$c['last_contacted_time'],'Last Contacted Address / अन्तिम सम्पर्क स्थान'=>$c['last_seen_address'],'Other Family Members Missing'=>$c['other_family_members_missing']?'Yes / हो':'No / होइन']],
 ['Family Contact & Notes',['Contact / सम्पर्क'=>trim($c['family_contact_name'].' - '.$c['family_contact_phone'],' -'),'Notes / थप विवरण'=>$c['additional_notes']]],
 ['Case Status / केस अवस्था',$statusRows],
];}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?=e($c['case_code'])?> Report</title><link rel="stylesheet" href="<?=e(base_url('assets/app.css'))?>"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap" rel="stylesheet">
<style>
.report-photo-row{display:flex;gap:20px;align-items:flex-start;margin:16px 0;padding-bottom:16px;border-bottom:1px solid var(--line)}
.report-gallery{display:grid;grid-template-columns:repeat(2,170px);gap:10px;flex:none}
.report-photo-page{display:flex;flex-direction:column;align-items:center;gap:4px}
.report-photo-page img{display:block;width:170px;height:170px;object-fit:cover;border-radius:10px;border:1px solid var(--line);background:#f1f5f9}
.report-photo-label{font-size:10px;color:var(--muted);text-align:center}
.report-photo-single{display:block;width:170px;height:170px;object-fit:cover;border-radius:10px;border:1px solid var(--line);background:#f1f5f9;flex:none}
.report-section{margin:0 0 18px;page-break-inside:avoid;break-inside:avoid}
.report-section h2{font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 8px;padding-bottom:6px;border-bottom:2px solid var(--primary)}
.report-section .case-meta{grid-template-columns:repeat(2,1fr)}
@media(max-width:620px){.report-section .case-meta{grid-template-columns:1fr}.report-photo-row{flex-wrap:wrap}.report-gallery{grid-template-columns:repeat(2,120px)}.report-photo-page img{width:120px;height:120px}}
@media print{
  @page{margin:12mm}
  body{font-size:12px}
  .container{width:100%}
  .report-photo-row{display:block;margin:0;padding:0;border:0}
  .report-gallery{display:block;page-break-after:always;break-after:page}
  .report-photo-page:not(:first-child){page-break-before:always;break-before:page}
  .report-photo-page img{width:auto;height:auto;max-width:170mm;max-height:220mm;object-fit:contain;border-radius:4px}
  .report-photo-label{font-size:11px;margin-top:8px}
  .report-photo-single{width:110px;height:110px}
  .report-section{margin:0 0 10px}
  .report-section h2{font-size:11px;margin:0 0 4px;padding-bottom:3px}
  .case-meta{gap:0 16px}
  .meta-row{padding:5px 0}
  .meta-row b{font-size:9.5px;margin-bottom:1px}
  .timeline-item{padding-bottom:12px;page-break-inside:avoid}
  .timeline-content{padding:8px}
}
</style>
</head><body><main class="container"><div class="no-print" style="padding:15px 0"><button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button> <button class="btn btn-ghost" onclick="history.back()">Back</button></div>
<article class="card print-card">
<div class="section-title"><div><h1><?=e(case_type_label($c['type']))?> Report / केस प्रतिवेदन</h1><b><?=e($c['case_code'])?></b><?php if($c['type']==='deceased'&&$detail):?><div><b><?=e($detail['body_id'])?></b> · Restricted DVI record</div><?php endif;?></div><div><span class="status status-<?=e($c['status'])?>"><?=e(case_status_label($c['status']))?></span></div></div>

<?php foreach($sections as [$sectionTitle,$rows]): $rows=array_filter($rows,fn($v)=>$v!==null&&$v!==''); if(!$rows) continue; ?>
<div class="report-section"><h2><?=e($sectionTitle)?></h2><div class="case-meta"><?php foreach($rows as $k=>$v):?><div class="meta-row"><b><?=e($k)?></b><?=nl2br(e((string)$v))?></div><?php endforeach;?></div></div>
<?php endforeach;?>

<?php if($gallery):?>
<div class="report-photo-row"><div class="report-gallery"><?php foreach($gallery as $i=>$media):?><div class="report-photo-page"><img src="<?=e(base_url('admin/media/dvi-photo/'.$media['id']))?>" alt="<?=e($media['label'])?>"><div class="report-photo-label"><?=e($c['case_code'])?> · Photo <?=e($i+1)?> of <?=e(count($gallery))?><?=$media['label']?' · '.e($media['label']):''?></div></div><?php endforeach;?></div></div>
<?php elseif($c['photo_url']): $reportPhotoUrl=$c['type']==='deceased'?base_url('admin/media/dvi/'.$id):base_url($c['photo_url']);?>
<div class="report-photo-row"><img class="report-photo-single" src="<?=e($reportPhotoUrl)?>" alt="Case photo"></div>
<?php endif;?>

<?php if($updates):?><h2 style="margin-top:24px">Case Progress Timeline / प्रगति विवरण</h2><div class="timeline"><?php foreach($updates as $u):?><div class="timeline-item"><div class="timeline-dot status-dot-<?=e($u['status_after'])?>"></div><div class="timeline-content"><div class="timeline-head"><b><?=e(case_status_label($u['status_after']))?><?php if($u['condition_after']):?> · <?=e(case_condition_label($u['condition_after']))?><?php endif;?></b><time><?=e($u['created_at'])?></time></div><?php if($u['where_found']):?><p><b>Found/Rescued at:</b> <?=e($u['where_found'])?></p><?php endif;?><?php if($u['current_location']):?><p><b>Current location:</b> <?=e($u['current_location'])?></p><?php endif;?><?php if($u['shifted_to']):?><p><b>Shifted to:</b> <?=e($u['shifted_to'])?></p><?php endif;?><?php if($u['note']):?><div class="timeline-note"><?=nl2br(e($u['note']))?></div><?php endif;?><div class="small muted">By <?=e($u['admin_name']?:'Admin')?> · <?= $u['public_visible']?'Public update':'Internal update' ?></div></div></div><?php endforeach;?></div><?php endif;?>
<p class="small muted">Generated <?=e(date('Y-m-d H:i:s'))?> by <?=e($admin['name'])?></p>
</article></main></body></html>
