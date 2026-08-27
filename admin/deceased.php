<?php
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/auth.php';
$admin=require_admin(['operator','approver','superadmin']);$errors=[];

function uploaded_photo_items(array $files): array {
    if(!isset($files['name'])) return [];
    if(!is_array($files['name'])) return [$files];
    $out=[];foreach($files['name'] as $i=>$name){if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;$out[]=['name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];}
    return $out;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $gender=$_POST['gender']??'Unknown';if(!in_array($gender,['Male','Female','Other','Unknown'],true))$gender='Unknown';
 $approxAge=($_POST['approx_age']??'')!==''?(int)$_POST['approx_age']:null;if($approxAge!==null&&($approxAge<0||$approxAge>120))$errors[]='Approximate age must be between 0 and 120.';
 $recoveryLocation=trim((string)($_POST['recovery_location']??''));$officialContact=trim((string)($_POST['recovery_officer_name']??''));$officialPhone=clean_phone((string)($_POST['recovery_officer_phone']??''));
 $mortuary=trim((string)($_POST['current_mortuary']??''));$shiftType=trim((string)($_POST['shifted_to_type']??''));$bodyCondition=trim((string)($_POST['body_condition']??''));
 $recoveredOrg=trim((string)($_POST['recovered_by_organization']??''));
 $allowedOrg=['nepal_army','nepal_police','apf','local_volunteers','other'];if(!in_array($recoveredOrg,$allowedOrg,true))$errors[]='Select who recovered the body.';
 if($recoveryLocation==='')$errors[]='Found / recovery location is required.';if($officialContact==='')$errors[]='Responsible officer/contact name is required.';if(!valid_phone($officialPhone))$errors[]='Valid responsible contact phone is required.';if($mortuary==='')$errors[]='Shifted-to hospital/mortuary name is required.';if($bodyCondition==='')$errors[]='Condition of the body is required.';
 $lat=trim((string)($_POST['recovery_latitude']??''));$lng=trim((string)($_POST['recovery_longitude']??''));if(($lat!==''||$lng!=='')&&!valid_latlng($lat,$lng))$errors[]='Invalid recovery GPS.';
 $photoItems=uploaded_photo_items($_FILES['photos']??[]);if(count($photoItems)>4)$errors[]='Upload a maximum of 4 deceased-person photos.';
 if(!$errors){
  try{
   $savedPhotos=[];foreach($photoItems as $item)$savedPhotos[]=save_restricted_photo($item,'evidence');
   $pdo=db();$pdo->beginTransaction();$token=random_token(20);$suspected=trim((string)($_POST['suspected_name']??''));$display=$suspected!==''?$suspected:'Unidentified Deceased';$primary=$savedPhotos[0]??null;
   $stmt=$pdo->prepare("INSERT INTO cases(type,name,age,gender,from_location,last_seen_address,photo_url,thumb_url,family_contact_name,family_contact_phone,additional_notes,status,current_condition,current_location,where_found,shifted_to,status_updated_at,public_token,is_public,created_by_admin_id) VALUES ('deceased',?,?,?,?,?,?,?,?,?,?, 'identity_unknown','deceased',?,?,?,?,?,1,?)");
   $stmt->execute([$display,null,$gender,'',$recoveryLocation,$primary,null,$officialContact,$officialPhone,trim((string)($_POST['additional_notes']??'')),$mortuary,$recoveryLocation,$mortuary,date('Y-m-d H:i:s'),$token,$admin['id']]);
   $id=(int)$pdo->lastInsertId();$code=case_code($id);$bodyId='RN-DVI-'.date('ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT).'-'.strtoupper(substr(random_token(3),0,4));$pdo->prepare('UPDATE cases SET case_code=? WHERE id=?')->execute([$code,$id]);
   foreach($savedPhotos as $i=>$path)$pdo->prepare("INSERT INTO case_media(case_id,media_kind,file_path,label,is_primary,uploaded_by_admin) VALUES (?,'deceased_photo',?,?,?,?)")->execute([$id,$path,'Recovery photo '.($i+1),$i===0?1:0,$admin['id']]);
   $dt=trim((string)($_POST['recovery_datetime']??''));$dt=$dt!==''?str_replace('T',' ',$dt).(strlen($dt)<=16?':00':''):null;
   $sql='INSERT INTO deceased_details(case_id,body_id,suspected_name,estimated_age_min,estimated_age_max,height_cm,build_description,hair_description,facial_hair_description,clothing,footwear,jewellery,tattoos,scars,birthmarks,documents_found,devices_found,other_belongings,recovery_datetime_gregorian,recovery_date_bs,recovery_location,recovery_latitude,recovery_longitude,river_waterbody,recovered_by_name,recovered_by_organization,police_office,recovery_officer_name,recovery_officer_post,recovery_officer_phone,body_bag_no,seal_no,current_mortuary,current_storage_location,fingerprint_collected,fingerprint_reference,fingerprint_result,dna_collected,dna_sample_id,dna_lab,dna_reference,dna_result,dental_summary,postmortem_reference,body_condition,shifted_to_type,forensic_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
   $vals=[$id,$bodyId,$suspected,$approxAge,$approxAge,($_POST['height_cm']??'')!==''?(float)$_POST['height_cm']:null,trim((string)($_POST['build_description']??'')),trim((string)($_POST['hair_description']??'')),trim((string)($_POST['facial_hair_description']??'')),trim((string)($_POST['clothing']??'')),trim((string)($_POST['footwear']??'')),trim((string)($_POST['jewellery']??'')),trim((string)($_POST['tattoos']??'')),trim((string)($_POST['scars']??'')),trim((string)($_POST['birthmarks']??'')),trim((string)($_POST['documents_found']??'')),trim((string)($_POST['devices_found']??'')),trim((string)($_POST['other_belongings']??'')),$dt,trim((string)($_POST['recovery_date_bs']??'')),$recoveryLocation,$lat!==''?(float)$lat:null,$lng!==''?(float)$lng:null,trim((string)($_POST['river_waterbody']??'')),trim((string)($_POST['recovered_by_name']??'')),$recoveredOrg,trim((string)($_POST['police_office']??'')),$officialContact,trim((string)($_POST['recovery_officer_post']??'')),$officialPhone,trim((string)($_POST['body_bag_no']??'')),trim((string)($_POST['seal_no']??'')),$mortuary,trim((string)($_POST['current_storage_location']??'')),!empty($_POST['fingerprint_collected'])?1:0,trim((string)($_POST['fingerprint_reference']??'')),trim((string)($_POST['fingerprint_result']??'')),!empty($_POST['dna_collected'])?1:0,trim((string)($_POST['dna_sample_id']??'')),trim((string)($_POST['dna_lab']??'')),trim((string)($_POST['dna_reference']??'')),trim((string)($_POST['dna_result']??'')),trim((string)($_POST['dental_summary']??'')),trim((string)($_POST['postmortem_reference']??'')),$bodyCondition,$shiftType,trim((string)($_POST['forensic_notes']??''))];
   $pdo->prepare($sql)->execute($vals);
   $pdo->prepare("INSERT INTO body_custody_events(case_id,event_type,event_datetime,event_date_bs,to_location,body_bag_no,seal_no,handled_by_name,handled_by_post,handled_by_office,admin_id,notes) VALUES (?,'recovered',COALESCE(?,NOW()),?,?,?,?,?,?,?,?,?)")->execute([$id,$dt,trim((string)($_POST['recovery_date_bs']??'')),$mortuary,trim((string)($_POST['body_bag_no']??'')),trim((string)($_POST['seal_no']??'')),$officialContact,trim((string)($_POST['recovery_officer_post']??'')),trim((string)($_POST['police_office']??'')),$admin['id'],'Initial recovery registration · '.$bodyCondition]);
   $pdo->prepare("INSERT INTO case_updates(case_id,admin_id,update_category,status_after,condition_after,where_found,current_location,shifted_to,latitude,longitude,note,public_visible) VALUES (?,?,'found','identity_unknown','deceased',?,?,?,?,?,'Recovered deceased person registered. Trace ID: {$bodyId}',1)")->execute([$id,$admin['id'],$recoveryLocation,$mortuary,$mortuary,$lat!==''?(float)$lat:null,$lng!==''?(float)$lng:null]);
   $pdo->commit();audit($admin['id'],'register_deceased_dvi',$id,$bodyId);run_case_reconciliation($id,$admin['id']);flash('success','Dead body registered. Trace ID: '.$bodyId);redirect(base_url('admin/reconciliation.php?id='.$id));
  }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$errors[]='Could not save: '.$e->getMessage();}
 }
}
page_header('Dead Body Registration',true);
?>
<div class="section-title"><div><div class="card-kicker">DEAD BODY TRACE / RESTRICTED</div><h1>Dead Body Registration / शव दर्ता</h1><div class="muted">Fast field registration first. Advanced forensic details can be added when available.</div></div><a class="btn btn-ghost" href="<?=e(base_url('admin/operator_help.php'))?>">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul><li><?=implode('</li><li>',array_map('e',$errors))?></li></ul></div><?php endif;?>
<form class="card form-card practical-form" method="post" enctype="multipart/form-data" id="deadBodyForm"><?=csrf_field()?>
<div class="grid">
<div class="col-12 dvi-warning"><b>Trace first, identify carefully.</b><p>A unique Body Trace ID is generated automatically. Clothing, documents or visual recognition can help tracing but do not by themselves constitute final forensic identification.</p></div>
<div class="col-12 form-group-heading"><span class="group-number">1</span><div><b>Basic Body Details / शवको आधारभूत विवरण</b></div></div>
<div class="col-4"><label>Approx. Gender / अनुमानित लिङ्ग<select name="gender"><option value="Unknown">Unknown / थाहा छैन</option><option value="Male">Male / पुरुष</option><option value="Female">Female / महिला</option><option value="Other">Other / अन्य</option></select></label></div>
<div class="col-4"><label>Approx. Age / अनुमानित उमेर<input type="number" name="approx_age" min="0" max="120" placeholder="40"></label></div>
<div class="col-4"><label>Suspected Name (if document suggests one)<input name="suspected_name" placeholder="Not confirmed"></label></div>
<div class="col-12"><label>Condition of Dead Body / शवको अवस्था *<textarea name="body_condition" required placeholder="Fresh / decomposed / injured / incomplete visibility / other observable condition. Keep description factual and non-graphic."></textarea></label></div>
<div class="col-6"><label>Clothes / हुलिया - कपडा<textarea name="clothing" placeholder="Shirt, trousers, jacket, colour, shoes..."></textarea></label></div>
<div class="col-6"><label>Birthmark / Tattoo / Scar / जन्मचिन्ह / ट्याटु / दाग<textarea name="birthmarks" placeholder="Birthmark..."></textarea><textarea name="tattoos" placeholder="Tattoo..." style="margin-top:8px"></textarea><textarea name="scars" placeholder="Scar / other identifying mark..." style="margin-top:8px"></textarea></label></div>
<div class="col-6"><label>Documents Found / भेटिएका कागजात<textarea name="documents_found" placeholder="Citizenship, passport, licence, ID card; record visible name/number/office carefully."></textarea></label></div>
<div class="col-6"><label>Other Details / Belongings / अन्य विवरण<textarea name="other_belongings" placeholder="Jewellery, bag, phone, keys, special belongings..."></textarea></label></div>
<div class="col-12"><label>Dead Body Photos / शवका फोटो (up to 4)<input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,image/*"><span class="hint">Restricted. Never shown in public family search.</span></label></div>

<div class="col-12 form-group-heading"><span class="group-number">2</span><div><b>Found / Recovery Details / फेला परेको विवरण</b></div></div>
<div class="col-8"><label>Found Location * / फेला परेको स्थान *<input name="recovery_location" required placeholder="River, road, village, ward, district, landmark"></label></div>
<div class="col-4"><label>River / Waterbody<input name="river_waterbody" placeholder="Narayani, Trishuli..."></label></div>
<div class="col-4"><label>Recovered By * / कसले उद्धार/बरामद गर्‍यो *<select name="recovered_by_organization" required><option value="nepal_army">Nepal Army / नेपाली सेना</option><option value="nepal_police">Nepal Police / नेपाल प्रहरी</option><option value="apf">APF / सशस्त्र प्रहरी</option><option value="local_volunteers">Local Volunteers / स्थानीय स्वयंसेवक</option><option value="other">Other / अन्य</option></select></label></div>
<div class="col-4"><label>Team / Rescuer Name<input name="recovered_by_name"></label></div>
<div class="col-4"><label>Police / Coordinating Office<input name="police_office"></label></div>
<div class="col-4"><label>Recovery Date/Time<input type="datetime-local" name="recovery_datetime"></label></div><div class="col-4"><label>Recovery Date BS<input name="recovery_date_bs" placeholder="2083-05-11"></label></div><div class="col-4"><label>Body Bag / Seal (if used)<input name="body_bag_no" placeholder="Body bag no."><input name="seal_no" placeholder="Seal no." style="margin-top:8px"></label></div>
<div class="col-6"><label>Recovery Latitude<input name="recovery_latitude" inputmode="decimal"></label></div><div class="col-6"><label>Recovery Longitude<input name="recovery_longitude" inputmode="decimal"></label></div>

<div class="col-12 form-group-heading"><span class="group-number">3</span><div><b>Shifted To / कहाँ सारियो</b></div></div>
<div class="col-4"><label>Shifted To Type *<select name="shifted_to_type" id="shiftedType" required><option value="bharatpur_hospital">Bharatpur Hospital / भरतपुर अस्पताल</option><option value="other_hospital">Other Hospital / अन्य अस्पताल</option><option value="mortuary">Dead Body House / Mortuary / शव गृह</option><option value="police_facility">Police Facility / प्रहरी परिसर</option><option value="other">Other / अन्य</option></select></label></div>
<div class="col-8"><label>Hospital / Dead Body House / Institution Name *<input name="current_mortuary" id="currentMortuary" required value="Bharatpur Hospital"></label></div>
<div class="col-8"><label>Storage / Exact Location<input name="current_storage_location" placeholder="Mortuary room, cold room, body rack/reference"></label></div>
<div class="col-4"><label>Responsible Contact Phone *<input name="recovery_officer_phone" required inputmode="tel"></label></div>
<div class="col-4"><label>Responsible Officer / Contact *<input name="recovery_officer_name" required></label></div><div class="col-4"><label>Post / Rank<input name="recovery_officer_post"></label></div><div class="col-4"><label>Office<input name="recovery_office_name" placeholder="Office / institution"></label></div>

<div class="col-12"><details class="advanced-details"><summary><b>Advanced Forensic / PM Details</b> — fill later if available</summary><div class="grid compact-grid advanced-grid">
<div class="col-3"><label>Height cm<input type="number" step="0.1" name="height_cm" min="30" max="250"></label></div><div class="col-3"><label>Build<input name="build_description"></label></div><div class="col-3"><label>Hair<input name="hair_description"></label></div><div class="col-3"><label>Facial Hair<input name="facial_hair_description"></label></div>
<div class="col-4"><label>Footwear<textarea name="footwear"></textarea></label></div><div class="col-4"><label>Jewellery<textarea name="jewellery"></textarea></label></div><div class="col-4"><label>Devices Found<textarea name="devices_found"></textarea></label></div>
<div class="col-3"><label class="check-label"><input type="checkbox" name="fingerprint_collected" value="1"><span>Fingerprint collected</span></label></div><div class="col-4"><label>Fingerprint Reference<input name="fingerprint_reference"></label></div><div class="col-5"><label>Fingerprint Result / Note<input name="fingerprint_result"></label></div>
<div class="col-3"><label class="check-label"><input type="checkbox" name="dna_collected" value="1"><span>DNA collected</span></label></div><div class="col-3"><label>DNA Sample ID<input name="dna_sample_id"></label></div><div class="col-3"><label>DNA Lab<input name="dna_lab"></label></div><div class="col-3"><label>DNA Reference<input name="dna_reference"></label></div>
<div class="col-6"><label>DNA Result / Note<textarea name="dna_result"></textarea></label></div><div class="col-6"><label>Dental Summary<textarea name="dental_summary"></textarea></label></div><div class="col-4"><label>Post-mortem Reference<input name="postmortem_reference"></label></div><div class="col-8"><label>Forensic Notes<textarea name="forensic_notes"></textarea></label></div>
</div></details></div>
<div class="col-12"><label>Additional Case Notes<textarea name="additional_notes"></textarea></label></div>
</div>
<div class="form-actions"><button class="btn btn-primary btn-lg">Register Dead Body & Generate Trace ID / शव दर्ता गरी ट्रेस आईडी बनाउनुहोस्</button></div>
</form>
<script>
(()=>{const t=document.getElementById('shiftedType'),m=document.getElementById('currentMortuary');t?.addEventListener('change',()=>{if(t.value==='bharatpur_hospital')m.value='Bharatpur Hospital';else if(m.value==='Bharatpur Hospital')m.value='';});})();
</script>
<?php page_footer(); ?>
