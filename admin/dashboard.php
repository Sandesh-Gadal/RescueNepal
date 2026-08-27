<?php
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/countries.php';
$admin=require_admin();

$nationalitySchemaReady=(bool)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cases' AND COLUMN_NAME='nationality_code'")->fetchColumn();
if(!$nationalitySchemaReady){
    http_response_code(503);
    page_header('Database Schema Incomplete',true);
    echo '<div class="card"><h1>Database schema incomplete</h1><p>This installation is missing the latest nationality fields. For a fresh server, use an empty database and run the /setup installer included with this package.</p></div>';
    page_footer();
    exit;
}

$q=trim($_GET['q']??'');
$status=$_GET['status']??'';
$type=$_GET['type']??'';
$condition=$_GET['condition']??'';
$view=$_GET['view']??'all';
$from=$_GET['from']??'';
$to=$_GET['to']??'';
$ageMin=$_GET['age_min']??'';
$ageMax=$_GET['age_max']??'';
$vehicle=trim($_GET['vehicle']??'');
$location=trim($_GET['location']??'');
$bs=trim($_GET['bs']??'');
$nationality=strtoupper(trim((string)($_GET['nationality']??'')));
$countries=country_list();
$page=max(1,(int)($_GET['page']??1));
$per=30;

$allowedViews=['all','missing','foreign_missing','rescue','rescued','active','under_review','in_progress','found_rescued','medical','deceased','closed','draft'];
if(!in_array($view,$allowedViews,true)) $view='all';

$where=['c.deleted_at IS NULL'];
$args=[];

switch($view){
    case 'missing': $where[]="c.type='missing'"; break;
    case 'foreign_missing': $where[]="c.type='missing' AND c.nationality_code IS NOT NULL AND c.nationality_code<>'NP'"; break;
    case 'rescue': $where[]="c.type='rescue_waiting'"; break;
    case 'rescued': $where[]="c.type='rescued'"; break;
    case 'active': $where[]="c.status NOT IN ('closed','draft')"; break;
    case 'under_review': $where[]="c.status='under_review'"; break;
    case 'in_progress': $where[]="c.status IN ('searching','rescue_dispatched')"; break;
    case 'found_rescued': $where[]="c.status IN ('located','found_alive','found_injured','rescued_safe','rescued_injured','shifted')"; break;
    case 'medical': $where[]="(c.current_condition IN ('injured','minor_injury','serious','critical','semi_conscious','unconscious','unable_communicate') OR c.status IN ('found_injured','rescued_injured'))"; break;
    case 'deceased': $where[]="c.type='deceased'"; break;
    case 'closed': $where[]="c.status='closed'"; break;
    case 'draft': $where[]="c.status='draft'"; break;
}

if($q!==''){
    $where[]='(c.case_code LIKE ? OR c.name LIKE ? OR c.family_contact_name LIKE ? OR c.family_contact_phone LIKE ? OR c.last_seen_address LIKE ? OR c.current_location LIKE ? OR c.where_found LIKE ? OR c.shifted_to LIKE ? OR c.from_location LIKE ? OR c.nationality_name LIKE ? OR c.identity_document_number LIKE ? OR c.foreign_national_category LIKE ?)';
    for($i=0;$i<12;$i++) $args[]='%'.$q.'%';
}
if(in_array($status,array_keys(case_status_labels()),true)){ $where[]='c.status=?'; $args[]=$status; }
if(in_array($type,['missing','rescue_waiting','rescued','deceased'],true)){ $where[]='c.type=?'; $args[]=$type; }
if(in_array($condition,array_keys(case_condition_labels()),true)){ $where[]='c.current_condition=?'; $args[]=$condition; }
if($from!==''){ $where[]='DATE(c.created_at)>=?'; $args[]=$from; }
if($to!==''){ $where[]='DATE(c.created_at)<=?'; $args[]=$to; }
if($ageMin!==''){ $where[]='c.age>=?'; $args[]=(int)$ageMin; }
if($ageMax!==''){ $where[]='c.age<=?'; $args[]=(int)$ageMax; }
if($vehicle!==''){ $where[]='c.vehicle_no LIKE ?'; $args[]='%'.$vehicle.'%'; }
if($location!==''){
    $where[]='(c.last_seen_address LIKE ? OR c.current_location LIKE ? OR c.where_found LIKE ? OR c.shifted_to LIKE ? OR c.from_location LIKE ?)';
    for($i=0;$i<5;$i++) $args[]='%'.$location.'%';
}
if($bs!==''){ $where[]='c.last_contacted_bs LIKE ?'; $args[]='%'.$bs.'%'; }
if($nationality==='FOREIGN'){ $where[]="c.type='missing' AND c.nationality_code IS NOT NULL AND c.nationality_code<>'NP'"; }
elseif($nationality==='UNKNOWN'){ $where[]="c.type='missing' AND (c.nationality_code IS NULL OR c.nationality_code='')"; }
elseif($nationality!=='' && isset($countries[$nationality])){ $where[]="c.type='missing' AND c.nationality_code=?"; $args[]=$nationality; }

