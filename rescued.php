<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/auth.php';
$admin=require_admin(['operator','approver','superadmin']);$staff=true;
page_header('Rescued Person',$staff);
?>
<div class="section-title"><div><h1><?=render_lang('Register a Rescued Person','उद्धार गरिएको व्यक्ति दर्ता')?></h1><div class="muted"><?=render_lang('For officials, rescuers, shelter houses, hospitals or responsible volunteers reporting a person who has already been rescued.','पहिले नै उद्धार गरिएको व्यक्तिको विवरण अधिकारी, उद्धारकर्ता, आश्रय गृह, अस्पताल वा जिम्मेवार स्वयंसेवकले दर्ता गर्न प्रयोग गर्ने फारम।')?></div></div><a class="btn btn-ghost" href="<?=e($staff?base_url('admin/operator_help.php'):base_url())?>">Back / पछाडि</a></div>
<?php if($staff):?><div class="operator-banner"><div><b>Operator Assisted Entry / अपरेटर सहयोग</b><div class="small"><?=e($admin['name'])?> · <?=e($admin['office_name'])?></div></div></div><?php endif;?>

<form class="card form-card practical-form" method="post" action="<?=e(base_url($staff?'submit_rescued.php?staff=1':'submit_rescued.php'))?>" enctype="multipart/form-data" id="rescuedForm">
<?=csrf_field()?>
<div class="grid">
<div class="col-12 form-group-heading"><span class="group-number">1</span><div><b><?=render_lang('Current Condition','हालको अवस्था')?></b><div class="hint"><?=render_lang('Choose the condition first. The form will show only the information needed for that condition.','पहिले अवस्था छान्नुहोस्। त्यसपछि आवश्यक विवरण मात्र देखाइनेछ।')?></div></div></div>
<div class="col-12 condition-choice-grid">
  <label class="condition-choice"><input type="radio" name="condition_level" value="safe" checked><span><b>Safe / सकुशल</b><small><?=render_lang('Can provide identity/contact details','नाम र सम्पर्क विवरण दिन सक्ने')?></small></span></label>
  <label class="condition-choice"><input type="radio" name="condition_level" value="injured"><span><b>Injured / घाइते</b><small><?=render_lang('Needs treatment or medical attention','उपचार आवश्यक')?></small></span></label>
  <label class="condition-choice"><input type="radio" name="condition_level" value="semi_conscious"><span><b>Semi-conscious / अर्धचेत</b><small><?=render_lang('Limited communication possible','सीमित रूपमा बोल्न/बुझ्न सक्ने')?></small></span></label>
  <label class="condition-choice"><input type="radio" name="condition_level" value="unconscious"><span><b>Unconscious / अचेत</b><small><?=render_lang('Cannot provide identity details','आफ्नो विवरण दिन नसक्ने')?></small></span></label>
</div>

<div class="col-12 form-group-heading"><span class="group-number">2</span><div><b><?=render_lang('Who rescued / reported the person?','कसले उद्धार / दर्ता गरेको हो?')?></b></div></div>
<div class="col-4"><label><?=render_lang('Rescuer / Reporting Body *','उद्धार / दर्ता गर्ने निकाय *')?><select name="rescued_by_type" required>
<option value="nepal_army">Nepal Army / नेपाली सेना</option><option value="nepal_police">Nepal Police / नेपाल प्रहरी</option><option value="apf">APF / सशस्त्र प्रहरी</option><option value="shelter_house">Shelter House / आश्रय गृह</option><option value="hospital">Hospital / अस्पताल</option><option value="local_volunteer">Local Volunteer / Rescuer / स्थानीय उद्धारकर्ता</option><option value="local_government">Local Government / स्थानीय तह</option><option value="ngo">NGO/INGO</option><option value="other">Other / अन्य</option>
</select></label></div>
<div class="col-4"><label><?=render_lang('Institution / Team Name *','संस्था / टोलीको नाम *')?><input name="rescuing_institution_name" maxlength="190" required placeholder="Police unit, Army unit, shelter, hospital, volunteer team..."></label></div>
<div class="col-4"><label><?=render_lang('Institution / Rescuer Phone *','संस्था / उद्धारकर्ता फोन *')?><input name="rescuing_institution_phone" inputmode="tel" required></label></div>
<div class="col-4"><label><?=render_lang('Responsible Person / Rescuer Name','जिम्मेवार व्यक्ति / उद्धारकर्ताको नाम')?><input name="rescued_by_name" maxlength="150"></label></div>
<div class="col-4"><label><?=render_lang('Rescue Date/Time','उद्धार मिति/समय')?><input type="datetime-local" name="rescue_datetime"></label></div>
<div class="col-4"><label><?=render_lang('Rescue Date BS','उद्धार मिति वि.सं.')?><input name="rescue_date_bs" placeholder="2083-05-11"></label></div>
<div class="col-8"><label><?=render_lang('Rescue / Found Location *','उद्धार / फेला परेको स्थान *')?><input name="rescue_location" id="rescueLocation" maxlength="300" required placeholder="Village, river, road, ward, district"></label></div>
<div class="col-4 gps-panel"><label>GPS</label><button class="btn btn-secondary btn-block" type="button" id="captureGps">Capture GPS / GPS लिनुहोस्</button><div class="mini-grid"><input name="rescue_latitude" id="rescueLat" placeholder="Latitude"><input name="rescue_longitude" id="rescueLng" placeholder="Longitude"></div><div class="small muted" id="gpsMsg"></div></div>

