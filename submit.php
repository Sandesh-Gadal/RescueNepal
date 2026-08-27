<?php
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/countries.php';
if($_SERVER['REQUEST_METHOD']!=='POST') redirect(base_url());
// verify_csrf() starts the application's configured session itself.
// Do not call PHP's default session_start() here, or the CSRF token will be
// checked against a different session on shared hosting.
verify_csrf();
$staffRoute=!empty($_GET['staff']);
$existingAdmin=current_admin();
if($staffRoute){
    $admin=require_admin(['operator','approver','superadmin']);
}else{
    if($existingAdmin){http_response_code(409);exit('Staff submissions must use the Admin New Report route.');}
    $admin=null;
    public_rate_limit();
}

$type=($_POST['type']??'missing')==='rescue_waiting'?'rescue_waiting':'missing';
$errors=[];
$name=trim($_POST['name']??'');
$age=($_POST['age']??'')!==''?(int)$_POST['age']:null;
$phone=clean_phone($_POST['family_contact_phone']??'');

if($name==='') $errors[]='Name is required.';
if($age!==null && ($age<0||$age>120)) $errors[]='Age must be between 0 and 120.';
if(!valid_phone($phone)) $errors[]='Please enter a valid Nepal or international phone number.';
if($type==='missing' && trim($_POST['family_contact_name']??'')==='') $errors[]='Family / contact person name is required.';

$lastBs='';
$lastTime=null;
$nationalityCode=null;
$nationalityName=null;
$foreignCategory=null;
$documentType=null;
$documentNumber=null;
$missingContext=null;
$associatedPlace=null;
if($type==='missing'){
    $countries=country_list();
    $nationalityCode=strtoupper(trim((string)($_POST['nationality_code']??'NP')));
    if(!isset($countries[$nationalityCode])){
        $errors[]='Please select a valid nationality.';
        $nationalityCode='NP';
    }
    $nationalityName=$countries[$nationalityCode]??'Nepal';
    $missingContext=trim((string)($_POST['missing_person_context']??''));
    $allowedContexts=['local','worker','tourist','student','other'];
    if(!in_array($missingContext,$allowedContexts,true)) $errors[]='Please select whether the person was local, worker, tourist, student or other.';
    $associatedPlace=trim((string)($_POST['associated_place_name']??''));
    if(mb_strlen($associatedPlace)>255) $errors[]='Institution/destination name is too long.';
    if($nationalityCode!=='NP'){
        $foreignCategory=in_array($missingContext,['tourist','worker','student'],true)?$missingContext:'other';
        $documentType=trim((string)($_POST['identity_document_type']??''));
        $allowedDocumentTypes=['','passport','visa','work_permit','residence_permit','national_id','other'];
        if(!in_array($documentType,$allowedDocumentTypes,true)) $documentType='';
        $documentNumber=trim((string)($_POST['identity_document_number']??''));
        if(mb_strlen($documentNumber)>120) $errors[]='Document number is too long.';
        $documentType=$documentType!==''?$documentType:null;
        $documentNumber=$documentNumber!==''?$documentNumber:null;
    }

    $year=(string)($_POST['last_contacted_year']??'2083');
    $month=(string)($_POST['last_contacted_month']??'');
    $day=(int)($_POST['last_contacted_day']??0);
    $time=trim($_POST['last_contacted_time']??'');
    if($year!=='2083') $errors[]='Last contacted year must be 2083 BS.';
    if(!in_array($month,['04','05'],true)) $errors[]='Last contacted month must be Shrawan or Bhadra.';
    if($day<1 || $day>32) $errors[]='Last contacted day must be between 1 and 32.';
    if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)) $errors[]='Please enter the last contacted time.';
    if(trim($_POST['last_seen_address']??'')==='') $errors[]='Last contacted address is required.';
    $lastBs='2083-'.$month.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);
    $lastTime=$time!==''?$time.':00':null;
}

$totalPersons=null;
if($type==='rescue_waiting'){
    if(!valid_latlng($_POST['latitude']??null,$_POST['longitude']??null)) $errors[]='Valid GPS latitude and longitude are required.';
    if(trim($_POST['rescue_address']??'')==='') $errors[]='Address is required.';
    if(trim($_POST['rescue_description']??'')==='') $errors[]='Situation is required.';
    $totalPersons=(int)($_POST['total_persons']??0);
    if($totalPersons<1 || $totalPersons>10000) $errors[]='Total persons must be at least 1.';
}

if($errors){
    http_response_code(422);
    echo '<h2>Could not submit</h2><ul><li>'.implode('</li><li>',array_map('e',$errors)).'</li></ul><p><a href="javascript:history.back()">Go back</a></p>';
    exit;
}