$ws=implode(' AND ',$where);
$stmt=db()->prepare("SELECT COUNT(*) FROM cases c WHERE $ws");
$stmt->execute($args);
$total=(int)$stmt->fetchColumn();
$pages=max(1,(int)ceil($total/$per));
$page=min($page,$pages);
$off=($page-1)*$per;

$stmt=db()->prepare("SELECT c.*,
    (SELECT latitude FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) latitude,
    (SELECT longitude FROM rescue_locations r WHERE r.case_id=c.id ORDER BY r.id DESC LIMIT 1) longitude
    FROM cases c WHERE $ws
    ORDER BY FIELD(c.status,'open','under_review','searching','rescue_dispatched','located','found_alive','found_injured','rescued_safe','rescued_injured','shifted','found_deceased','close_requested','draft','closed'),c.updated_at DESC
    LIMIT $per OFFSET $off");
$stmt->execute($args);
$cases=$stmt->fetchAll();

$stats=db()->query("SELECT
    COUNT(*) total,
    SUM(type='missing') missing_total,
    SUM(type='missing' AND nationality_code='NP') nepali_missing_total,
    SUM(type='missing' AND (nationality_code IS NULL OR nationality_code='')) unknown_nationality_total,
    SUM(type='missing' AND nationality_code IS NOT NULL AND nationality_code<>'NP') foreign_missing_total,
    COUNT(DISTINCT CASE WHEN type='missing' AND nationality_code IS NOT NULL AND nationality_code<>'NP' THEN nationality_code END) foreign_country_count,
    SUM(type='rescue_waiting') rescue_total,
    SUM(type='rescued') rescued_total,
    SUM(type='deceased') dvi_total,
    SUM(status NOT IN ('closed','draft')) active_total,
    SUM(status='under_review') review_total,
    SUM(status IN ('searching','rescue_dispatched')) progress_total,
    SUM(status IN ('located','found_alive','found_injured','rescued_safe','rescued_injured','shifted')) found_rescued_total,
    SUM(current_condition IN ('injured','minor_injury','serious','critical','semi_conscious','unconscious','unable_communicate') OR status IN ('found_injured','rescued_injured')) medical_total,
    SUM(type='deceased' OR current_condition='deceased' OR status='found_deceased') deceased_total,
    SUM(status='closed') closed_total,
    SUM(status='draft') draft_total
    FROM cases WHERE deleted_at IS NULL")->fetch();

$statusCounts=[];
foreach(case_status_labels() as $key=>$labels) $statusCounts[$key]=0;
foreach(db()->query("SELECT status,COUNT(*) cnt FROM cases WHERE deleted_at IS NULL GROUP BY status")->fetchAll() as $row){
    $statusCounts[$row['status']]=(int)$row['cnt'];
}
$pending=(int)db()->query("SELECT COUNT(*) FROM close_approval_requests WHERE status='pending'")->fetchColumn();
$pendingFamily=(int)db()->query("SELECT COUNT(*) FROM family_match_requests WHERE status IN ('submitted','under_review','possible_match')")->fetchColumn();
$pendingIdentification=(int)db()->query("SELECT COUNT(*) FROM identification_approval_requests WHERE status='pending'")->fetchColumn();

$foreignCountries=db()->query("SELECT nationality_code,nationality_name,COUNT(*) cnt,SUM(status NOT IN ('closed','draft')) active_cnt FROM cases WHERE deleted_at IS NULL AND type='missing' AND nationality_code IS NOT NULL AND nationality_code<>'NP' GROUP BY nationality_code,nationality_name ORDER BY cnt DESC,nationality_name ASC LIMIT 10")->fetchAll();
$topForeignCountry=$foreignCountries[0]??null;

$viewLabels=[
    'all'=>'All Cases',
    'missing'=>'Missing Person',
    'foreign_missing'=>'Foreign Nationals Missing',
    'rescue'=>'Rescue Requests',
    'rescued'=>'Rescued Persons',
    'active'=>'Active Cases',
    'under_review'=>'Under Review',
    'in_progress'=>'Search / Rescue in Progress',
    'found_rescued'=>'Found / Rescued',
    'medical'=>'Injured / Critical',
    'deceased'=>'Deceased',
    'closed'=>'Closed Cases',
    'draft'=>'Drafts',
];

function dashboard_link(string $view): string {
    return base_url('admin/dashboard.php?'.http_build_query(['view'=>$view]).'#dashboard-filter-panel');
}
function latest_case_location(array $c): string {
    return (string)($c['current_location'] ?: ($c['where_found'] ?: ($c['last_seen_address'] ?: $c['from_location'])));
}

page_header('Dashboard',true);
?>

<section class="dashboard-head">
  <div>
    <p class="dashboard-eyebrow">CASE MANAGEMENT</p>
    <h1>Dashboard / ड्यासबोर्ड</h1>
    <p>National overview of rescue requests, missing persons, rescued persons, and DVI/deceased cases.</p>
  </div>
  <div class="dashboard-head-actions">
    <a class="btn btn-primary" href="<?=e(base_url('admin/new-report/missing'))?>">New Missing Person</a>
    <a class="btn btn-secondary" href="<?=e(base_url('admin/new-report/rescue'))?>">New Rescue Request</a>
    <a class="btn btn-secondary" href="<?=e(base_url('admin/new-report/rescued'))?>">Register Rescued Person</a>
    <?php if(can_manage_dvi($admin['role'])):?><a class="btn btn-secondary" href="<?=e(base_url('admin/new-report/deceased'))?>">Register DVI / Deceased</a><?php endif;?>
  </div>
</section>

<section class="dashboard-section">
  <div class="dashboard-section-heading">
    <div><h2>Case Summary</h2><p>Click a summary to filter the case list.</p></div>
    <div class="dashboard-user-line">Signed in: <strong><?=e($admin['name'])?></strong><?php if(!empty($admin['office_name'])):?> · <?=e($admin['office_name'])?><?php endif;?></div>
  </div>

  <div class="summary-grid summary-grid-main">
    <a class="summary-card summary-card-primary <?=$view==='all'?'is-selected':''?>" href="<?=e(dashboard_link('all'))?>"><span>Total Cases</span><strong><?=e((int)$stats['total'])?></strong><small>All registered cases</small></a>
    <a class="summary-card <?=$view==='missing'?'is-selected':''?>" href="<?=e(dashboard_link('missing'))?>"><span>Missing Person</span><strong><?=e((int)$stats['missing_total'])?></strong><small>Total missing-person reports</small></a>
    <a class="summary-card <?=$view==='rescue'?'is-selected':''?>" href="<?=e(dashboard_link('rescue'))?>"><span>Rescue Requests</span><strong><?=e((int)$stats['rescue_total'])?></strong><small>Waiting / requested rescue</small></a>
    <a class="summary-card <?=$view==='rescued'?'is-selected':''?>" href="<?=e(dashboard_link('rescued'))?>"><span>Rescued Persons</span><strong><?=e((int)$stats['rescued_total'])?></strong><small>Rescued even without prior request</small></a>
    <a class="summary-card <?=$view==='deceased'?'is-selected':''?>" href="<?=e(dashboard_link('deceased'))?>"><span>DVI / Deceased</span><strong><?=e((int)$stats['dvi_total'])?></strong><small>Recovered / unidentified deceased</small></a>
    <a class="summary-card <?=$view==='active'?'is-selected':''?>" href="<?=e(dashboard_link('active'))?>"><span>Active Cases</span><strong><?=e((int)$stats['active_total'])?></strong><small>Not closed or draft</small></a>
    <a class="summary-card <?=$view==='found_rescued'?'is-selected':''?>" href="<?=e(dashboard_link('found_rescued'))?>"><span>Found / Rescued</span><strong><?=e((int)$stats['found_rescued_total'])?></strong><small>Located, found or rescued</small></a>
    <a class="summary-card <?=$view==='closed'?'is-selected':''?>" href="<?=e(dashboard_link('closed'))?>"><span>Closed Cases</span><strong><?=e((int)$stats['closed_total'])?></strong><small>Completed and closed</small></a>
  </div>

  <div class="summary-grid summary-grid-secondary">
    <a class="summary-card summary-card-compact <?=$view==='under_review'?'is-selected':''?>" href="<?=e(dashboard_link('under_review'))?>"><span>Under Review</span><strong><?=e((int)$stats['review_total'])?></strong></a>
    <a class="summary-card summary-card-compact <?=$view==='in_progress'?'is-selected':''?>" href="<?=e(dashboard_link('in_progress'))?>"><span>Search / Rescue in Progress</span><strong><?=e((int)$stats['progress_total'])?></strong></a>
    <a class="summary-card summary-card-compact <?=$view==='medical'?'is-selected':''?>" href="<?=e(dashboard_link('medical'))?>"><span>Injured / Critical</span><strong><?=e((int)$stats['medical_total'])?></strong></a>
    <a class="summary-card summary-card-compact <?=$view==='deceased'?'is-selected':''?>" href="<?=e(dashboard_link('deceased'))?>"><span>Deceased</span><strong><?=e((int)$stats['deceased_total'])?></strong></a>
    <a class="summary-card summary-card-compact <?=$view==='draft'?'is-selected':''?>" href="<?=e(dashboard_link('draft'))?>"><span>Drafts</span><strong><?=e((int)$stats['draft_total'])?></strong></a>
    <?php if(can_close($admin['role'])):?><a class="summary-card summary-card-compact" href="<?=e(base_url('admin/approvals.php'))?>"><span>Closure Requests</span><strong><?=e($pending)?></strong></a><?php endif;?>
  </div>
</section>

<section class="dashboard-section nationality-overview-section">
  <div class="dashboard-section-heading">
    <div><h2>Nationality Overview</h2><p>Missing-person cases by nationality. Foreign-national counts exclude Nepal.</p></div>
  </div>
  <div class="nationality-summary-grid">
    <a class="nationality-summary-card" href="<?=e(base_url('admin/dashboard.php?'.http_build_query(['view'=>'missing','nationality'=>'NP'])))?>"><span>Nepali Missing Persons</span><strong><?=e((int)$stats['nepali_missing_total'])?></strong><small>Nepal nationality</small></a>
    <a class="nationality-summary-card nationality-summary-foreign" href="<?=e(base_url('admin/dashboard.php?'.http_build_query(['view'=>'foreign_missing','nationality'=>'FOREIGN'])))?>"><span>Foreign Nationals Missing</span><strong><?=e((int)$stats['foreign_missing_total'])?></strong><small>All non-Nepal nationalities</small></a>
    <a class="nationality-summary-card" href="<?=e(base_url('admin/dashboard.php?'.http_build_query(['view'=>'missing','nationality'=>'UNKNOWN'])))?>"><span>Nationality Not Recorded</span><strong><?=e((int)$stats['unknown_nationality_total'])?></strong><small>Older / unverified records</small></a>
    <div class="nationality-summary-card"><span>Foreign Countries Represented</span><strong><?=e((int)$stats['foreign_country_count'])?></strong><small><?=e($topForeignCountry ? 'Highest: '.$topForeignCountry['nationality_name'].' ('.$topForeignCountry['cnt'].')' : 'No foreign cases yet')?></small></div>
  </div>
  <div class="nationality-country-list">
    <div class="nationality-country-list-head"><strong>Foreign Nationality Ranking</strong><span>Click a country to filter the case register.</span></div>
    <?php if($foreignCountries):?>
      <div class="nationality-country-grid">
        <?php foreach($foreignCountries as $fc):?>
          <a href="<?=e(base_url('admin/dashboard.php?'.http_build_query(['view'=>'missing','nationality'=>$fc['nationality_code']])))?>" class="nationality-country-item">
            <span><?=e($fc['nationality_name'])?></span><strong><?=e((int)$fc['cnt'])?></strong><small><?=e((int)$fc['active_cnt'])?> active</small>
          </a>
        <?php endforeach;?>
      </div>
    <?php else:?><div class="nationality-empty">No foreign-national missing-person cases have been registered yet.</div><?php endif;?>
  </div>
</section>

<section class="dashboard-section">
  <div class="dashboard-section-heading">
    <div><h2>Current Workload</h2><p>Where active cases are in the operational process.</p></div>
  </div>
  <?php
    $flow=[
      'open'=>['Open / Reported','Newly registered'],
      'under_review'=>['Under Review','Being checked'],
      'searching'=>['Search in Progress','Search underway'],
      'rescue_dispatched'=>['Rescue Dispatched','Team dispatched'],
      'located'=>['Located','Location confirmed'],
      'shifted'=>['Shifted / Transferred','Moved to another location'],
      'close_requested'=>['Closure Requested','Awaiting approval'],
      'closed'=>['Closed','Case completed'],
    ];
  ?>
  <div class="workflow-status-grid">
    <?php foreach($flow as $key=>$meta):?>
      <a href="<?=e(base_url('admin/dashboard.php?'.http_build_query(['status'=>$key])))?>" class="workflow-status-cell">
        <span><?=e($meta[0])?></span><strong><?=e($statusCounts[$key]??0)?></strong><small><?=e($meta[1])?></small>
      </a>
    <?php endforeach;?>
  </div>
</section>

<section class="dashboard-section">
  <div class="dashboard-section-heading">
    <div><h2>Outcome Breakdown</h2><p>Confirmed outcomes and where completed cases currently stand.</p></div>
  </div>
  <?php
    $outcomes=[
      'found_alive'=>['Found Alive','Missing person found alive'],
      'found_injured'=>['Found Injured','Missing person found injured'],
      'rescued_safe'=>['Rescued Safe','Rescue completed safely'],
      'rescued_injured'=>['Rescued Injured','Rescued with injury'],
      'found_deceased'=>['Found Deceased','Deceased confirmed'],
      'shifted'=>['Shifted / Transferred','Moved to hospital or another location'],
      'closed'=>['Closed','Case formally completed'],
    ];
  ?>
  <div class="outcome-status-grid">
    <?php foreach($outcomes as $key=>$meta):?>
      <a href="<?=e(base_url('admin/dashboard.php?'.http_build_query(['status'=>$key])))?>" class="outcome-status-cell">
        <span><?=e($meta[0])?></span><strong><?=e($statusCounts[$key]??0)?></strong><small><?=e($meta[1])?></small>
      </a>
    <?php endforeach;?>
  </div>
</section>

<?php if($pending>0 && can_close($admin['role'])):?><div class="alert alert-warning dashboard-alert"><b><?=e($pending)?> closure request(s) need review.</b> <a href="<?=e(base_url('admin/approvals.php'))?>">Review closure requests</a></div><?php endif;?>

<section class="dashboard-section case-register-section">
  <div class="dashboard-section-heading dashboard-case-heading">
    <div>
      <p class="dashboard-eyebrow">CASE REGISTER</p>
      <h2><?=e($viewLabels[$view]??'All Cases')?></h2>
      <p><?=e($total)?> matching case<?=($total===1?'':'s')?>. Use filters to find a specific record.</p>
    </div>
    <button class="btn btn-secondary" type="button" id="exportSelected">Export Selected</button>
  </div>

  <div class="quick-filter-row" aria-label="Quick case filters">
    <?php foreach(['all','missing','foreign_missing','rescue','rescued','active','found_rescued','medical','deceased','closed'] as $vf):?>
      <a class="quick-filter <?=$view===$vf?'is-active':''?>" href="<?=e(dashboard_link($vf))?>"><?=e($viewLabels[$vf])?></a>
    <?php endforeach;?>
  </div>

  <form class="dashboard-filter-panel" id="dashboard-filter-panel" method="get" action="<?=e(base_url('admin/dashboard.php'))?>#dashboard-filter-panel">
    <input type="hidden" name="view" value="<?=e($view)?>">
    <div class="dashboard-filter-main">
      <label class="filter-search"><span>Search</span><input name="q" value="<?=e($q)?>" placeholder="Name, Case ID, phone or address"></label>
      <label><span>Case Type</span><select name="type"><option value="">All types</option><option value="missing" <?=$type==='missing'?'selected':''?>>Missing Person</option><option value="rescue_waiting" <?=$type==='rescue_waiting'?'selected':''?>>Rescue Request</option><option value="rescued" <?=$type==='rescued'?'selected':''?>>Rescued Person</option><option value="deceased" <?=$type==='deceased'?'selected':''?>>DVI / Deceased</option></select></label>
      <label><span>Nationality</span><select name="nationality"><option value="">All nationalities</option><option value="NP" <?=$nationality==='NP'?'selected':''?>>Nepal</option><option value="FOREIGN" <?=$nationality==='FOREIGN'?'selected':''?>>All Foreign Nationals</option><option value="UNKNOWN" <?=$nationality==='UNKNOWN'?'selected':''?>>Nationality Not Recorded</option><?php foreach($countries as $cc=>$cn): if($cc==='NP') continue; ?><option value="<?=e($cc)?>" <?=$nationality===$cc?'selected':''?>><?=e($cn)?></option><?php endforeach;?></select></label>
      <label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach(case_status_labels() as $s=>$lbl):?><option value="<?=e($s)?>" <?=$status===$s?'selected':''?>><?=e($lbl[0])?></option><?php endforeach;?></select></label>
      <label><span>Condition</span><select name="condition"><option value="">All conditions</option><?php foreach(case_condition_labels() as $cnd=>$lbl):?><option value="<?=e($cnd)?>" <?=$condition===$cnd?'selected':''?>><?=e($lbl[0])?></option><?php endforeach;?></select></label>
      <label><span>Location</span><input name="location" value="<?=e($location)?>" placeholder="Current / found / last seen"></label>
      <div class="dashboard-filter-buttons"><button class="btn btn-primary" type="submit">Apply Filters</button><a class="btn btn-ghost" href="<?=e(base_url('admin/dashboard.php'))?>#dashboard-filter-panel">Clear All</a></div>
    </div>
    <details class="dashboard-more-filters" <?=($from||$to||$ageMin||$ageMax||$vehicle||$bs||$nationality)?'open':''?>>
      <summary>More filters</summary>
      <div class="advanced-filter-grid dashboard-advanced-grid">
        <label>Created from<input type="date" name="from" value="<?=e($from)?>"></label>
        <label>Created to<input type="date" name="to" value="<?=e($to)?>"></label>
        <label>Minimum age<input type="number" name="age_min" value="<?=e($ageMin)?>" min="0"></label>
        <label>Maximum age<input type="number" name="age_max" value="<?=e($ageMax)?>" min="0"></label>
        <label>Vehicle number<input name="vehicle" value="<?=e($vehicle)?>"></label>
        <label>BS date<input name="bs" value="<?=e($bs)?>" placeholder="2083-05"></label>
      </div>
    </details>
  </form>

  <div class="dashboard-table-wrap table-wrap">
    <table class="dashboard-case-table">
      <thead><tr><th class="select-col"><input type="checkbox" id="selectAll" aria-label="Select all cases"></th><th>Case</th><th>Person / Request</th><th>Progress</th><th>Condition</th><th>Latest Location</th><th>Contact</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php foreach($cases as $c):?>
        <tr>
          <td><input class="case-check" type="checkbox" value="<?=$c['id']?>" aria-label="Select <?=e($c['case_code'])?>"></td>
          <td><strong class="case-code-text"><?=e($c['case_code'])?></strong><span class="table-subtext"><?=e(case_type_label($c['type']))?></span></td>
          <td><strong><?=e($c['name'])?></strong><?php if($c['age']!==null):?><span class="table-subtext">Age <?=e($c['age'])?></span><?php endif;?><?php if($c['type']==='missing'):?><span class="table-subtext nationality-table-text">Nationality: <?=e($c['nationality_name']?:'Not recorded')?></span><?php if(!empty($c['missing_person_context'])):?><span class="table-subtext"><?=e(missing_person_context_label($c['missing_person_context']))?></span><?php endif;?><?php endif;?></td>
          <td><span class="status status-<?=e($c['status'])?>"><?=e(case_status_label($c['status']))?></span></td>
          <td><?= $c['current_condition'] ? '<span class="condition condition-'.e($c['current_condition']).'">'.e(case_condition_label($c['current_condition'])).'</span>' : '<span class="table-muted">Not confirmed</span>' ?></td>
          <td><span class="location-text"><?=e(latest_case_location($c) ?: 'Not updated')?></span><?php if(!empty($c['shifted_to'])):?><span class="table-subtext">Shifted to: <?=e($c['shifted_to'])?></span><?php endif;?></td>
          <td><strong><?=e($c['family_contact_phone'])?></strong><?php if(!empty($c['family_contact_name'])):?><span class="table-subtext"><?=e($c['family_contact_name'])?></span><?php endif;?></td>
          <td><span class="updated-text"><?=e($c['updated_at'])?></span></td>
          <td><a class="btn btn-sm btn-secondary" href="<?=e(base_url('admin/case.php?id='.$c['id']))?>">Open Case</a></td>
        </tr>
      <?php endforeach;?>
      <?php if(!$cases):?><tr><td colspan="9" class="empty-case-cell"><strong>No matching cases.</strong><span>Change or clear the filters to see other records.</span></td></tr><?php endif;?>
      </tbody>
    </table>
  </div>

  <div class="dashboard-mobile-cases">
    <?php foreach($cases as $c):?>
      <article class="dashboard-mobile-case">
        <div class="dashboard-mobile-case-head"><div><span class="mobile-case-type"><?=e(case_type_label($c['type']))?></span><strong><?=e($c['case_code'])?></strong></div><span class="status status-<?=e($c['status'])?>"><?=e(case_status_label($c['status']))?></span></div>
        <div class="dashboard-mobile-person"><h3><?=e($c['name'])?></h3><p><?=e($c['family_contact_phone'])?></p><?php if($c['type']==='missing'):?><p class="mobile-nationality">Nationality: <?=e($c['nationality_name']?:'Not recorded')?></p><?php endif;?></div>
        <dl class="dashboard-mobile-details">
          <div><dt>Condition</dt><dd><?=e($c['current_condition']?case_condition_label($c['current_condition']):'Not confirmed')?></dd></div>
          <div><dt>Latest Location</dt><dd><?=e(latest_case_location($c) ?: 'Not updated')?></dd></div>
          <div><dt>Last Updated</dt><dd><?=e($c['updated_at'])?></dd></div>
        </dl>
        <a class="btn btn-secondary dashboard-mobile-open" href="<?=e(base_url('admin/case.php?id='.$c['id']))?>">Open Case</a>
      </article>
    <?php endforeach;?>
    <?php if(!$cases):?><div class="dashboard-empty-mobile"><strong>No matching cases.</strong><span>Change or clear the filters to see other records.</span></div><?php endif;?>
  </div>

  <?php if($pages>1):?><div class="pagination dashboard-pagination"><?php for($i=max(1,$page-3);$i<=min($pages,$page+3);$i++):?><a class="<?=$i===$page?'active':''?>" href="?<?=e(http_build_query(array_merge($_GET,['page'=>$i])))?>"><?=$i?></a><?php endfor;?></div><?php endif;?>
</section>

<section class="dashboard-section dashboard-admin-tools-section">
  <div class="dashboard-section-heading"><div><h2>Administration</h2><p>Management tools for authorized staff.</p></div></div>
  <div class="dashboard-admin-tools">
    <?php if(can_close($admin['role'])):?><a href="<?=e(base_url('admin/approvals.php'))?>"><strong>Closure Requests</strong><span><?=e($pending)?> pending</span></a><?php endif;?>
    <a href="<?=e(base_url('admin/family-requests'))?>"><strong>Family Match Requests</strong><span><?=e($pendingFamily)?> awaiting review</span></a>
    <?php if(can_approve_identification($admin['role'])):?><a href="<?=e(base_url('admin/dashboard.php?view=deceased'))?>"><strong>DVI Identification</strong><span><?=e($pendingIdentification)?> approval(s) pending</span></a><?php endif;?>
    <?php if(can_create_admin($admin['role'])):?><a href="<?=e(base_url('admin/admins.php'))?>"><strong>Admin Users</strong><span>Create and manage accounts</span></a><?php endif;?>
    <a href="<?=e(base_url('admin/import.php'))?>"><strong>Import Data</strong><span>Import case records</span></a>
    <a href="<?=e(base_url('admin/export.php'))?>"><strong>Export All Data</strong><span>Download complete data</span></a>
    <a href="<?=e(base_url('admin/audit.php'))?>"><strong>Activity Log</strong><span>Review staff actions</span></a>
  </div>
</section>

<script>
document.getElementById('selectAll')?.addEventListener('change',e=>document.querySelectorAll('.case-check').forEach(c=>c.checked=e.target.checked));
document.getElementById('exportSelected')?.addEventListener('click',()=>{
  const ids=[...document.querySelectorAll('.case-check:checked')].map(c=>c.value);
  if(!ids.length){alert('Select at least one case.');return;}
  location.href='<?=e(base_url('admin/export.php'))?>?ids='+encodeURIComponent(ids.join(','));
});
</script>
<?php page_footer(); ?>
