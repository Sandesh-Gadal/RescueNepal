<?php
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/auth.php';
$admin=require_admin();
if(!can_create_admin($admin['role'])){http_response_code(403);exit('Permission denied');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['admin_action']??'create';

    if($action==='toggle'){
        if($admin['role']!=='superadmin'){flash('danger','Only Superadmin can enable or disable admin accounts.');redirect(base_url('admin/admins.php'));}
        $targetId=(int)($_POST['admin_id']??0);
        if($targetId===(int)$admin['id']){flash('danger','You cannot disable your own account.');redirect(base_url('admin/admins.php'));}
        $stmt=db()->prepare('SELECT id,name,email,is_active,role FROM admins WHERE id=? LIMIT 1');$stmt->execute([$targetId]);$target=$stmt->fetch();
        if(!$target){flash('danger','Admin not found.');redirect(base_url('admin/admins.php'));}
        $new=(int)$target['is_active']===1?0:1;
        db()->prepare('UPDATE admins SET is_active=?,updated_at=NOW() WHERE id=?')->execute([$new,$targetId]);
        audit($admin['id'],$new?'enable_admin':'disable_admin',null,($new?'Enabled ':'Disabled ').$target['email']);
        flash('success',$target['name'].' '.($new?'enabled.':'disabled.'));
        redirect(base_url('admin/admins.php'));
    }

    $name=trim($_POST['name']??'');
    $email=strtolower(trim($_POST['email']??''));
    $phone=clean_phone($_POST['phone']??'');
    $role=$_POST['role']??'viewer';
    $postTitle=trim($_POST['post_title']??'');
    $officeName=trim($_POST['office_name']??'');
    $password=(string)($_POST['password']??'');
    $allowed=['viewer','operator','approver'];
    if($admin['role']==='superadmin')$allowed[]='superadmin';

    $errors=[];
    if($name==='')$errors[]='Name is required.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email.';
    if(!valid_phone($phone))$errors[]='Enter a valid phone number.';
    if($postTitle==='')$errors[]='Post / title is required.';
    if($officeName==='')$errors[]='Office name is required.';
    if(!in_array($role,$allowed,true))$errors[]='Invalid role.';
    if(strlen($password)<8)$errors[]='Temporary password must be at least 8 characters.';

    if($errors){flash('danger',implode(' ', $errors));}
    else{
        try{
            $stmt=db()->prepare('INSERT INTO admins(name,phone,email,post_title,office_name,password_hash,role,is_active) VALUES (?,?,?,?,?,?,?,1)');
            $stmt->execute([$name,$phone,$email,$postTitle,$officeName,password_hash($password,PASSWORD_DEFAULT),$role]);
            audit($admin['id'],'create_admin',null,'Created '.$email.' as '.$role.' for '.$officeName);
            flash('success','New admin created successfully: '.$name.' ('.$role.').');
        }catch(Throwable $e){
            $msg=str_contains(strtolower($e->getMessage()),'duplicate')?'That email already has an admin account.':'Could not create admin: '.$e->getMessage();
            flash('danger',$msg);
        }
    }
    redirect(base_url('admin/admins.php'));
}