<div id="safeFields" class="col-12 conditional-panel">
<div class="form-group-heading"><span class="group-number">3</span><div><b><?=render_lang('Safe Person Details','सकुशल व्यक्तिको विवरण')?></b><div class="hint"><?=render_lang('Keep this fast: identity, contact, workplace/destination and one family contact.','छिटो भर्नुहोस्: नाम, सम्पर्क, काम/गन्तव्य र परिवार सम्पर्क।')?></div></div></div>
<div class="grid compact-grid">
<div class="col-5"><label><?=render_lang('Full Name *','पूरा नाम *')?><input name="name" id="safeName" maxlength="150"></label></div>
<div class="col-3"><label><?=render_lang('Age','उमेर')?><input type="number" name="age" min="0" max="120" inputmode="numeric"></label></div>
<div class="col-4"><label><?=render_lang('Gender *','लिङ्ग *')?><select name="gender" required><option value="Unknown">Unknown / थाहा छैन</option><option value="Male">Male / पुरुष</option><option value="Female">Female / महिला</option><option value="Other">Other / अन्य</option></select></label></div>
<div class="col-6"><label><?=render_lang('Person Mobile / Contact','व्यक्तिको मोबाइल / सम्पर्क')?><input name="person_phone" inputmode="tel"></label></div>
<div class="col-6"><label><?=render_lang('Home Address','घर ठेगाना')?><input name="permanent_address" maxlength="300"></label></div>
<div class="col-6"><label><?=render_lang('Workplace / Office','काम गर्ने ठाउँ / कार्यालय')?><input name="workplace" maxlength="190"></label></div>
<div class="col-6"><label><?=render_lang('Destination / Where they were going','गन्तव्य / कहाँ जाँदै हुनुहुन्थ्यो')?><input name="destination" maxlength="255"></label></div>
<div class="col-6"><label><?=render_lang('Family / Contact Person *','परिवार / सम्पर्क व्यक्ति *')?><input name="emergency_contact_name" id="safeContactName" maxlength="150"></label></div>
<div class="col-6"><label><?=render_lang('Family / Contact Phone *','परिवार / सम्पर्क फोन *')?><input name="emergency_contact_phone" id="safeContactPhone" inputmode="tel"></label></div>
</div></div>

<div id="assistedFields" class="col-12 conditional-panel" hidden>
<div class="form-group-heading"><span class="group-number">3</span><div><b><?=render_lang('Injured / Semi-conscious / Unconscious Person','घाइते / अर्धचेत / अचेत व्यक्तिको विवरण')?></b><div class="hint"><?=render_lang('Do not guess a name. Record approximate appearance and any documents found.','नाम अनुमान नगर्नुहोस्। अनुमानित हुलिया र भेटिएका कागजात मात्र लेख्नुहोस्।')?></div></div></div>
<div class="grid compact-grid">
<div class="col-4"><label><?=render_lang('Approx. Age From','अनुमानित उमेरदेखि')?><input type="number" name="estimated_age_min" min="0" max="120"></label></div>
<div class="col-4"><label><?=render_lang('Approx. Age To','अनुमानित उमेरसम्म')?><input type="number" name="estimated_age_max" min="0" max="120"></label></div>
<div class="col-4"><label><?=render_lang('Gender *','लिङ्ग *')?><select name="assisted_gender" id="assistedGender"><option value="Unknown">Unknown / थाहा छैन</option><option value="Male">Male / पुरुष</option><option value="Female">Female / महिला</option><option value="Other">Other / अन्य</option></select></label></div>
<div class="col-4"><label><?=render_lang('Document Type Found','भेटिएको कागजातको प्रकार')?><select name="identity_document_type"><option value="">None / थाहा छैन</option><option value="citizenship">Citizenship / नागरिकता</option><option value="passport">Passport / राहदानी</option><option value="driving_license">Driving Licence / सवारी चालक अनुमतिपत्र</option><option value="national_id">National ID / राष्ट्रिय परिचयपत्र</option><option value="student_id">Student ID / विद्यार्थी परिचयपत्र</option><option value="office_id">Office ID / कार्यालय परिचयपत्र</option><option value="other">Other / अन्य</option></select></label></div>
<div class="col-4"><label><?=render_lang('Document Number','कागजात नम्बर')?><input name="identity_document_number" maxlength="120"></label></div>
<div class="col-4"><label><?=render_lang('Any Name Visible on Document','कागजातमा देखिएको नाम')?><input name="document_name_found" maxlength="150"></label></div>
<div class="col-12"><label><?=render_lang('Document Details Found With Person','व्यक्तिसँग भेटिएको कागजातको विवरण')?><textarea name="documents_found" placeholder="Type, issuing office, number, name/address visible on document. Do not guess."></textarea></label></div>
<div class="col-6"><label><?=render_lang('Clothes / Appearance','कपडा / हुलिया')?><textarea name="clothing"></textarea></label></div>
<div class="col-6"><label><?=render_lang('Birthmark / Scar / Tattoo / Other Mark','जन्मचिन्ह / दाग / ट्याटु / अन्य चिन्ह')?><textarea name="distinguishing_marks"></textarea></label></div>
<div class="col-12"><label><?=render_lang('Injury / Condition Summary','चोट / अवस्थाको छोटो विवरण')?><textarea name="injury_summary"></textarea></label></div>
</div></div>

