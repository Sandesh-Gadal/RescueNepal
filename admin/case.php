<?php
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/auth.php'; require_once __DIR__.'/../includes/countries.php';
$admin=require_admin();
$id=(int)($_GET['id']??0);
$stmt=db()->prepare('SELECT c.*,a.name closed_by_name,su.name status_by_name FROM cases c LEFT JOIN admins a ON a.id=c.closed_by_admin_id LEFT JOIN admins su ON su.id=c.status_updated_by WHERE c.id=? AND c.deleted_at IS NULL');
$stmt->execute([$id]);$case=$stmt->fetch(); if(!$case){http_response_code(404);exit('Case not found');}

$missingStatuses=['open','under_review','searching','located','found_alive','found_injured','found_deceased','shifted'];
$rescueStatuses=['open','under_review','rescue_dispatched','located','rescued_safe','rescued_injured','found_deceased','shifted'];
$rescuedStatuses=['open','under_review','identity_unknown','potential_match','family_contacted','rescued_safe','rescued_injured','shifted'];
$deceasedStatuses=['identity_unknown','potential_match','family_contacted','identification_review','shifted'];
$allowedStatuses=match($case['type']){'missing'=>$missingStatuses,'rescue_waiting'=>$rescueStatuses,'rescued'=>$rescuedStatuses,'deceased'=>$deceasedStatuses,default=>['open']};
$conditions=array_keys(case_condition_labels());

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf(); $action=$_POST['action']??'';
    if($action==='soft_delete' && $admin['role']==='superadmin'){
        db()->prepare('UPDATE cases SET deleted_at=NOW(),deleted_by_admin_id=? WHERE id=?')->execute([$admin['id'],$id]);
        audit($admin['id'],'soft_delete_case',$id,trim($_POST['reason']??'')); flash('success','Case archived.'); redirect(base_url('admin/dashboard.php'));
    }
    if($action==='note'){
        $note=trim($_POST['note']??''); if($note!==''){db()->prepare('INSERT INTO case_notes(case_id,admin_id,note) VALUES (?,?,?)')->execute([$id,$admin['id'],$note]);audit($admin['id'],'add_internal_note',$id,$note);flash('success','Internal note added.');}
    }
    elseif($action==='progress_update' && in_array($admin['role'],['operator','approver','superadmin'],true) && $case['status']!=='closed'){
        $status=(string)($_POST['status']??'');
        $condition=(string)($_POST['condition']??'unknown');
        $whereFound=trim((string)($_POST['where_found']??''));
        $currentLocation=trim((string)($_POST['current_location']??''));
        $shiftedTo=trim((string)($_POST['shifted_to']??''));
        $note=trim((string)($_POST['public_note']??''));
        $lat=trim((string)($_POST['latitude']??'')); $lng=trim((string)($_POST['longitude']??''));
        $publicVisible=isset($_POST['public_visible'])?1:0;
        if(!in_array($status,$allowedStatuses,true)){flash('danger','Invalid status for this case type.'); redirect(base_url('admin/case.php?id='.$id));}
        if(!in_array($condition,$conditions,true))$condition='unknown';
        $inferred=['found_alive'=>'alive','found_injured'=>'injured','found_deceased'=>'deceased','rescued_safe'=>'safe','rescued_injured'=>'injured'];
        if($condition==='unknown' && isset($inferred[$status]))$condition=$inferred[$status];
        if(($lat!==''||$lng!=='') && !valid_latlng($lat,$lng)){flash('danger','Latitude/longitude is invalid. Leave both blank or enter valid coordinates.');redirect(base_url('admin/case.php?id='.$id));}
        if(in_array($status,['found_alive','found_injured','found_deceased','rescued_safe','rescued_injured','located'],true) && $whereFound==='' && $currentLocation===''){
            flash('danger','Please enter where the person was found/rescued or their current location.');redirect(base_url('admin/case.php?id='.$id));
        }
        $category='status';
        if(str_starts_with($status,'found_')||$status==='located')$category='found';
        elseif(str_starts_with($status,'rescue_')||str_starts_with($status,'rescued_'))$category='rescue';
        elseif($status==='shifted')$category='transfer'; elseif($status==='searching')$category='search';
        $pdo=db(); $pdo->beginTransaction();
        try{
            $ins=$pdo->prepare('INSERT INTO case_updates(case_id,admin_id,update_category,status_after,condition_after,where_found,current_location,shifted_to,latitude,longitude,note,public_visible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([$id,$admin['id'],$category,$status,$condition,$whereFound?:null,$currentLocation?:null,$shiftedTo?:null,$lat!==''?(float)$lat:null,$lng!==''?(float)$lng:null,$note?:null,$publicVisible]);
            $upd=$pdo->prepare("UPDATE cases SET status=?,current_condition=?,current_location=COALESCE(NULLIF(?,''),current_location),where_found=COALESCE(NULLIF(?,''),where_found),shifted_to=COALESCE(NULLIF(?,''),shifted_to),status_note=COALESCE(NULLIF(?,''),status_note),status_updated_at=NOW(),status_updated_by=?,updated_at=NOW() WHERE id=?");
            $upd->execute([$status,$condition,$currentLocation,$whereFound,$shiftedTo,$note,$admin['id'],$id]);
            audit($admin['id'],'case_progress_update',$id,case_status_label($status).' | '.($note?:'No public note'));
            $pdo->commit(); flash('success','Case progress updated and timeline entry created.');
        }catch(Throwable $e){$pdo->rollBack();flash('danger','Could not save update: '.$e->getMessage());}
    }
    elseif($action==='request_close' && in_array($admin['role'],['operator','approver','superadmin'],true)){
        $reason=trim($_POST['reason']??'');
        if($reason==='')flash('danger','Closure reason is required.');
        elseif(can_close($admin['role'])){
            db()->prepare("UPDATE cases SET status='closed',close_reason=?,closed_at=NOW(),closed_by_admin_id=?,status_updated_at=NOW(),status_updated_by=? WHERE id=?")->execute([$reason,$admin['id'],$admin['id'],$id]);
            db()->prepare("INSERT INTO case_updates(case_id,admin_id,update_category,status_after,note,public_visible) VALUES (?,?,'status','closed','Case formally closed.',1)")->execute([$id,$admin['id']]);
            audit($admin['id'],'close_case',$id,$reason);flash('success','Case closed.');
        } else {
            $exists=db()->prepare("SELECT COUNT(*) FROM close_approval_requests WHERE case_id=? AND status='pending'");$exists->execute([$id]);
            if((int)$exists->fetchColumn()>0){flash('warning','A close approval request is already pending for this case.');}
            else{db()->prepare('INSERT INTO close_approval_requests(case_id,requested_by,reason) VALUES (?,?,?)')->execute([$id,$admin['id'],$reason]);audit($admin['id'],'request_close',$id,$reason);flash('success','Close approval requested. Current operational status remains unchanged until approval.');}
        }
    }
    redirect(base_url('admin/case.php?id='.$id));
}

