<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$root = __DIR__;
$lockFile = $root . '/storage/install.lock';
$configFile = $root . '/config.php';
$schemaFile = $root . '/database.sql';
$accessCodeFile = $root . '/deployment/SETUP_ACCESS_CODE.txt';
$tokenFile = $root . '/storage/setup.token';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function default_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'rescuenepal.info'));
    return ($https ? 'https' : 'http') . '://' . $host;
}
function setup_token(string $path): string {
    if (is_file($path)) {
        $t = trim((string)@file_get_contents($path));
        if (preg_match('/^[a-f0-9]{64}$/', $t)) return $t;
    }
    $t = bin2hex(random_bytes(32));
    @file_put_contents($path, $t, LOCK_EX);
    @chmod($path, 0600);
    return $t;
}
function expected_access_code(string $path): string {
    $raw = is_file($path) ? (string)file_get_contents($path) : '';
    if (preg_match('/RNSETUP-[A-F0-9]{12}/', $raw, $m)) return $m[0];
    return '';
}
function execute_schema(PDO $pdo, string $schemaPath): void {
    $sql = (string)file_get_contents($schemaPath);
    if ($sql === '') throw new RuntimeException('database.sql is empty or unreadable.');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
}
function config_php(array $data): string {
    $export = var_export($data, true);
    return "<?php\nreturn " . $export . ";\n";
}

$installed = is_file($lockFile) && is_file($configFile);
$errors = [];
$success = false;
$baseDefault = default_base_url();
$checks = [
    'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'ZipArchive (Excel)' => class_exists('ZipArchive'),
    'SimpleXML (Excel)' => extension_loaded('simplexml'),
    'GD (photo resize)' => extension_loaded('gd'),
    'Fileinfo (upload validation)' => extension_loaded('fileinfo'),
    'Mbstring' => extension_loaded('mbstring'),
    'OpenSSL' => extension_loaded('openssl'),
    'Application folder writable' => is_writable($root),
    'Storage folder writable' => is_dir($root.'/storage') && is_writable($root.'/storage'),
    'Uploads folder writable' => is_dir($root.'/uploads') && is_writable($root.'/uploads'),
];
$environmentOk = !in_array(false, $checks, true);

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string)($_POST['setup_token'] ?? '');
    $serverToken = setup_token($tokenFile);
    if ($postedToken === '' || !hash_equals($serverToken, $postedToken)) $errors[] = 'Setup security token expired. Reload the page and submit again.';

    $accessCode = trim((string)($_POST['access_code'] ?? ''));
    $expectedCode = expected_access_code($accessCodeFile);
    if ($expectedCode === '' || !hash_equals($expectedCode, $accessCode)) $errors[] = 'Invalid setup access code. Use the code from deployment/SETUP_ACCESS_CODE.txt.';

    if (!$environmentOk) $errors[] = 'One or more required PHP extensions/folders are not ready. Fix the failed checks shown below first.';

    $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) $errors[] = 'Enter a valid Base URL, for example https://rescuenepal.info.';
    elseif (!str_starts_with($baseUrl, 'https://') && !preg_match('#^http://(?:localhost|127\.0\.0\.1)(?::\d+)?$#', $baseUrl)) $errors[] = 'Production Base URL must use HTTPS.';

    $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPass === '') $errors[] = 'All MySQL connection fields are required.';

    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminPhone = trim((string)($_POST['admin_phone'] ?? ''));
    $adminEmail = strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $postTitle = trim((string)($_POST['post_title'] ?? ''));
    $officeName = trim((string)($_POST['office_name'] ?? ''));
    $adminPass = (string)($_POST['admin_password'] ?? '');
    $adminPass2 = (string)($_POST['admin_password_confirm'] ?? '');
    if ($adminName === '' || $adminPhone === '' || $adminEmail === '' || $postTitle === '' || $officeName === '') $errors[] = 'Complete all Superadmin profile fields.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid Superadmin email address.';
    if (strlen($adminPass) < 10) $errors[] = 'Superadmin password must be at least 10 characters.';
    if ($adminPass !== $adminPass2) $errors[] = 'Superadmin passwords do not match.';

    if (!$errors) {
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            execute_schema($pdo, $schemaFile);

            $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            $caseCount = (int)$pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn();
            if ($adminCount > 0 || $caseCount > 0) {
                throw new RuntimeException('This database is not empty. Fresh setup requires an empty database so existing production data is never overwritten accidentally.');
            }

            // Build config manually so __DIR__ remains executable PHP instead of a literal string.
            $cfg = "<?php\nreturn [\n"
                . "    'app_name' => 'Rescue Nepal - Missing Persons & Rescue Registry',\n"
                . "    'base_url' => " . var_export($baseUrl, true) . ",\n"
                . "    'timezone' => 'Asia/Kathmandu',\n"
                . "    'db' => [\n"
                . "        'host' => " . var_export($dbHost, true) . ",\n"
                . "        'name' => " . var_export($dbName, true) . ",\n"
                . "        'user' => " . var_export($dbUser, true) . ",\n"
                . "        'pass' => " . var_export($dbPass, true) . ",\n"
                . "        'charset' => 'utf8mb4',\n"
                . "    ],\n"
                . "    'security' => [\n"
                . "        'session_name' => 'rescuenepal_registry',\n"
                . "        'max_public_submissions_per_hour' => 20,\n"
                . "    ],\n"
                . "    'sms' => [\n"
                . "        'otp_webhook_url' => '',\n"
                . "        'bearer_token' => '',\n"
                . "    ],\n"
                . "    'uploads' => [\n"
                . "        'max_photo_bytes' => 8 * 1024 * 1024,\n"
                . "        'photo_dir' => __DIR__ . '/uploads/photos',\n"
                . "        'thumb_dir' => __DIR__ . '/uploads/thumbs',\n"
                . "        'import_dir' => __DIR__ . '/uploads/imports',\n"
                . "        'evidence_dir' => __DIR__ . '/uploads/evidence',\n"
                . "        'family_dir' => __DIR__ . '/uploads/family',\n"
                . "    ],\n"
                . "];\n";

            // Prepare the configuration file before creating the admin so a
            // filesystem failure cannot leave the database half-installed.
            $tmp = $configFile . '.tmp-' . bin2hex(random_bytes(4));
            if (file_put_contents($tmp, $cfg, LOCK_EX) === false) throw new RuntimeException('Could not write temporary config file. Check document-root permissions.');
            @chmod($tmp, 0640);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO admins(name,phone,email,post_title,office_name,password_hash,role,is_active) VALUES (?,?,?,?,?,?,\'superadmin\',1)');
                $stmt->execute([$adminName, $adminPhone, $adminEmail, $postTitle, $officeName, password_hash($adminPass, PASSWORD_DEFAULT)]);

                if (!@rename($tmp, $configFile)) throw new RuntimeException('Could not activate config.php. Check file permissions.');
                @chmod($configFile, 0640);

                foreach ([$root.'/uploads/photos',$root.'/uploads/thumbs',$root.'/uploads/imports',$root.'/uploads/evidence',$root.'/uploads/family',$root.'/storage/sessions',$root.'/storage/security'] as $dir) {
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                }
                @chmod($root.'/storage/sessions', 0700);
                @chmod($root.'/storage/security', 0700);

                $lock = "Installed: " . date('c') . "\nBase URL: {$baseUrl}\nInstall ID: " . bin2hex(random_bytes(12)) . "\n";
                if (file_put_contents($lockFile, $lock, LOCK_EX) === false) throw new RuntimeException('Could not create installation lock.');
                @chmod($lockFile, 0600);

                $pdo->commit();
            } catch (Throwable $inner) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                @unlink($tmp);
                @unlink($configFile);
                @unlink($lockFile);
                throw $inner;
            }
            @unlink($tokenFile);
            $success = true;
            $installed = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$token = !$installed ? setup_token($tokenFile) : '';