<div class="col-12 form-group-heading"><span class="group-number">4</span><div><b><?=render_lang('Where is the rescued person now?','उद्धार गरिएको व्यक्ति अहिले कहाँ छन्?')?></b></div></div>
<div class="col-4"><label><?=render_lang('Place Type *','स्थानको प्रकार *')?><select name="current_place_type" required><option value="rescue_shelter">Shelter House / आश्रय गृह</option><option value="hospital">Hospital / अस्पताल</option><option value="police">Police Office / प्रहरी कार्यालय</option><option value="army_apf">Army/APF Facility / सेना/सशस्त्र</option><option value="local_government">Local Government / स्थानीय तह</option><option value="temporary_camp">Temporary Camp / अस्थायी शिविर</option><option value="family">With Family / परिवारसँग</option><option value="ngo_ingo">NGO/INGO</option><option value="other">Other / अन्य</option></select></label></div>
<div class="col-8"><label><?=render_lang('Shelter / Hospital / Institution Name *','आश्रय गृह / अस्पताल / संस्थाको नाम *')?><input name="current_institution_name" maxlength="190" required></label></div>
<div class="col-8"><label><?=render_lang('Current Address / Location *','हालको ठेगाना / स्थान *')?><input name="current_institution_address" maxlength="300" required></label></div>
<div class="col-4"><label><?=render_lang('Institution Contact Phone *','संस्था सम्पर्क फोन *')?><input name="institution_contact_phone" inputmode="tel" required></label></div>
<div class="col-6"><label><?=render_lang('Institution Contact Person','संस्था सम्पर्क व्यक्ति')?><input name="institution_contact_name" maxlength="150"></label></div>
<div class="col-6"><label><?=render_lang('Post / Designation','पद')?><input name="institution_contact_post" maxlength="120"></label></div>
<div class="col-6"><label><?=render_lang('Current Photo (living person)','हालको फोटो (जीवित व्यक्ति)')?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/*" capture="environment" data-preview="photoPreview"></label><img id="photoPreview" class="photo-preview" alt="Preview"></div>
<div class="col-6"><label><?=render_lang('Other Belongings / Additional Details','अन्य सामान / थप विवरण')?><textarea name="belongings"></textarea></label></div>
<input type="hidden" name="public_photo_allowed" value="1">
</div>
<div class="form-actions"><button class="btn btn-primary btn-lg"><?=render_lang('Save Rescued Person & Generate Case ID','उद्धार व्यक्ति दर्ता गरी केस आईडी बनाउनुहोस्')?></button></div>
</form>
<script>
(()=>{
 const form=document.getElementById('rescuedForm'),safe=document.getElementById('safeFields'),assisted=document.getElementById('assistedFields');
 const safeName=document.getElementById('safeName'),safeContactName=document.getElementById('safeContactName'),safeContactPhone=document.getElementById('safeContactPhone');
 const assistedGender=document.getElementById('assistedGender');
 function sync(){const v=form.querySelector('input[name="condition_level"]:checked')?.value||'safe';const isSafe=v==='safe';safe.hidden=!isSafe;assisted.hidden=isSafe;safeName.required=isSafe;safeContactName.required=isSafe;safeContactPhone.required=isSafe;assistedGender.required=!isSafe;}
 form.querySelectorAll('input[name="condition_level"]').forEach(x=>x.addEventListener('change',sync));sync();
 const btn=document.getElementById('captureGps');btn?.addEventListener('click',()=>{const msg=document.getElementById('gpsMsg');if(!navigator.geolocation){msg.textContent='GPS not supported';return;}msg.textContent='Capturing GPS...';navigator.geolocation.getCurrentPosition(p=>{document.getElementById('rescueLat').value=p.coords.latitude.toFixed(7);document.getElementById('rescueLng').value=p.coords.longitude.toFixed(7);msg.textContent='GPS captured';},()=>{msg.textContent='Could not capture GPS. Enter location manually.';},{enableHighAccuracy:true,timeout:10000});});
})();
</script>
<?php page_footer(); ?>