$loc=null;$rescuedDetail=null;$deceasedDetail=null;$deceasedMedia=[];
if($case['type']==='rescue_waiting'){$s=db()->prepare('SELECT * FROM rescue_locations WHERE case_id=? ORDER BY id DESC LIMIT 1');$s->execute([$id]);$loc=$s->fetch();}
elseif($case['type']==='rescued'){$s=db()->prepare('SELECT * FROM rescued_person_details WHERE case_id=?');$s->execute([$id]);$rescuedDetail=$s->fetch();if($rescuedDetail&&$rescuedDetail['current_latitude']!==null&&$rescuedDetail['current_longitude']!==null)$loc=['latitude'=>$rescuedDetail['current_latitude'],'longitude'=>$rescuedDetail['current_longitude'],'address'=>$rescuedDetail['current_institution_address']?:$rescuedDetail['current_institution_name']];elseif($rescuedDetail&&$rescuedDetail['rescue_latitude']!==null&&$rescuedDetail['rescue_longitude']!==null)$loc=['latitude'=>$rescuedDetail['rescue_latitude'],'longitude'=>$rescuedDetail['rescue_longitude'],'address'=>$rescuedDetail['rescue_location']];}
elseif($case['type']==='deceased'){$s=db()->prepare('SELECT * FROM deceased_details WHERE case_id=?');$s->execute([$id]);$deceasedDetail=$s->fetch();$m=db()->prepare("SELECT id,label,is_primary,created_at FROM case_media WHERE case_id=? AND media_kind='deceased_photo' ORDER BY is_primary DESC,id ASC");$m->execute([$id]);$deceasedMedia=$m->fetchAll();if($deceasedDetail&&$deceasedDetail['recovery_latitude']!==null&&$deceasedDetail['recovery_longitude']!==null)$loc=['latitude'=>$deceasedDetail['recovery_latitude'],'longitude'=>$deceasedDetail['recovery_longitude'],'address'=>$deceasedDetail['recovery_location']];}
$notesStmt=db()->prepare('SELECT n.*,a.name admin_name FROM case_notes n LEFT JOIN admins a ON a.id=n.admin_id WHERE n.case_id=? ORDER BY n.id DESC');$notesStmt->execute([$id]);$notes=$notesStmt->fetchAll();
$updatesStmt=db()->prepare('SELECT u.*,a.name admin_name,a.post_title FROM case_updates u LEFT JOIN admins a ON a.id=u.admin_id WHERE u.case_id=? ORDER BY u.created_at DESC,u.id DESC');$updatesStmt->execute([$id]);$updates=$updatesStmt->fetchAll();
$pc=db()->prepare("SELECT r.*,a.name requested_by_name FROM close_approval_requests r LEFT JOIN admins a ON a.id=r.requested_by WHERE r.case_id=? AND r.status='pending' ORDER BY r.id DESC LIMIT 1");$pc->execute([$id]);$pendingClose=$pc->fetch();
$adminGps=null;foreach($updates as $gx){if($gx['latitude']!==null&&$gx['longitude']!==null){$adminGps=['lat'=>(float)$gx['latitude'],'lng'=>(float)$gx['longitude'],'label'=>$gx['current_location']?:($gx['where_found']?:'Latest update location')];break;}}if(!$adminGps&&$loc)$adminGps=['lat'=>(float)$loc['latitude'],'lng'=>(float)$loc['longitude'],'label'=>$loc['address']?:'Original rescue location'];
page_header($case['case_code'],true);
if($adminGps):?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><?php endif;
?>
<div class="case-admin-head">
  <div><div class="card-kicker"><?=e(strtoupper(case_type_label($case['type'])))?></div><h1><?=e($case['case_code'])?></h1><div class="inline"><span class="status status-<?=e($case['status'])?>"><?=e(case_status_label($case['status']))?></span><?php if($case['current_condition']):?><span class="condition condition-<?=e($case['current_condition'])?>"><?=e(case_condition_label($case['current_condition']))?></span><?php endif;?></div></div>
  <div class="case-admin-buttons"><a class="btn btn-primary" target="_blank" href="<?=e(base_url('track.php?admin_preview=1&id='.$id))?>">Public Tracking</a><a class="btn btn-secondary" target="_blank" href="<?=e(base_url('admin/report.php?id='.$id))?>">Print Report</a><?php if(in_array($case['type'],['rescued','deceased'],true)):?><a class="btn btn-secondary" href="<?=e(base_url('admin/reconciliation/'.$id))?>">Reconciliation</a><a class="btn btn-secondary" href="<?=e(base_url('admin/cases/'.$id.'/handover'))?>">Handover / Reunite</a><?php endif;?><?php if($case['type']==='deceased'):?><a class="btn btn-secondary" href="<?=e(base_url('admin/cases/'.$id.'/custody'))?>">Chain of Custody</a><?php endif;?><a class="btn btn-ghost" href="<?=e(base_url('admin/dashboard.php'))?>">Back</a></div>
