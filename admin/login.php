<?php
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/auth.php';
start_admin_session();
if(current_admin()) redirect(base_url('admin/dashboard.php'));
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(login_admin(trim($_POST['login']??''),$_POST['password']??'')) redirect(base_url('admin/dashboard.php'));
    $error=login_is_rate_limited() ? 'Too many failed login attempts. Please try again after 15 minutes.' : 'Invalid email/phone or password.';
}
page_header('Admin Login');
?>
<div class="narrow" style="margin:auto">
  <div class="card">
    <h1>Admin / Operator Login</h1>
    <p class="muted">Authorized staff only.</p>
    <?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
    <form method="post">
      <?=csrf_field()?>
      <label>Email or Phone / इमेल वा फोन<input name="login" autocomplete="username" required></label>
      <label>Password / पासवर्ड<input name="password" type="password" autocomplete="current-password" required></label>
      <button class="btn btn-primary btn-block">Login / लगइन</button>
    </form>
  </div>
</div>
<?php page_footer(); ?>