$admins=db()->query('SELECT id,name,phone,email,post_title,office_name,role,is_active,created_at FROM admins ORDER BY is_active DESC,created_at DESC')->fetchAll();
page_header('Admin Management',true);
?>
<div class="admin-fullview-page">
  <div class="section-title admin-page-title admin-fullview-head">
    <div>
      <div class="card-kicker">ADMINISTRATION</div>
      <h1>Admin Management / एडमिन व्यवस्थापन</h1>
      <div class="muted">Create staff accounts, assign roles and manage access from one full-screen workspace.</div>
    </div>
    <div class="inline admin-head-actions">
      <a class="btn btn-secondary" href="<?=e(base_url('admin/operator_help.php'))?>">How to Fill Forms</a>
      <a class="btn btn-ghost" href="<?=e(base_url('admin/dashboard.php'))?>">Back to Cases</a>
    </div>
  </div>

  <div class="role-grid admin-role-grid">
    <div class="role-card"><b>Viewer</b><p>View cases and export data. Cannot edit or close.</p></div>
    <div class="role-card role-operator"><b>Operator</b><p>Create reports, update cases and request case closure.</p></div>
    <div class="role-card role-approver"><b>Approver</b><p>Review close requests, close cases and create admins.</p></div>
    <div class="role-card role-super"><b>Superadmin</b><p>Full control including admin accounts and account activation.</p></div>
  </div>

  <div class="card admin-create-workspace">
    <div class="admin-create-header">
      <div>
        <div class="card-kicker">NEW ACCOUNT</div>
        <h2>Create New Admin / नयाँ एडमिन</h2>
        <p class="muted">Create an account for an operator, viewer, approver or another superadmin. Use official staff details.</p>
      </div>
      
    </div>

    <form method="post" id="createAdminForm" class="admin-create-form">
      <?=csrf_field()?>
      <input type="hidden" name="admin_action" value="create">

      <section class="admin-form-section">
        <div class="admin-section-heading">
          <span>1</span>
          <div><b>Staff Identity / कर्मचारी विवरण</b><small>Who will use this account?</small></div>
        </div>
        <div class="admin-form-grid admin-form-grid-3">
          <label>Full Name / पूरा नाम *
            <input name="name" required placeholder="e.g. Hari Prasad Sharma" autocomplete="name">
          </label>
          <label>Phone / मोबाइल *
            <input name="phone" required inputmode="tel" placeholder="98XXXXXXXX" autocomplete="tel">
          </label>
          <label>Email / इमेल *
            <input name="email" type="email" required placeholder="name@office.gov.np" autocomplete="email">
          </label>
          <label>Post / Title / पद *
            <input name="post_title" required placeholder="Rescue Officer, Ward Secretary...">
          </label>
          <label>Office Name / कार्यालय *
            <input name="office_name" required placeholder="Municipality / DAO / Office name">
          </label>
          <label>Role / अधिकार *
            <select name="role" id="adminRole">
              <option value="viewer">Viewer — view + export</option>
              <option value="operator" selected>Operator — report + update + request close</option>
              <option value="approver">Approver — approve/close + create admins</option>
              <?php if($admin['role']==='superadmin'):?><option value="superadmin">Superadmin — full access</option><?php endif;?>
            </select>
          </label>
        </div>
        <div class="role-help admin-role-help" id="roleHelp">Operator can assist with forms, update case information and request closure, but cannot finally close a case.</div>
      </section>

      <section class="admin-form-section admin-security-section">
        <div class="admin-section-heading">
          <span>2</span>
          <div><b>Login Security / लगइन सुरक्षा</b><small>Give the new staff member a temporary password.</small></div>
        </div>
        <div class="admin-security-grid">
          <label>Temporary Password / अस्थायी पासवर्ड *
            <div class="password-row admin-password-row">
              <input type="password" name="password" id="adminPassword" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters">
              <button class="btn btn-ghost" type="button" id="showPassword">Show</button>
            </div>
          </label>
          <div class="admin-password-tools">
            <button class="btn btn-secondary" type="button" id="generatePassword">Generate Password</button>
            <div class="small muted">Share it securely and ask the user to change it after first login.</div>
          </div>
        </div>
      </section>

      <div class="admin-create-submitbar">
        <div class="admin-submit-note"><div><b>Ready to create</b><small>The account becomes active immediately after creation.</small></div></div>
        <button class="btn btn-primary btn-lg admin-create-submit" type="submit">Create Admin / एडमिन बनाउनुहोस्</button>
      </div>
    </form>
  </div>

  <div class="card admin-existing-workspace">
    <div class="section-title admin-existing-title" style="margin-top:0">
      <div><div class="card-kicker">ACCOUNTS</div><h2>Existing Admins / हालका एडमिन</h2><div class="muted"><?=count($admins)?> account(s)</div></div>
      <div class="admin-account-count"><?=count($admins)?> Total</div>
    </div>
    <div class="admin-list admin-list-fullview">
    <?php foreach($admins as $a):?>
      <div class="admin-person <?=$a['is_active']?'':'admin-disabled'?>">
        
        <div class="admin-person-main">
          <div class="inline"><b><?=e($a['name'])?></b><span class="role-pill role-<?=e($a['role'])?>"><?=e(ucfirst($a['role']))?></span><?php if(!$a['is_active']):?><span class="status">Disabled</span><?php endif;?></div>
          <div class="small muted"><?=e($a['post_title'])?> · <?=e($a['office_name'])?></div>
          <div class="small admin-contact-line">Phone: <?=e($a['phone'])?> &nbsp; Email: <?=e($a['email'])?></div>
        </div>
        <div class="admin-person-date"><small>Created</small><b><?=e(date('d M Y',strtotime($a['created_at'])))?></b></div>
        <?php if($admin['role']==='superadmin' && (int)$a['id']!==(int)$admin['id']):?>
        <form method="post" class="admin-toggle-form">
          <?=csrf_field()?>
          <input type="hidden" name="admin_action" value="toggle"><input type="hidden" name="admin_id" value="<?=$a['id']?>">
          <button class="btn btn-sm <?=$a['is_active']?'btn-danger':'btn-success'?>" data-confirm="<?=$a['is_active']?'Disable this admin account?':'Enable this admin account?'?>"><?=$a['is_active']?'Disable':'Enable'?></button>
        </form>
        <?php endif;?>
      </div>
    <?php endforeach;?>
    </div>
  </div>
</div>
<script>
(function(){
 const help={viewer:'Viewer can only view cases and export data. Use this for monitoring/audit staff.',operator:'Operator can assist with forms, update case information and request closure, but cannot finally close a case.',approver:'Approver can review and finally close cases and can create admin accounts.',superadmin:'Superadmin has full system control. Assign this role only when absolutely necessary.'};
 const role=document.getElementById('adminRole'),box=document.getElementById('roleHelp');
 role?.addEventListener('change',()=>box.textContent=help[role.value]||'');
 const p=document.getElementById('adminPassword'),show=document.getElementById('showPassword');
 show?.addEventListener('click',()=>{const visible=p.type==='text';p.type=visible?'password':'text';show.textContent=visible?'Show':'Hide';});
 document.getElementById('generatePassword')?.addEventListener('click',()=>{const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';let out='';const a=new Uint32Array(14);crypto.getRandomValues(a);a.forEach(n=>out+=chars[n%chars.length]);p.value=out;p.type='text';show.textContent='Hide';p.focus();p.select();});
})();
</script>
<?php page_footer(); ?>