</div>

<div class="current-state-grid">
 <div class="state-card primary-state"><span>Current Status / हालको स्थिति</span><strong><?=public_status_html($case['status'])?></strong><small><?=e($case['status_updated_at']?:$case['updated_at'])?></small></div>
 <div class="state-card"><span>Condition / अवस्था</span><strong><?= $case['current_condition']?public_condition_html($case['current_condition']):'Not confirmed / पुष्टि छैन' ?></strong></div>
 <div class="state-card"><span>Current Location / हालको स्थान</span><strong><?=e($case['current_location']?:($case['last_seen_address']?:'Not updated'))?></strong></div>
 <div class="state-card"><span>Shifted To / स्थानान्तरण</span><strong><?=e($case['shifted_to']?:'—')?></strong></div>
</div>

<div class="case-workspace">
<div class="case-main-column">
 <div class="card case-info-card"><div class="section-title compact-title"><h2>Case Information / केस विवरण</h2></div><div class="case-meta">
 <?php if($case['type']==='rescue_waiting'){ $rows=['Name / नाम'=>$case['name'],'Address / ठेगाना'=>$case['last_seen_address'],'Mobile No.'=>$case['family_contact_phone'],'Total Persons'=>$case['total_persons'],'Situation / अवस्था'=>$case['rescue_description'],'Additional Note'=>$case['additional_notes']]; }
 elseif($case['type']==='rescued' && $rescuedDetail){ $rows=['Name / नाम'=>$case['name'],'Identity Status'=>$rescuedDetail['identity_status'],'Condition / अवस्था'=>rescued_condition_label($rescuedDetail['condition_level']),'Age'=>$case['age'],'Estimated Age'=>$rescuedDetail['estimated_age_min']||$rescuedDetail['estimated_age_max']?trim(($rescuedDetail['estimated_age_min']?:'?').'–'.($rescuedDetail['estimated_age_max']?:'?')):null,'Gender'=>$case['gender'],'Person Phone'=>$rescuedDetail['person_phone'],'Home Address'=>$rescuedDetail['permanent_address'],'Workplace'=>$rescuedDetail['workplace'],'Destination'=>$rescuedDetail['destination'],'Family / Contact Person'=>$rescuedDetail['emergency_contact_name'],'Family / Contact Phone'=>$rescuedDetail['emergency_contact_phone'],'Document Type'=>$rescuedDetail['identity_document_type'],'Document Number'=>$rescuedDetail['identity_document_number'],'Documents Found'=>$rescuedDetail['documents_found'],'Rescue Date/Time'=>$rescuedDetail['rescue_datetime_gregorian'],'Rescue Date BS'=>$rescuedDetail['rescue_date_bs'],'Rescue Location'=>$rescuedDetail['rescue_location'],'Rescued By'=>rescued_by_label($rescuedDetail['rescued_by_type']),'Rescuer / Responsible Person'=>$rescuedDetail['rescued_by_name'],'Rescuing Institution'=>$rescuedDetail['rescuing_institution_name'],'Rescuer Phone'=>$rescuedDetail['rescuing_institution_phone'],'Injury / Condition'=>$rescuedDetail['injury_summary'],'Current Institution'=>$rescuedDetail['current_institution_name'],'Current Address'=>$rescuedDetail['current_institution_address'],'Institution Contact'=>$rescuedDetail['institution_contact_name'],'Contact Post'=>$rescuedDetail['institution_contact_post'],'Contact Phone'=>$rescuedDetail['institution_contact_phone'],'Clothing / Appearance'=>$rescuedDetail['clothing'],'Marks / Birthmark / Tattoo'=>$rescuedDetail['distinguishing_marks'],'Belongings'=>$rescuedDetail['belongings'],'Reunification'=>$rescuedDetail['reunification_status']]; }
 elseif($case['type']==='deceased' && $deceasedDetail){ $rows=['Body Trace ID'=>$deceasedDetail['body_id'],'Identity Status'=>$deceasedDetail['identity_status'],'Official Identity'=>$deceasedDetail['official_identity_name'],'Suspected Name'=>$deceasedDetail['suspected_name'],'Approx. Gender'=>$case['gender'],'Approx. Age'=>$deceasedDetail['estimated_age_min'],'Body Condition'=>$deceasedDetail['body_condition'],'Found / Recovery Location'=>$deceasedDetail['recovery_location'],'River / Waterbody'=>$deceasedDetail['river_waterbody'],'Recovered By'=>deceased_recovered_by_label($deceasedDetail['recovered_by_organization']),'Team / Rescuer'=>$deceasedDetail['recovered_by_name'],'Recovery Date/Time'=>$deceasedDetail['recovery_datetime_gregorian'],'Recovery Date BS'=>$deceasedDetail['recovery_date_bs'],'Shifted To Type'=>$deceasedDetail['shifted_to_type'],'Shifted To'=>$deceasedDetail['current_mortuary'],'Storage Location'=>$deceasedDetail['current_storage_location'],'Responsible Officer'=>$deceasedDetail['recovery_officer_name'],'Responsible Phone'=>$deceasedDetail['recovery_officer_phone'],'Clothing / Huliya'=>$deceasedDetail['clothing'],'Tattoos'=>$deceasedDetail['tattoos'],'Scars'=>$deceasedDetail['scars'],'Birthmarks'=>$deceasedDetail['birthmarks'],'Documents Found'=>$deceasedDetail['documents_found'],'Other Belongings'=>$deceasedDetail['other_belongings'],'Body Bag No.'=>$deceasedDetail['body_bag_no'],'Seal No.'=>$deceasedDetail['seal_no'],'Fingerprint Reference'=>$deceasedDetail['fingerprint_reference'],'Fingerprint Result'=>$deceasedDetail['fingerprint_result'],'DNA Sample ID'=>$deceasedDetail['dna_sample_id'],'DNA Lab'=>$deceasedDetail['dna_lab'],'DNA Reference'=>$deceasedDetail['dna_reference'],'DNA Result'=>$deceasedDetail['dna_result'],'Dental Summary'=>$deceasedDetail['dental_summary'],'Post-mortem Ref'=>$deceasedDetail['postmortem_reference'],'Identification Method'=>$deceasedDetail['official_identification_method'],'Forensic Notes'=>$deceasedDetail['forensic_notes']]; }
 else { $rows=['Name / नाम'=>$case['name'],'Age'=>$case['age'],'Gender'=>$case['gender'],'Nationality / राष्ट्रियता'=>$case['nationality_name']?:'Not recorded','Person Category / हैसियत'=>missing_person_context_label($case['missing_person_context']),'Work Institution / Office / Destination / School'=>$case['associated_place_name'],'Document Type'=>$case['nationality_code']&&$case['nationality_code']!=='NP'?identity_document_type_label($case['identity_document_type']):null,'Document Number'=>$case['nationality_code']&&$case['nationality_code']!=='NP'?$case['identity_document_number']:null,'From / घर'=>$case['from_location'],'Vehicle'=>$case['vehicle_no'],'Last Contacted BS'=>$case['last_contacted_bs'],'Last Contacted Time'=>$case['last_contacted_time'],'Last Contacted Address'=>$case['last_seen_address'],'Other Family Members Missing'=>$case['other_family_members_missing']?'Yes / हो':'No / होइन','Contact Person'=>$case['family_contact_name'],'Contact Phone'=>$case['family_contact_phone'],'Additional Notes'=>$case['additional_notes']]; }
 foreach($rows as $k=>$v): if($v!==null&&$v!==''):?><div class="meta-row"><b><?=e($k)?></b><?=nl2br(e((string)$v))?></div><?php endif; endforeach;?></div>
 <?php if($case['type']==='deceased'&&$deceasedMedia):?><div class="restricted-gallery"><?php foreach($deceasedMedia as $media):$u=base_url('admin/media/dvi-photo/'.$media['id']);?><a target="_blank" href="<?=e($u)?>"><img class="case-photo" src="<?=e($u)?>" alt="Restricted deceased photo"><small><?=e($media['label'])?></small></a><?php endforeach;?></div><?php elseif($case['photo_url']):$adminPhotoUrl=base_url($case['photo_url']);?><div class="case-photo-box"><a target="_blank" href="<?=e($adminPhotoUrl)?>"><img class="case-photo" src="<?=e($adminPhotoUrl)?>" alt="Case photo"></a></div><?php endif;?></div>

 <?php if($adminGps):?><div class="card"><div class="section-title compact-title"><div><div class="card-kicker">LATEST GPS</div><h2>Case Location Map / स्थान नक्सा</h2></div><a class="btn btn-secondary btn-sm" target="_blank" href="https://www.google.com/maps?q=<?=e($adminGps['lat'].','.$adminGps['lng'])?>">Directions</a></div><div id="adminCaseMap" class="map"></div><div class="small muted map-caption"><?=e($adminGps['label'])?> · <?=e($adminGps['lat'])?>, <?=e($adminGps['lng'])?></div></div><?php endif;?>

 <div class="card"><div class="section-title compact-title"><div><h2>Progress Timeline / प्रगति विवरण</h2><div class="muted small">Every operational update is preserved here.</div></div></div>
 <div class="timeline admin-timeline">
   <?php foreach($updates as $u):?><div class="timeline-item"><div class="timeline-dot status-dot-<?=e($u['status_after'])?>"></div><div class="timeline-content"><div class="timeline-head"><div><b><?=e(case_status_label($u['status_after']))?></b><?php if($u['condition_after']):?> <span class="condition condition-<?=e($u['condition_after'])?>"><?=e(case_condition_label($u['condition_after']))?></span><?php endif;?></div><time><?=e($u['created_at'])?></time></div><div class="small muted">Updated by <?=e($u['admin_name']?:'Admin')?><?=!empty($u['post_title'])?' · '.e($u['post_title']):''?> · <?= $u['public_visible']?'<span class="public-mark">Public</span>':'<span class="private-mark">Internal</span>' ?></div>
   <?php if($u['where_found']):?><p><b>Where found/rescued:</b> <?=e($u['where_found'])?></p><?php endif;?><?php if($u['current_location']):?><p><b>Current location:</b> <?=e($u['current_location'])?></p><?php endif;?><?php if($u['shifted_to']):?><p><b>Shifted to:</b> <?=e($u['shifted_to'])?></p><?php endif;?><?php if($u['note']):?><div class="timeline-note"><?=nl2br(e($u['note']))?></div><?php endif;?><?php if($u['latitude']!==null&&$u['longitude']!==null):?><div class="small muted">GPS: <?=e($u['latitude'])?>, <?=e($u['longitude'])?></div><?php endif;?></div></div><?php endforeach;?>
   <div class="timeline-item"><div class="timeline-dot initial-dot"></div><div class="timeline-content"><div class="timeline-head"><b>Case Registered / केस दर्ता</b><time><?=e($case['created_at'])?></time></div><div class="small muted">Initial report received.</div></div></div>
 </div></div>

 <div class="card"><h2>Internal Notes / आन्तरिक नोट</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="note"><textarea name="note" placeholder="Internal note visible only to admins..." required></textarea><button class="btn btn-secondary">Add Internal Note</button></form><?php foreach($notes as $n):?><div class="internal-note"><b><?=e($n['admin_name']??'Admin')?> · <?=e($n['created_at'])?></b><div><?=nl2br(e($n['note']))?></div></div><?php endforeach;?></div>