try{
    $photo=null;
    if(!empty($_FILES['photo'])) $photo=save_uploaded_photo($_FILES['photo']);
    $status=($_POST['action']??'publish')==='draft'?'draft':'open';
    $publicToken=random_token(20);
    $contactName=$type==='rescue_waiting'?$name:trim($_POST['family_contact_name']??'');
    $address=$type==='rescue_waiting'?trim($_POST['rescue_address']??''):trim($_POST['last_seen_address']??'');

    $sql='INSERT INTO cases(
        type,name,age,gender,from_location,missing_person_context,associated_place_name,purpose_en,purpose_np,vehicle_no,
        nationality_code,nationality_name,foreign_national_category,identity_document_type,identity_document_number,
        last_contacted_gregorian,last_contacted_bs,last_contacted_time,last_seen_address,
        photo_url,thumb_url,family_contact_name,family_contact_phone,additional_notes,
        rescue_description,total_persons,danger_level,needs_rescue,other_family_members_missing,
        status,public_token,is_public,created_by_admin_id,created_at,updated_at
    ) VALUES (
        :type,:name,:age,:gender,:from_location,:missing_context,:associated_place,:purpose_en,:purpose_np,:vehicle_no,
        :nationality_code,:nationality_name,:foreign_category,:document_type,:document_number,
        NULL,:last_bs,:last_time,:address,
        :photo,:thumb,:contact_name,:phone,:notes,
        :rescue_description,:total_persons,NULL,:needs_rescue,:other_family_missing,
        :status,:public_token,1,:created_by_admin_id,NOW(),NOW()
    )';
    $stmt=db()->prepare($sql);
    $stmt->execute([
        ':type'=>$type,
        ':name'=>$name,
        ':age'=>$age,
        ':gender'=>$type==='missing'?($_POST['gender']??'Unknown'):'Unknown',
        ':from_location'=>$type==='missing'?trim($_POST['from_location']??''):'',
        ':missing_context'=>$type==='missing'?$missingContext:null,
        ':associated_place'=>$type==='missing'?$associatedPlace:null,
        ':purpose_en'=>$type==='missing'?$missingContext:'',
        ':purpose_np'=>$type==='missing'?$associatedPlace:'',
        ':vehicle_no'=>$type==='missing'?trim($_POST['vehicle_no']??''):'',
        ':nationality_code'=>$type==='missing'?$nationalityCode:null,
        ':nationality_name'=>$type==='missing'?$nationalityName:null,
        ':foreign_category'=>$type==='missing'?$foreignCategory:null,
        ':document_type'=>$type==='missing'?$documentType:null,
        ':document_number'=>$type==='missing'?$documentNumber:null,
        ':last_bs'=>$type==='missing'?$lastBs:'',
        ':last_time'=>$type==='missing'?$lastTime:null,
        ':address'=>$address,
        ':photo'=>$photo['photo']??null,
        ':thumb'=>$photo['thumb']??null,
        ':contact_name'=>$contactName,
        ':phone'=>$phone,
        ':notes'=>trim($_POST['additional_notes']??''),
        ':rescue_description'=>$type==='rescue_waiting'?trim($_POST['rescue_description']??''):'',
        ':total_persons'=>$type==='rescue_waiting'?$totalPersons:null,
        ':needs_rescue'=>$type==='rescue_waiting'?1:0,
        ':other_family_missing'=>$type==='missing'&&!empty($_POST['other_family_members_missing'])?1:0,
        ':status'=>$status,
        ':public_token'=>$publicToken,
        ':created_by_admin_id'=>$admin['id']??null,
    ]);

    $id=(int)db()->lastInsertId();
    $code=case_code($id);
    db()->prepare('UPDATE cases SET case_code=? WHERE id=?')->execute([$code,$id]);

    if($type==='rescue_waiting'){
        db()->prepare('INSERT INTO rescue_locations(case_id,latitude,longitude,address,accuracy,timestamp_gregorian,timestamp_bs) VALUES (?,?,?,?,?,NOW(),NULL)')
            ->execute([$id,(float)$_POST['latitude'],(float)$_POST['longitude'],$address,($_POST['accuracy']??'')!==''?(float)$_POST['accuracy']:null]);
    }
    if(!$admin) log_public_submission();
    if($admin){ audit((int)$admin['id'],'operator_create_case',$id,'Created '.$type.' case '.$code.' via assisted form'); }
    redirect(base_url('success.php?'.http_build_query(array_filter(['staff'=>$admin?1:null,'token'=>$publicToken]))));
}catch(Throwable $e){
    http_response_code(500);
    echo '<h2>Submission failed</h2><p>'.e($e->getMessage()).'</p><p><a href="javascript:history.back()">Go back</a></p>';
}
