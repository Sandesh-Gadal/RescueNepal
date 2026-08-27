<?php
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/auth.php';
if($_SERVER['REQUEST_METHOD']!=='POST')redirect(base_url());
verify_csrf();
$admin=require_admin(['operator','approver','superadmin']);

$allowedConditions=['safe','injured','semi_conscious','unconscious'];
$conditionLevel=$_POST['condition_level']??'safe';if(!in_array($conditionLevel,$allowedConditions,true))$conditionLevel='safe';
$isSafe=$conditionLevel==='safe';
$canIdentify=in_array($conditionLevel,['safe','injured','semi_conscious'],true);
$name=trim((string)($_POST['name']??''));$docName=trim((string)($_POST['document_name_found']??''));
$hasName=$canIdentify&&$name!=='';
$age=($_POST['age']??'')!==''?(int)$_POST['age']:null;
$gender=$canIdentify?($_POST['gender']??'Unknown'):($_POST['assisted_gender']??'Unknown');if(!in_array($gender,['Male','Female','Other','Unknown'],true))$gender='Unknown';
$amin=($_POST['estimated_age_min']??'')!==''?(int)$_POST['estimated_age_min']:null;$amax=($_POST['estimated_age_max']??'')!==''?(int)$_POST['estimated_age_max']:null;
$location=trim((string)($_POST['rescue_location']??''));
$institution=trim((string)($_POST['current_institution_name']??''));$instAddress=trim((string)($_POST['current_institution_address']??''));
$instPhone=clean_phone((string)($_POST['institution_contact_phone']??''));$instContact=trim((string)($_POST['institution_contact_name']??''));
$rescuerInstitution=trim((string)($_POST['rescuing_institution_name']??''));$rescuerPhone=clean_phone((string)($_POST['rescuing_institution_phone']??''));
$emergencyName=trim((string)($_POST['emergency_contact_name']??''));$emergencyPhone=clean_phone((string)($_POST['emergency_contact_phone']??''));
$personPhone=clean_phone((string)($_POST['person_phone']??''));
$docType=trim((string)($_POST['identity_document_type']??''));$docNo=trim((string)($_POST['identity_document_number']??''));$docs=trim((string)($_POST['documents_found']??''));
$errors=[];
if($isSafe&&$name==='')$errors[]='Safe person name is required.';
if($age!==null&&($age<0||$age>120))$errors[]='Age must be between 0 and 120.';
if(!$isSafe&&$amin!==null&&($amin<0||$amin>120))$errors[]='Approximate age is invalid.';
if(!$isSafe&&$amax!==null&&($amax<0||$amax>120))$errors[]='Approximate age is invalid.';
if($amin!==null&&$amax!==null&&$amin>$amax)$errors[]='Approximate minimum age cannot exceed maximum age.';
if($location==='')$errors[]='Rescue / found location is required.';
if($rescuerInstitution==='')$errors[]='Rescuing/reporting institution or team is required.';
if(!valid_phone($rescuerPhone))$errors[]='Valid rescuer/institution phone is required.';
if($institution==='')$errors[]='Current shelter/hospital/institution name is required.';
if($instAddress==='')$errors[]='Current location/address is required.';
if(!valid_phone($instPhone))$errors[]='Valid current institution phone is required.';
if($isSafe){if($emergencyName==='')$errors[]='Family/contact person name is required for a safe person.';if(!valid_phone($emergencyPhone))$errors[]='Valid family/contact phone is required for a safe person.';}
$lat=trim((string)($_POST['rescue_latitude']??''));$lng=trim((string)($_POST['rescue_longitude']??''));if(($lat!==''||$lng!=='')&&!valid_latlng($lat,$lng))$errors[]='Invalid GPS coordinates.';
$allowedDocTypes=['','citizenship','passport','driving_license','national_id','student_id','office_id','other'];if(!in_array($docType,$allowedDocTypes,true))$docType='';
if(mb_strlen($docNo)>120)$errors[]='Document number is too long.';
if($errors){http_response_code(422);echo '<h2>Could not submit</h2><ul><li>'.implode('</li><li>',array_map('e',$errors)).'</li></ul><p><a href="javascript:history.back()">Go back</a></p>';exit;}

