<?php
require_once __DIR__.'/../includes/layout.php'; require_once __DIR__.'/../includes/auth.php'; $admin=require_admin();$rows=db()->query('SELECT l.*,a.name admin_name,c.case_code FROM audit_log l LEFT JOIN admins a ON a.id=l.admin_id LEFT JOIN cases c ON c.id=l.case_id ORDER BY l.id DESC LIMIT 500')->fetchAll();page_header('Audit Log',true);
?>
<div class="section-title"><h1>Audit / Activity Log</h1><a class="btn btn-ghost" href="<?=e(base_url('admin/dashboard.php'))?>">Back</a></div><div class="table-wrap"><table><thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Case</th><th>Notes</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['timestamp'])?></td><td><?=e($r['admin_name']??'System/Public')?></td><td><?=e($r['action'])?></td><td><?=e($r['case_code'])?></td><td><?=e($r['notes'])?></td></tr><?php endforeach;?></tbody></table></div>
<?php page_footer(); ?>
