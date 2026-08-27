<?php
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/auth.php';

$staffRoute=!empty($_GET['staff']);
$existingAdmin=current_admin();
if($staffRoute){
    require_admin();
}else if($existingAdmin){
    redirect(base_url('receipt.php?staff=1&token='.urlencode((string)($_GET['token']??''))));
}

if(!headers_sent()){
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

$token=(string)($_GET['token']??'');
$stmt=db()->prepare('SELECT c.*,
    (SELECT latitude FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) latitude,
    (SELECT longitude FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) longitude,
    (SELECT accuracy FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) gps_accuracy
    FROM cases c WHERE c.public_token=? AND c.deleted_at IS NULL LIMIT 1');
$stmt->execute([$token]);
$case=$stmt->fetch();
if(!$case){http_response_code(404);exit('Case not found');}

$isRescue=$case['type']==='rescue_waiting';$isRescued=$case['type']==='rescued';$rescuedDetail=null;if($isRescued){$rs=db()->prepare('SELECT * FROM rescued_person_details WHERE case_id=?');$rs->execute([$case['id']]);$rescuedDetail=$rs->fetch();}
$backUrl=$staffRoute
    ? base_url('admin/case.php?id='.(int)$case['id'])
    : base_url('success.php?token='.urlencode($token));

$statusText=ucwords(str_replace('_',' ',(string)$case['status']));
$createdText=!empty($case['created_at'])?date('Y-m-d H:i',strtotime((string)$case['created_at'])):'';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title><?=e($case['case_code'])?> - Submission Receipt</title>
<style>
:root{--ink:#17212b;--muted:#66727f;--line:#dfe5ea;--soft:#f6f8fa;--accent:#1f5f49;--accent-soft:#edf6f2;--danger:#8b2f2f}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;background:#eef2f5}
body{margin:0;color:var(--ink);background:#eef2f5;font-family:Inter,system-ui,-apple-system,"Segoe UI","Noto Sans Devanagari",Arial,sans-serif;line-height:1.42}
.receipt-shell{width:min(760px,calc(100% - 24px));margin:24px auto;padding:0}
.receipt-card{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(18,35,52,.07)}
.receipt-header{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;padding:22px 24px;border-bottom:1px solid var(--line)}
.receipt-brand{min-width:0}.receipt-brand strong{display:block;font-size:18px;line-height:1.25;overflow-wrap:anywhere}.receipt-brand span{display:block;margin-top:4px;color:var(--muted);font-size:12px}
.receipt-state{flex:0 0 auto;border:1px solid #b9d8ca;background:var(--accent-soft);color:var(--accent);border-radius:999px;padding:7px 11px;font-size:11px;font-weight:800;letter-spacing:.04em}
.case-block{padding:22px 24px;background:var(--soft);border-bottom:1px solid var(--line)}
.case-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.case-id{display:block;margin-top:5px;font-size:clamp(25px,6vw,38px);font-weight:850;line-height:1.08;letter-spacing:.035em;overflow-wrap:anywhere}
.case-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.pill{display:inline-flex;align-items:center;border:1px solid var(--line);background:#fff;border-radius:999px;padding:6px 9px;font-size:11px;font-weight:700}.pill.status{color:var(--accent);border-color:#b9d8ca}
.photo-wrap{padding:20px 24px 0}.case-photo{display:block;width:min(260px,100%);max-height:320px;object-fit:cover;border:1px solid var(--line);border-radius:12px;margin:0 auto}
.details{display:grid;grid-template-columns:1fr 1fr;padding:18px 24px 24px;column-gap:22px}
.field{min-width:0;padding:12px 0;border-bottom:1px solid #edf0f2}.field.wide{grid-column:1/-1}.field span{display:block;color:var(--muted);font-size:11px;margin-bottom:4px}.field b{display:block;font-size:14px;font-weight:700;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
.receipt-footer{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px 24px;background:var(--soft);border-top:1px solid var(--line);font-size:12px}.receipt-footer div:last-child{text-align:right}.receipt-footer span{color:var(--muted);display:block}.receipt-footer b{display:block;margin-top:2px;overflow-wrap:anywhere}
.notice{margin:14px 0 0;padding:12px 14px;border:1px solid var(--line);background:#fff;border-radius:10px;color:var(--muted);font-size:12px;text-align:center}
.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.btn{appearance:none;border:1px solid var(--line);border-radius:10px;padding:12px 14px;min-height:46px;font:inherit;font-weight:750;text-align:center;text-decoration:none;cursor:pointer;background:#fff;color:var(--ink)}.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
@media(max-width:620px){
 body{background:#fff}.receipt-shell{width:100%;margin:0;padding:0}.receipt-card{border:0;border-radius:0;box-shadow:none}.receipt-header,.case-block,.photo-wrap{padding-left:16px;padding-right:16px}.details{grid-template-columns:1fr;padding:12px 16px 18px}.field.wide{grid-column:auto}.receipt-footer{grid-template-columns:1fr;padding:14px 16px}.receipt-footer div:last-child{text-align:left}.actions{grid-template-columns:1fr;padding:0 12px 12px}.notice{margin:12px}.receipt-header{gap:10px}.receipt-state{font-size:10px;padding:6px 8px}.case-id{font-size:29px;letter-spacing:.02em}}
@media(max-width:360px){.receipt-header{display:block}.receipt-state{display:inline-flex;margin-top:10px}.case-id{font-size:25px}.field b{font-size:13px}}
@media print{
 @page{size:A4;margin:10mm}
 html,body{background:#fff}.receipt-shell{width:100%;max-width:none;margin:0}.receipt-card{box-shadow:none;border:1px solid #bbb;border-radius:0}.no-print{display:none!important}.case-photo{max-height:230px}.receipt-header,.case-block{padding-top:14px;padding-bottom:14px}.field{padding:8px 0}.details{padding-top:10px;padding-bottom:14px}
}
</style>
</head>
<body>
<main class="receipt-shell">
  <section class="receipt-card" aria-label="Case submission receipt">
    <header class="receipt-header">
      <div class="receipt-brand">
        <strong><?=e(app_config()['app_name'])?></strong>
        <span>Case submission receipt / केस दर्ता विवरण</span>
      </div>
      <div class="receipt-state">SUBMITTED</div>
    </header>

    <div class="case-block">
      <div class="case-label">CASE ID / केस ID</div>
      <strong class="case-id"><?=e($case['case_code'])?></strong>
      <div class="case-meta">
        <span class="pill"><?=e(case_type_label($case['type']))?></span>
        <span class="pill status">Status: <?=e($statusText)?></span>
      </div>
    </div>

    <?php if(!empty($case['photo_url'])):?>
      <div class="photo-wrap"><img class="case-photo" src="<?=e(base_url($case['photo_url']))?>" alt="Submitted case photo"></div>
    <?php endif;?>

    <div class="details">
      <div class="field"><span>Name / नाम</span><b><?=e($case['name'])?></b></div>
      <div class="field"><span>Mobile / मोबाइल</span><b><?=e($case['family_contact_phone'])?></b></div>

      <?php if($isRescue):?>
        <div class="field"><span>Total Persons / जम्मा व्यक्ति</span><b><?=e($case['total_persons']??'—')?></b></div>
        <div class="field"><span>Address / ठेगाना</span><b><?=e($case['last_seen_address']?:'—')?></b></div>
        <div class="field wide"><span>Situation / अवस्था</span><b><?=nl2br(e($case['rescue_description']?:'—'))?></b></div>
        <div class="field wide"><span>GPS Location / GPS स्थान</span><b><?=e($case['latitude'])?>, <?=e($case['longitude'])?><?=$case['gps_accuracy']!==null?' · ±'.e(round((float)$case['gps_accuracy'])).' m':''?></b></div>
      <?php elseif($isRescued):?>
        <div class="field"><span>Identity / पहिचान</span><b><?=e(ucwords(str_replace('_',' ',(string)($rescuedDetail['identity_status']??'unknown'))))?></b></div>
        <div class="field"><span>Condition / अवस्था</span><b><?=e(case_condition_label($case['current_condition']?:'unknown'))?></b></div>
        <div class="field wide"><span>Rescue Location / उद्धार स्थान</span><b><?=e($rescuedDetail['rescue_location']??$case['where_found'])?></b></div>
        <div class="field wide"><span>Current Institution / हालको संस्था</span><b><?=e($rescuedDetail['current_institution_name']??$case['current_location'])?></b></div>
        <div class="field"><span>Institution Contact</span><b><?=e($rescuedDetail['institution_contact_name']??$case['family_contact_name'])?></b></div>
        <div class="field"><span>Institution Phone</span><b><?=e($rescuedDetail['institution_contact_phone']??$case['family_contact_phone'])?></b></div>
      <?php else:?>
        <div class="field"><span>Age / उमेर</span><b><?=e($case['age']??'—')?></b></div>
        <div class="field"><span>Gender / लिङ्ग</span><b><?=e($case['gender']?:'—')?></b></div>
        <div class="field"><span>Nationality / राष्ट्रियता</span><b><?=e($case['nationality_name']?:'Not recorded')?></b></div>
        <?php if(!empty($case['missing_person_context'])):?><div class="field"><span>Person Category / व्यक्ति प्रकार</span><b><?=e(missing_person_context_label($case['missing_person_context']))?></b></div><?php endif;?><?php if(!empty($case['associated_place_name'])):?><div class="field wide"><span>Work Institution / Office / Destination / School</span><b><?=e($case['associated_place_name'])?></b></div><?php endif;?>
        <div class="field"><span>Home / घर</span><b><?=e($case['from_location']?:'—')?></b></div>
        <div class="field"><span>Vehicle / सवारी</span><b><?=e($case['vehicle_no']?:'—')?></b></div>
        <div class="field"><span>Last Contact BS / अन्तिम सम्पर्क</span><b><?=e($case['last_contacted_bs']?:'—')?><?=!empty($case['last_contacted_time'])?' · '.e(substr((string)$case['last_contacted_time'],0,5)):''?></b></div>
        <div class="field"><span>Family Contact / परिवार सम्पर्क</span><b><?=e($case['family_contact_name']?:'—')?></b></div>
        <div class="field wide"><span>Last Contact / Seen Address</span><b><?=e($case['last_seen_address']?:'—')?></b></div>
        <div class="field wide"><span>Other family member also missing?</span><b><?=!empty($case['other_family_members_missing'])?'Yes / हो':'No / होइन'?></b></div>
        <?php if(!empty($case['identity_document_type']) || !empty($case['identity_document_number'])):?>
          <div class="field wide"><span>Optional Document</span><b><?=e(trim(($case['identity_document_type']??'').' '.($case['identity_document_number']??'')))?></b></div>
        <?php endif;?>
      <?php endif;?>

      <?php if(trim((string)$case['additional_notes'])!==''):?>
        <div class="field wide"><span>Additional Note / थप नोट</span><b><?=nl2br(e($case['additional_notes']))?></b></div>
      <?php endif;?>
    </div>

    <footer class="receipt-footer">
      <div><span>Submission</span><b>Saved successfully / सुरक्षित</b></div>
      <div><span>Submitted at</span><b><?=e($createdText)?></b></div>
    </footer>
  </section>

  <div class="notice no-print">Keep the Case ID safe. Use it to track the latest verified status of this case.</div>
  <div class="actions no-print">
    <button type="button" class="btn primary" onclick="window.print()">Print / Save PDF</button>
    <a class="btn" href="<?=e($backUrl)?>">Back</a>
  </div>
</main>
</body>
</html>
