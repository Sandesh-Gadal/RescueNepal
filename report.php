<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/countries.php';
$type=($_GET['type']??'missing')==='rescue'?'rescue_waiting':'missing';
$staffRoute=!empty($_GET['staff']);
$existingAdmin=current_admin();
if($staffRoute){
    $admin=require_admin(['operator','approver','superadmin']);
}else{
    if($existingAdmin) redirect(base_url('report.php?staff=1&type='.($type==='missing'?'missing':'rescue')));
    $admin=null;
}
$assisted=$staffRoute;
$countries=country_list();
page_header($type==='missing'?'Missing Person Report':'Rescue Request',$assisted);
?>
<div class="section-title"><div><h1><?=$type==='missing'?render_lang('Missing Person Report','हराइरहेको व्यक्ति विवरण'):render_lang('Waiting for Rescue','उद्धारको प्रतीक्षामा')?></h1><div class="muted"><?=render_lang('Fill only verified information. Required fields are marked with *.','पुष्टि भएको विवरण मात्र भर्नुहोस्। * चिन्ह भएका विवरण अनिवार्य हुन्।')?></div></div><a class="btn btn-ghost" href="<?=e($assisted?base_url('admin/operator_help.php'):base_url())?>">Back / पछाडि</a></div>

<?php if($assisted): ?>
<div class="operator-banner">
  <div><b><?=render_lang('Operator Assisted Entry','अपरेटर सहयोगमा विवरण प्रविष्टि')?></b><div class="small"><?=e($admin['name'])?> · <?=e($admin['post_title'])?> · <?=e($admin['office_name'])?></div></div>
  <a class="btn btn-sm btn-ghost" href="<?=e(base_url('admin/operator_help.php'))?>"><?=render_lang('How to Fill','फारम कसरी भर्ने')?></a>
</div>
<?php endif; ?>

<?php if($type==='rescue_waiting'): ?>
<div class="danger-banner"><h2><?=render_lang('Waiting for Rescue','उद्धारको प्रतीक्षामा')?></h2><div><?=render_lang('First capture GPS. Then confirm name, phone, number of persons and current situation.','पहिले GPS स्थान लिनुहोस्। त्यसपछि नाम, मोबाइल, जम्मा व्यक्ति र हालको अवस्था पुष्टि गर्नुहोस्।')?></div></div>

<?php else: ?>

<?php endif; ?>

<form class="card form-card" method="post" action="<?=e(base_url($assisted?'submit.php?staff=1':'submit.php'))?>" enctype="multipart/form-data" id="caseForm">
<?=csrf_field()?><input type="hidden" name="type" value="<?=e($type)?>">
<div class="grid">
<?php if($type==='missing'): ?>
<div class="col-12 form-group-heading"><span class="group-number">1</span><div><b><?=render_lang('Missing Person Details','हराइरहेको व्यक्तिको विवरण')?></b><div class="hint"><?=render_lang('Enter the person’s own details, not the reporter’s details.','यहाँ हराइरहेको व्यक्तिकै विवरण भर्नुहोस्, सूचना दिने व्यक्तिको होइन।')?></div></div></div>
<div class="col-8"><label><?=render_lang('Full Name *','पूरा नाम *')?><input name="name" maxlength="150" required autocomplete="name" placeholder="Enter full name"><span class="hint"><?=render_lang('Use the full name used by family or official records.','परिवार वा आधिकारिक कागजातमा प्रयोग भएको पूरा नाम लेख्नुहोस्।')?></span></label></div>
<div class="col-4"><label><?=render_lang('Age','उमेर')?><input type="number" name="age" min="0" max="120" inputmode="numeric" placeholder="35"></label></div>
<div class="col-4"><label><?=render_lang('Gender','लिङ्ग')?><select name="gender"><option value="Unknown">Unknown / थाहा छैन</option><option value="Male">Male / पुरुष</option><option value="Female">Female / महिला</option><option value="Other">Other / अन्य</option></select></label></div>
<div class="col-8"><label><?=render_lang('From / Home Location','घरको ठेगाना / स्थान')?><input name="from_location" maxlength="255" placeholder="Ward, Municipality, District"><span class="hint"><?=render_lang('Permanent or current home location.','स्थायी वा हाल बसोबास गर्ने स्थान।')?></span></label></div>