$baseVal = (string)($_POST['base_url'] ?? $baseDefault);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rescue Nepal Setup</title>
<style>
:root{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#172033;background:#f4f7fb}*{box-sizing:border-box}body{margin:0}.wrap{max-width:1040px;margin:0 auto;padding:28px 18px 60px}.head{margin-bottom:22px}.head h1{font-size:30px;margin:0 0 8px}.head p{margin:0;color:#596579;line-height:1.6}.card{background:white;border:1px solid #dce3ec;border-radius:14px;padding:22px;margin:16px 0;box-shadow:0 4px 16px rgba(20,33,61,.05)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:700;font-size:14px}input{width:100%;margin-top:7px;padding:12px 13px;border:1px solid #c9d2df;border-radius:9px;font:inherit;background:#fff}input:focus{outline:2px solid #bfd7ff;border-color:#4c83c9}.hint{font-size:13px;color:#68758a;margin-top:6px}.checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px}.check{padding:9px 0;border-bottom:1px solid #edf1f6;display:flex;justify-content:space-between;gap:10px}.ok{color:#087443;font-weight:800}.bad{color:#b42318;font-weight:800}.alert{border-radius:10px;padding:13px 15px;margin:12px 0;line-height:1.5}.alert-error{background:#fff0f0;border:1px solid #f0b7b7;color:#8d1c1c}.alert-success{background:#ecfdf3;border:1px solid #a7e0bc;color:#176439}.btn{display:inline-flex;justify-content:center;align-items:center;min-height:46px;padding:0 20px;border:0;border-radius:9px;background:#174f86;color:#fff;font-weight:800;font-size:15px;cursor:pointer;text-decoration:none}.btn:hover{background:#123e6a}.note{background:#f8fafc;border-left:4px solid #174f86;padding:14px 16px;line-height:1.55}.small{font-size:13px;color:#68758a}@media(max-width:700px){.wrap{padding:18px 12px 40px}.grid,.checks{grid-template-columns:1fr}.head h1{font-size:25px}.card{padding:17px}.btn{width:100%}}
</style>
</head>
<body><main class="wrap">
<div class="head"><h1>Rescue Nepal — Fresh Server Setup</h1><p>Creates the latest database schema, writes the production configuration, and creates the first Superadmin. No sample cases are inserted.</p></div>
<?php if ($installed): ?>
  <div class="card">
    <?php if($success): ?><div class="alert alert-success"><strong>Installation completed successfully.</strong><br>The database contains zero seeded cases and the first Superadmin is ready.</div><?php endif; ?>
    <h2>Application is installed</h2>
    <p>Setup is now locked. Continue to the administration login.</p>
    <a class="btn" href="<?=h(rtrim((string)($_POST['base_url'] ?? $baseDefault),'/').'/admin/login')?>">Open Admin Login</a>
    <p class="small">For additional security, you may delete <code>setup.php</code> and <code>deployment/SETUP_ACCESS_CODE.txt</code> after confirming the site works.</p>
  </div>
<?php else: ?>
  <?php foreach($errors as $error): ?><div class="alert alert-error"><?=h($error)?></div><?php endforeach; ?>
  <div class="card"><h2>1. Server readiness</h2><div class="checks">
    <?php foreach($checks as $label=>$state): ?><div class="check"><span><?=h($label)?></span><span class="<?=$state?'ok':'bad'?>"><?=$state?'READY':'FIX REQUIRED'?></span></div><?php endforeach; ?>
  </div><p class="small">If an extension is missing, enable it from cPanel PHP Selector / Select PHP Version before installing.</p></div>

  <form method="post" autocomplete="off">
    <input type="hidden" name="setup_token" value="<?=h($token)?>">
    <div class="card"><h2>2. Installation access</h2><div class="grid">
      <label class="full">Setup Access Code<input name="access_code" required value="<?=h((string)($_POST['access_code']??''))?>"><span class="hint">Open <code>deployment/SETUP_ACCESS_CODE.txt</code> from the downloaded package or cPanel File Manager.</span></label>
      <label class="full">Website Base URL<input name="base_url" required value="<?=h($baseVal)?>"><span class="hint">For this deployment use <strong>https://rescuenepal.info</strong> with no trailing slash.</span></label>
    </div></div>

    <div class="card"><h2>3. MySQL database</h2><div class="note">Create an empty MySQL database and user in cPanel first, assign the user to the database, and grant <strong>ALL PRIVILEGES</strong>.</div><div class="grid" style="margin-top:16px">
      <label>Database Host<input name="db_host" required value="<?=h((string)($_POST['db_host']??'localhost'))?>"></label>
      <label>Database Name<input name="db_name" required value="<?=h((string)($_POST['db_name']??''))?>"></label>
      <label>Database User<input name="db_user" required value="<?=h((string)($_POST['db_user']??''))?>"></label>
      <label>Database Password<input type="password" name="db_pass" required value=""></label>
    </div></div>

    <div class="card"><h2>4. First Superadmin</h2><div class="grid">
      <label>Name<input name="admin_name" required value="<?=h((string)($_POST['admin_name']??''))?>"></label>
      <label>Mobile Number<input name="admin_phone" required value="<?=h((string)($_POST['admin_phone']??''))?>"></label>
      <label>Email<input type="email" name="admin_email" required value="<?=h((string)($_POST['admin_email']??''))?>"></label>
      <label>Post / Title<input name="post_title" required value="<?=h((string)($_POST['post_title']??'Administrator'))?>"></label>
      <label class="full">Office / Organization<input name="office_name" required value="<?=h((string)($_POST['office_name']??'Rescue Nepal'))?>"></label>
      <label>New Password<input type="password" name="admin_password" required minlength="10"></label>
      <label>Confirm Password<input type="password" name="admin_password_confirm" required minlength="10"></label>
    </div></div>

    <div class="card"><h2>5. Install</h2><p>The installer will create the latest tables and one Superadmin. It will <strong>not</strong> create any missing-person or rescue sample records.</p><button class="btn" type="submit" <?=$environmentOk?'':'disabled'?>>Create Rescue Nepal Application</button></div>
  </form>
<?php endif; ?>
</main></body></html>