</div>

<div class="case-side-column">
 <?php if(in_array($admin['role'],['operator','approver','superadmin'],true) && $case['status']!=='closed'):?>
 <div class="card progress-update-card"><div class="card-kicker">OPERATIONS UPDATE</div><h2>Post Case Update</h2><p class="muted small">Update what is currently known. Checked “Public tracking” information will immediately appear to anyone tracking this Case ID.</p>
 <form method="post" id="progressForm"><?=csrf_field()?><input type="hidden" name="action" value="progress_update">
 <label>Status / स्थिति<select name="status" id="progressStatus" required><?php foreach($allowedStatuses as $s):?><option value="<?=e($s)?>" <?=$case['status']===$s?'selected':''?>><?=e(case_status_label($s))?> — <?=e(case_status_label($s,'np'))?></option><?php endforeach;?></select></label>
 <label>Condition / व्यक्ति अवस्था<select name="condition"><?php foreach(case_condition_labels() as $key=>$lbl):?><option value="<?=e($key)?>" <?=$case['current_condition']===$key?'selected':''?>><?=e($lbl[0])?> / <?=e($lbl[1])?></option><?php endforeach;?></select></label>
 <label>Where Found / Rescued<input name="where_found" value="<?=e($case['where_found'])?>" placeholder="e.g. Narayani riverbank, Ward 3"></label>
 <label>Current Location<input name="current_location" value="<?=e($case['current_location'])?>" placeholder="Where is the person now?"></label>
 <label>Shifted To<input name="shifted_to" value="<?=e($case['shifted_to'])?>" placeholder="Hospital, shelter, police office, home..."></label>
 <div class="update-gps"><div class="inline"><b>Update GPS (optional)</b><button class="btn btn-sm btn-ghost" type="button" id="getUpdateGps">Use Current GPS</button></div><div class="mini-grid"><input name="latitude" id="updateLat" inputmode="decimal" placeholder="Latitude"><input name="longitude" id="updateLng" inputmode="decimal" placeholder="Longitude"></div><div class="small muted" id="updateGpsMsg">Use this when the admin/operator is at the actual found/rescue location.</div></div>
 <label>Public Update Note<textarea name="public_note" placeholder="Example: Person found alive at 4:20 PM. First aid provided and shifted to Bharatpur Hospital."></textarea></label>
 <label class="check-label"><input type="checkbox" name="public_visible" value="1" <?=$case['type']==='deceased'?'':'checked'?>> <span><b>Show on public tracking page</b><small>Uncheck only for confidential/internal operational information.</small></span></label>
 <button class="btn btn-primary btn-block btn-lg" data-confirm="Post this case update?">Save & Publish Update</button>
 </form></div>
 <?php else:?><div class="card"><h2>Case Closed</h2><div class="alert alert-success"><?=e($case['close_reason']?:'This case has been closed.')?></div></div><?php endif;?>

 <div class="card"><h2>Closure / केस बन्द</h2><?php if(!empty($pendingClose)):?><div class="alert alert-warning"><b>Close approval pending</b><br>Requested by <?=e($pendingClose['requested_by_name']?:'Admin')?> · <?=e($pendingClose['requested_at'])?><br><span class="small"><?=e($pendingClose['reason'])?></span></div><?php if(can_close($admin['role'])):?><a class="btn btn-secondary btn-block" href="<?=e(base_url('admin/approvals.php'))?>">Review Pending Approval</a><?php endif;?><?php elseif($case['status']!=='closed'&&in_array($admin['role'],['operator','approver','superadmin'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="request_close"><label><?=can_close($admin['role'])?'Closure reason':'Reason for close approval'?><textarea name="reason" required placeholder="Why can this case be formally closed?"></textarea></label><button class="btn btn-danger btn-block" data-confirm="Confirm this close action?"><?=can_close($admin['role'])?'Close Case':'Request Close Approval'?></button></form><?php elseif($case['status']==='closed'):?><div class="alert alert-success"><b>Closed</b><br><?=e($case['close_reason'])?><br><span class="small"><?=e($case['closed_at'])?></span></div><?php else:?><div class="muted">Viewer role is read-only.</div><?php endif;?></div>
 <?php if($admin['role']==='superadmin'):?><div class="card"><h2>Archive</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="soft_delete"><label>Archive reason<textarea name="reason" required></textarea></label><button class="btn btn-danger btn-block" data-confirm="Archive this case?">Archive Case</button></form></div><?php endif;?>
</div></div>
<?php if($adminGps):?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>const acm=L.map('adminCaseMap').setView([<?=json_encode($adminGps['lat'])?>,<?=json_encode($adminGps['lng'])?>],15);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(acm);L.marker([<?=json_encode($adminGps['lat'])?>,<?=json_encode($adminGps['lng'])?>]).addTo(acm).bindPopup(<?=json_encode($adminGps['label'])?>).openPopup();</script><?php endif;?>
<script>
document.getElementById('getUpdateGps')?.addEventListener('click',()=>{const msg=document.getElementById('updateGpsMsg');if(!navigator.geolocation){msg.textContent='GPS is not supported on this device.';return;}msg.textContent='Getting GPS...';navigator.geolocation.getCurrentPosition(p=>{document.getElementById('updateLat').value=p.coords.latitude.toFixed(7);document.getElementById('updateLng').value=p.coords.longitude.toFixed(7);msg.textContent='GPS captured. Accuracy about '+Math.round(p.coords.accuracy)+' m.';},e=>msg.textContent='Could not get GPS: '+e.message,{enableHighAccuracy:true,timeout:12000,maximumAge:0});});
</script>
<?php page_footer(); ?>