<div class="col-12 nationality-panel">
  <div class="nationality-main-field">
    <label><?=render_lang('Nationality *','राष्ट्रियता *')?>
      <select name="nationality_code" id="nationalityCode" required>
        <option value="NP" selected>Nepal / नेपाल</option>
        <optgroup label="Other countries / अन्य देश">
          <?php foreach($countries as $countryCode=>$countryName): if($countryCode==='NP') continue; ?>
            <option value="<?=e($countryCode)?>"><?=e($countryName)?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
      <span class="hint"><?=render_lang('Nepal is selected by default. Choose another country only when the missing person is a foreign national.','नेपाल पूर्वनिर्धारित छ। हराइरहेको व्यक्ति विदेशी नागरिक भए मात्र अर्को देश छान्नुहोस्।')?></span>
    </label>
  </div>
  <div id="foreignNationalityFields" class="foreign-nationality-fields" hidden>
    <div class="foreign-nationality-heading"><b><?=render_lang('Foreign National Document Details','विदेशी नागरिकको कागजात विवरण')?></b><span><?=render_lang('Only enter document information if available.','उपलब्ध भए मात्र कागजातको विवरण राख्नुहोस्।')?></span></div>
    <div class="grid compact-grid">
      <div class="col-6"><label><?=render_lang('Document Type (Optional)','कागजातको प्रकार (ऐच्छिक)')?>
        <select name="identity_document_type">
          <option value="">Not available / उपलब्ध छैन</option>
          <option value="passport">Passport / राहदानी</option>
          <option value="visa">Visa / भिसा</option>
          <option value="work_permit">Work Permit / कार्य अनुमति</option>
          <option value="residence_permit">Residence Permit / बसोबास अनुमति</option>
          <option value="national_id">National ID / राष्ट्रिय परिचयपत्र</option>
          <option value="other">Other / अन्य</option>
        </select>
      </label></div>
      <div class="col-6"><label><?=render_lang('Document Number (Optional)','कागजात नम्बर (ऐच्छिक)')?>
        <input name="identity_document_number" maxlength="120" autocomplete="off" placeholder="Passport / document number">
        <span class="hint"><?=render_lang('Leave blank if the number is not available.','नम्बर उपलब्ध नभए खाली छोड्न सकिन्छ।')?></span>
      </label></div>
    </div>
  </div>