try{
 $photo=!empty($_FILES['photo'])?save_uploaded_photo($_FILES['photo']):null;$pdo=db();$pdo->beginTransaction();
 $identity=$hasName?'known':($docName!==''?'claimed':'unknown');
$displayName=$hasName?$name:($docName!==''?('Possible: '.$docName):('Unknown '.($gender==='Female'?'Female':($gender==='Male'?'Male':'Person'))));
 $status=$isSafe?'rescued_safe':'rescued_injured';
 $conditionMap=['safe'=>'safe','injured'=>'injured','semi_conscious'=>'semi_conscious','unconscious'=>'unconscious'];$condition=$conditionMap[$conditionLevel];
 $token=random_token(20);$familyName=($hasName&&$emergencyName!=='')?$emergencyName:($instContact!==''?$instContact:$institution);$familyPhone=($hasName&&valid_phone($emergencyPhone))?$emergencyPhone:$instPhone;
 $notes=trim((string)($_POST['injury_summary']??''));
 $stmt=$pdo->prepare("INSERT INTO cases(type,name,age,gender,from_location,last_seen_address,photo_url,thumb_url,family_contact_name,family_contact_phone,additional_notes,status,current_condition,current_location,where_found,shifted_to,status_updated_at,public_token,is_public,created_by_admin_id) VALUES ('rescued',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,1,?)");
 $stmt->execute([$displayName,$canIdentify?$age:null,$gender,trim((string)($_POST['permanent_address']??'')),$location,$photo['photo']??null,$photo['thumb']??null,$familyName,$familyPhone,$notes,$status,$condition,$institution,$location,$institution,$token,$admin['id']??null]);
 $id=(int)$pdo->lastInsertId();$code=case_code($id);$pdo->prepare('UPDATE cases SET case_code=? WHERE id=?')->execute([$code,$id]);
 $rescueDt=trim((string)($_POST['rescue_datetime']??''));$rescueDt=$rescueDt!==''?str_replace('T',' ',$rescueDt).(strlen($rescueDt)<=16?':00':''):null;
 $conscious=$conditionLevel==='unconscious'?0:1;$communicate=in_array($conditionLevel,['semi_conscious','unconscious'],true)?0:1;
 $medical=$isSafe?'not_required':(($_POST['current_place_type']??'')==='hospital'?'admitted':'first_aid');
 $sql="INSERT INTO rescued_person_details(case_id,identity_status,nickname,estimated_age_min,estimated_age_max,permanent_address,person_phone,workplace,destination,emergency_contact_name,emergency_contact_phone,documents_found,identity_document_type,identity_document_number,rescue_datetime_gregorian,rescue_date_bs,rescue_location,rescue_latitude,rescue_longitude,rescued_by_name,rescued_by_type,rescuing_institution_name,rescuing_institution_phone,condition_level,conscious,can_communicate,medical_attention,injury_summary,special_assistance,current_place_type,current_institution_name,current_institution_address,institution_contact_name,institution_contact_post,institution_contact_phone,institution_alt_phone,institution_office_phone,institution_email,language_spoken,height_cm,clothing,distinguishing_marks,belongings,public_photo_allowed) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
 $vals=[$id,$identity,'',$hasName?null:$amin,$hasName?null:$amax,trim((string)($_POST['permanent_address']??'')),$personPhone,trim((string)($_POST['workplace']??'')),trim((string)($_POST['destination']??'')),$emergencyName,$emergencyPhone,$docs,$docType!==''?$docType:null,$docNo!==''?$docNo:null,$rescueDt,trim((string)($_POST['rescue_date_bs']??'')),$location,$lat!==''?(float)$lat:null,$lng!==''?(float)$lng:null,trim((string)($_POST['rescued_by_name']??'')),trim((string)($_POST['rescued_by_type']??'')),$rescuerInstitution,$rescuerPhone,$conditionLevel,$conscious,$communicate,$medical,$notes,'',$_POST['current_place_type']??'other',$institution,$instAddress,$instContact,trim((string)($_POST['institution_contact_post']??'')),$instPhone,'','','','',null,trim((string)($_POST['clothing']??'')),trim((string)($_POST['distinguishing_marks']??'')),trim((string)($_POST['belongings']??'')),1];
 $pdo->prepare($sql)->execute($vals);
 $pdo->prepare("INSERT INTO case_updates(case_id,admin_id,update_category,status_after,condition_after,where_found,current_location,shifted_to,latitude,longitude,note,public_visible) VALUES (?,?,'rescue',?,?,?,?,?,?,?,?,1)")->execute([$id,$admin['id']??null,$status,$condition,$location,$institution,$institution,$lat!==''?(float)$lat:null,$lng!==''?(float)$lng:null,'Rescued person registered by '.$rescuerInstitution.'.']);
 $pdo->commit();if(!$admin)log_public_submission();else audit((int)$admin['id'],'register_rescued_person',$id,$code.' · '.$conditionLevel);run_case_reconciliation($id,$admin['id']??null);redirect(base_url('success.php?'.http_build_query(array_filter(['staff'=>$admin?1:null,'token'=>$token]))));
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code(500);echo '<h2>Submission failed</h2><p>'.e($e->getMessage()).'</p>';}
