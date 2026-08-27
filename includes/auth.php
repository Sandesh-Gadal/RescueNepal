<?php
require_once __DIR__ . '/helpers.php';

function start_admin_session(): void { start_app_session(); }

function admin_session_expired(): bool {
    $now=time();
    $last=(int)($_SESSION['_admin_last_activity'] ?? 0);
    $login=(int)($_SESSION['_admin_login_time'] ?? 0);
    $idleLimit=30*60;      // 30 minutes idle
    $absoluteLimit=12*60*60; // 12 hours absolute
    if ($last && ($now-$last)>$idleLimit) return true;
    if ($login && ($now-$login)>$absoluteLimit) return true;
    $ua=(string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $saved=(string)($_SESSION['_admin_ua'] ?? '');
    if ($saved!=='' && !hash_equals($saved,hash('sha256',$ua))) return true;
    return false;
}

function current_admin(): ?array {
    start_admin_session();
    if (empty($_SESSION['admin'])) return null;
    if (admin_session_expired()) {
        $_SESSION=[];
        if (session_status()===PHP_SESSION_ACTIVE) @session_regenerate_id(true);
        return null;
    }

    $a=$_SESSION['admin'];
    // Re-check the account so disabled users lose access immediately.
    $stmt=db()->prepare('SELECT id,name,email,phone,role,post_title,office_name,is_active FROM admins WHERE id=? LIMIT 1');
    $stmt->execute([(int)$a['id']]);
    $fresh=$stmt->fetch();
    if (!$fresh || !(int)$fresh['is_active']) {
        $_SESSION=[];
        return null;
    }
    $_SESSION['admin']=[
        'id'=>(int)$fresh['id'],'name'=>$fresh['name'],'email'=>$fresh['email'],'phone'=>$fresh['phone'],
        'role'=>$fresh['role'],'post_title'=>$fresh['post_title'],'office_name'=>$fresh['office_name']
    ];
    $_SESSION['_admin_last_activity']=time();
    return $_SESSION['admin'];
}

function require_admin(?array $roles=null): array {
    $a=current_admin();
    if(!$a) redirect(base_url('admin/login.php'));
    if($roles && !in_array($a['role'],$roles,true)){
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
    return $a;
}

function login_throttle_file(): string {
    $dir=__DIR__.'/../storage/security';
    if(!is_dir($dir)) @mkdir($dir,0700,true);
    $ip=(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return $dir.'/login_'.hash('sha256',$ip).'.json';
}

function login_throttle_state(): array {
    $file=login_throttle_file();
    $state=['attempts'=>[],'blocked_until'=>0];
    if(is_file($file)){
        $raw=@file_get_contents($file);
        $d=json_decode((string)$raw,true);
        if(is_array($d)) $state=array_merge($state,$d);
    }
    $now=time();
    $state['attempts']=array_values(array_filter((array)$state['attempts'],fn($t)=>(int)$t>$now-900));
    if((int)$state['blocked_until']<$now) $state['blocked_until']=0;
    return $state;
}

function login_is_rate_limited(): bool {
    $s=login_throttle_state();
    return (int)$s['blocked_until']>time();
}

function record_login_failure(): void {
    $file=login_throttle_file();
    $s=login_throttle_state();
    $s['attempts'][]=time();
    if(count($s['attempts'])>=5){
        $s['blocked_until']=time()+900;
        $s['attempts']=[];
    }
    @file_put_contents($file,json_encode($s),LOCK_EX);
}

function clear_login_failures(): void {
    $file=login_throttle_file();
    if(is_file($file)) @unlink($file);
}

function login_admin(string $login,string $password): bool {
    if(login_is_rate_limited()) return false;
    $stmt=db()->prepare('SELECT * FROM admins WHERE (email=? OR phone=?) AND is_active=1 LIMIT 1');
    $stmt->execute([$login,$login]);
    $a=$stmt->fetch();
    if(!$a || !password_verify($password,$a['password_hash'])){
        record_login_failure();
        return false;
    }
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin']=[
        'id'=>(int)$a['id'],'name'=>$a['name'],'email'=>$a['email'],'phone'=>$a['phone'],'role'=>$a['role'],
        'post_title'=>$a['post_title'],'office_name'=>$a['office_name']
    ];
    $_SESSION['_admin_login_time']=time();
    $_SESSION['_admin_last_activity']=time();
    $_SESSION['_admin_ua']=hash('sha256',(string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    clear_login_failures();
    audit((int)$a['id'],'login',null,'Admin logged in');
    return true;
}

function logout_admin(): void {
    $a=current_admin();
    if($a) audit($a['id'],'logout');
    $_SESSION=[];
    if(ini_get('session.use_cookies')){
        $p=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);
    }
    if(session_status()===PHP_SESSION_ACTIVE) session_destroy();
}