</div>
<div class="col-5"><label><?=render_lang('Who were they there as? *','उहाँ त्यहाँ कुन हैसियतमा हुनुहुन्थ्यो? *')?>
<select name="missing_person_context" id="missingPersonContext" required>
<option value="">Select / छान्नुहोस्</option>
<option value="local">Local / स्थानीय</option>
<option value="worker">Worker / कामदार</option>
<option value="tourist">Tourist / पर्यटक</option>
<option value="student">Student / विद्यार्थी</option>
<option value="other">Other / अन्य</option>
</select><span class="hint"><?=render_lang('This replaces the old Purpose of Going field.','यसले पुरानो जाने उद्देश्य फिल्डलाई सरल बनाउँछ।')?></span></label></div>
<div class="col-7"><label><?=render_lang('Work Institution / Office / Destination / School Name','काम गर्ने निकाय / कार्यालय / गन्तव्य / विद्यालयको नाम')?><input name="associated_place_name" id="associatedPlaceName" maxlength="255" placeholder="Office, employer, hotel/destination, school/college..."><span class="hint"><?=render_lang('Enter the most useful place or institution connected with the person.','व्यक्तिसँग सम्बन्धित उपयोगी संस्था वा गन्तव्यको नाम लेख्नुहोस्।')?></span></label></div>
<div class="col-4"><label><?=render_lang('Vehicle No.','सवारी नं.')?><input name="vehicle_no" maxlength="80" placeholder="BA-3-1234"></label></div>
<div class="col-8"><label><?=render_lang('Recent Photo','हालसालैको फोटो')?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/*" capture="environment" data-preview="photoPreview"><span class="hint"><?=render_lang('Recommended: clear recent face photo. Max 8MB.','सिफारिस: हालसालैको स्पष्ट अनुहार देखिने फोटो। अधिकतम 8MB।')?></span></label><img id="photoPreview" class="photo-preview" alt="Preview"></div>

<div class="col-12 form-group-heading"><span class="group-number">2</span><div><b><?=render_lang('Last Contact Information','अन्तिम सम्पर्क विवरण')?></b><div class="hint"><?=render_lang('Ask the family: when and where was the last confirmed contact?','परिवारलाई सोध्नुहोस्: अन्तिम पटक निश्चित रूपमा कहिले र कहाँ सम्पर्क भएको थियो?')?></div></div></div>
<div class="col-12 bs-date-panel">
  <div class="bs-date-title"><?=render_lang('Last Contacted Date (BS only) *','अन्तिम सम्पर्क मिति (वि.सं. मात्र) *')?></div>
  <div class="grid compact-grid">
    <div class="col-4"><label><?=render_lang('Year','वर्ष')?><input value="2083" readonly aria-readonly="true"><input type="hidden" name="last_contacted_year" value="2083"></label></div>
    <div class="col-4"><label><?=render_lang('Month *','महिना *')?><select name="last_contacted_month" required><option value="04">Shrawan / श्रावण</option><option value="05">Bhadra / भाद्र</option></select></label></div>
    <div class="col-4"><label><?=render_lang('Day *','गते *')?><select name="last_contacted_day" required><?php for($d=1;$d<=32;$d++):?><option value="<?=$d?>"><?=$d?></option><?php endfor;?></select></label></div>
  </div>
</div>
<div class="col-4"><label><?=render_lang('Last Contacted Time *','अन्तिम सम्पर्क समय *')?><input type="time" name="last_contacted_time" required><span class="hint"><?=render_lang('Use the closest known time.','थाहा भएको नजिकको समय राख्नुहोस्।')?></span></label></div>
<div class="col-8"><label><?=render_lang('Last Contacted / Last Seen Address *','अन्तिम सम्पर्क / देखिएको ठेगाना *')?><input name="last_seen_address" maxlength="300" required placeholder="Road, village, landmark, ward, district"><span class="hint"><?=render_lang('Be specific: landmark, road, village or ward helps search teams.','सम्भव भएसम्म स्पष्ट स्थान, सडक, टोल, गाउँ वा वडा लेख्नुहोस्।')?></span></label></div>
<div class="col-12"><label><?=render_lang('Are other family members of this person also missing?','के यस व्यक्तिका अन्य परिवारका सदस्य पनि हराइरहेका छन्?')?><select name="other_family_members_missing"><option value="0" selected>No / होइन</option><option value="1">Yes / हो</option></select></label></div>

<div class="col-12 form-group-heading"><span class="group-number">3</span><div><b><?=render_lang('Family / Reporter Contact','परिवार / सूचना दिने व्यक्तिको सम्पर्क')?></b><div class="hint"><?=render_lang('This person should be reachable for verification and updates.','यो व्यक्ति पुष्टि र थप जानकारीका लागि सम्पर्कमा आउन सक्ने हुनुपर्छ।')?></div></div></div>
<div class="col-6"><label><?=render_lang('Family / Contact Person Name *','परिवार / सम्पर्क व्यक्तिको नाम *')?><input name="family_contact_name" maxlength="150" required placeholder="Contact person full name"></label></div>
<div class="col-6"><label><?=render_lang('Contact Phone *','सम्पर्क फोन *')?><input name="family_contact_phone" inputmode="tel" required placeholder="98XXXXXXXX or +97798XXXXXXXX"><span class="hint"><?=render_lang('Verify the number by reading it back once.','नम्बर एक पटक दोहोर्‍याएर पुष्टि गर्नुहोस्।')?></span></label></div>
<div class="col-12"><label><?=render_lang('Additional Notes','थप विवरण')?><textarea name="additional_notes" placeholder="Clothes, companion, physical marks, route, medical or other useful information..."></textarea></label></div>

<?php else: ?>
<div class="col-12 form-group-heading"><span class="group-number">1</span><div><b><?=render_lang('Contact & Location','सम्पर्क र स्थान')?></b><div class="hint"><?=render_lang('Confirm who is at the location and how rescuers can contact them.','स्थानमा को हुनुहुन्छ र उद्धार टोलीले कसरी सम्पर्क गर्ने भन्ने पुष्टि गर्नुहोस्।')?></div></div></div>
<div class="col-6"><label><?=render_lang('Name *','नाम *')?><input name="name" maxlength="150" required autocomplete="name" placeholder="Name of contact/person at location"></label></div>
<div class="col-6"><label><?=render_lang('Mobile No. *','मोबाइल नं. *')?><input name="family_contact_phone" inputmode="tel" required placeholder="98XXXXXXXX or +97798XXXXXXXX"><span class="hint"><?=render_lang('Use a phone that is currently reachable.','अहिले सम्पर्क हुन सक्ने मोबाइल नम्बर राख्नुहोस्।')?></span></label></div>
<div class="col-8"><label><?=render_lang('Address *','ठेगाना *')?><input name="rescue_address" id="rescueAddress" maxlength="300" required placeholder="Village, road, ward, landmark, district"><span class="hint"><?=render_lang('Enter a nearby landmark even if GPS is available.','GPS भए पनि नजिकको चिनिने स्थान लेख्नुहोस्।')?></span></label></div>
<div class="col-4"><label><?=render_lang('Total Persons *','जम्मा व्यक्ति *')?><input type="number" name="total_persons" min="1" max="10000" value="1" required inputmode="numeric"></label></div>

<div class="col-12 form-group-heading"><span class="group-number">2</span><div><b><?=render_lang('Current Situation','हालको अवस्था')?></b><div class="hint"><?=render_lang('Describe the immediate danger in one or two clear sentences.','तत्काल जोखिमलाई एक वा दुई स्पष्ट वाक्यमा लेख्नुहोस्।')?></div></div></div>
<div class="col-12"><label><?=render_lang('Situation *','अवस्था / परिस्थिति *')?><textarea name="rescue_description" required placeholder="Example: 5 people trapped on second floor due to flood; water is rising."></textarea></label></div>
<div class="col-6"><label><?=render_lang('Photo Upload (Optional)','फोटो अपलोड (ऐच्छिक)')?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/*" capture="environment" data-preview="photoPreview"><span class="hint"><?=render_lang('Optional. Take a safe photo only if possible. Max 8MB.','ऐच्छिक। सुरक्षित भए मात्र फोटो खिच्नुहोस्। अधिकतम 8MB।')?></span></label><img id="photoPreview" class="photo-preview" alt="Preview"></div>
<div class="col-6 gps-panel"><label><?=render_lang('GPS Location *','GPS स्थान *')?></label><button class="btn btn-primary btn-block" type="button" id="getLocation"><?=render_lang('Capture GPS Location','GPS स्थान लिनुहोस्')?></button><div class="gps-status" id="locMsg"><?=render_lang('GPS not captured yet.','GPS अझै लिइएको छैन।')?></div><div class="grid compact-grid"><div class="col-6"><input name="latitude" id="latitude" inputmode="decimal" required placeholder="Latitude"></div><div class="col-6"><input name="longitude" id="longitude" inputmode="decimal" required placeholder="Longitude"></div></div><input type="hidden" name="accuracy" id="accuracy"></div>
<div class="col-12"><label><?=render_lang('Additional Note','थप नोट')?><textarea name="additional_notes" placeholder="Injuries, elderly/children, access route, nearby landmark, urgent needs..."></textarea></label></div>
<?php endif; ?>
</div>
<div class="submit-checklist">
  <b><?=render_lang('Before Submit','पेश गर्नु अघि')?></b>
  <span><?=render_lang('Recheck name, phone and location/date once.','नाम, फोन र स्थान/मिति एक पटक फेरि जाँच गर्नुहोस्।')?></span>
</div>
<div class="form-actions mobile-sticky"><button class="btn btn-primary btn-lg" type="submit" name="action" value="publish"><?=render_lang('Submit Report','विवरण पेश गर्नुहोस्')?></button><?php if($type==='missing'):?><button class="btn btn-ghost" type="submit" name="action" value="draft"><?=render_lang('Save Draft','ड्राफ्ट सुरक्षित गर्नुहोस्')?></button><?php endif;?></div>
</form>
<script>
(function(){
 const form=document.getElementById('caseForm');
 const btn=document.getElementById('getLocation');
 const nationality=document.getElementById('nationalityCode');
 const foreignFields=document.getElementById('foreignNationalityFields');
 const foreignCategory=document.getElementById('foreignNationalCategory');
 function syncNationalityFields(){
   if(!nationality || !foreignFields) return;
   const isForeign=nationality.value && nationality.value!=='NP';
   foreignFields.hidden=!isForeign;
   if(foreignCategory) foreignCategory.required=isForeign;
   if(!isForeign){
     if(foreignCategory) foreignCategory.value='';
     foreignFields.querySelectorAll('input,select').forEach(el=>{
       if(el!==foreignCategory && el.name!=='nationality_code') el.value='';
     });
   }
 }
 if(nationality){ nationality.addEventListener('change',syncNationalityFields); syncNationalityFields(); }
 if(btn)btn.addEventListener('click',()=>{
   const msg=document.getElementById('locMsg');
   if(!navigator.geolocation){msg.textContent='Geolocation unavailable / GPS उपलब्ध छैन';msg.className='gps-status bad';return;}
   btn.disabled=true; msg.textContent='Getting GPS location... / GPS स्थान लिँदै...';msg.className='gps-status working';
   navigator.geolocation.getCurrentPosition(async p=>{
     const lat=p.coords.latitude.toFixed(7),lng=p.coords.longitude.toFixed(7);
     document.getElementById('latitude').value=lat;
     document.getElementById('longitude').value=lng;
     document.getElementById('accuracy').value=Math.round(p.coords.accuracy);
     msg.textContent='GPS captured. Accuracy about '+Math.round(p.coords.accuracy)+' m / GPS स्थान सुरक्षित भयो';msg.className='gps-status good';btn.textContent='GPS Captured / GPS सुरक्षित';
     try{
       const r=await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+lat+'&lon='+lng,{headers:{'Accept':'application/json'}});
       const j=await r.json();
       if(j.display_name && !document.getElementById('rescueAddress').value)document.getElementById('rescueAddress').value=j.display_name;
     }catch(e){}
     btn.disabled=false;
   },err=>{msg.textContent='Location failed: '+err.message;msg.className='gps-status bad';btn.disabled=false;},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
 });
 if(form)form.addEventListener('submit',e=>{
   const submitter=e.submitter;
   if(submitter && submitter.value==='draft')return;
   if(!form.checkValidity()){return;}
   if(submitter){submitter.disabled=true;submitter.dataset.oldText=submitter.textContent;submitter.textContent='Submitting... / पेश हुँदै...';}
 });
})();
</script>
<script>
(()=>{const c=document.getElementById('missingPersonContext'),p=document.getElementById('associatedPlaceName');if(!c||!p)return;const hints={local:'Local area / ward / community (optional)',worker:'Employer / office / work site name',tourist:'Hotel / destination / trekking route / attraction',student:'School / college / institution name',other:'Related place / institution / destination'};const sync=()=>{p.placeholder=hints[c.value]||'Office, employer, destination or school name';};c.addEventListener('change',sync);sync();})();
</script>
<?php page_footer(); ?>
