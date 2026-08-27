<?php
require_once __DIR__ . '/db.php';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/**
 * Convert legacy internal PHP paths to clean public/admin routes.
 * Existing code can keep calling base_url('admin/case.php?id=12') while the
 * browser sees /admin/cases/12.
 */
function clean_route_path(string $path=''): string {
    if ($path === '') return '';
    $parts = parse_url($path);
    $p = ltrim((string)($parts['path'] ?? ''), '/');
    $query = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $query);

    $route = $p;
    switch ($p) {
        case 'index.php': $route=''; break;
        case 'report.php':
            $type=(string)($query['type'] ?? '');
            $staff=!empty($query['staff']);
            unset($query['type'],$query['staff']);
            if ($staff && in_array($type,['missing','rescue'],true)) $route='admin/new-report/'.$type;
            elseif ($type==='missing') $route='missing-person';
            elseif ($type==='rescue') $route='rescue-request';
            else $route='';
            break;
        case 'submit.php':
            $staff=!empty($query['staff']); unset($query['staff']);
            $route=$staff?'admin/submit':'submit';
            break;
        case 'track.php':
            if (!empty($query['admin_preview']) && !empty($query['id'])) {
                $route='admin/cases/'.rawurlencode((string)$query['id']).'/public-preview';
                unset($query['admin_preview'],$query['id']);
            } elseif (!empty($query['code'])) {
                $route='track/'.rawurlencode((string)$query['code']); unset($query['code']);
            } else $route='track';
            break;
        case 'success.php':
            $staff=!empty($query['staff']); $token=(string)($query['token'] ?? '');
            unset($query['staff'],$query['token']);
            $route=($staff?'admin/confirmation/':'confirmation/').rawurlencode($token);
            break;
        case 'receipt.php':
            $staff=!empty($query['staff']); $token=(string)($query['token'] ?? '');
            unset($query['staff'],$query['token']);
            $route=($staff?'admin/receipt/':'receipt/').rawurlencode($token);
            break;
        case 'rescued.php':
            $staff=!empty($query['staff']); unset($query['staff']);
            $route=$staff?'admin/new-report/rescued':'rescued-person'; break;
        case 'submit_rescued.php':
            $staff=!empty($query['staff']); unset($query['staff']);
            $route=$staff?'admin/submit-rescued':'submit-rescued'; break;
        case 'public_search.php': $route='find'; break;
        case 'family_match.php':
            if (!empty($query['case'])) { $route='family-match/'.rawurlencode((string)$query['case']); unset($query['case']); } else $route='family-match';
            break;
        case 'otp.php': $route='otp'; break;
        case 'admin/deceased.php': $route='admin/new-report/deceased'; break;
        case 'admin/reconciliation.php':
            $id=(string)($query['id'] ?? ''); unset($query['id']); $route='admin/reconciliation/'.rawurlencode($id); break;
        case 'admin/family_requests.php': $route='admin/family-requests'; break;
        case 'admin/handover.php':
            $id=(string)($query['id'] ?? ''); unset($query['id']); $route='admin/cases/'.rawurlencode($id).'/handover'; break;
        case 'admin/custody.php':
            $id=(string)($query['id'] ?? ''); unset($query['id']); $route='admin/cases/'.rawurlencode($id).'/custody'; break;
        case 'admin/login.php': $route='admin/login'; break;
        case 'admin/logout.php': $route='admin/logout'; break;
        case 'admin/dashboard.php': $route='admin/cases'; break;
        case 'admin/operator_help.php': $route='admin/new-report'; break;
        case 'admin/case.php':
            $id=(string)($query['id'] ?? ''); unset($query['id']);
            $route='admin/cases/'.rawurlencode($id); break;
        case 'admin/report.php':
            $id=(string)($query['id'] ?? ''); unset($query['id']);
            $route='admin/cases/'.rawurlencode($id).'/report'; break;
        case 'admin/idcard.php':
            $id=(string)($query['id'] ?? ''); unset($query['id']);
            $route='admin/cases/'.rawurlencode($id).'/id-card'; break;
        case 'admin/approvals.php': $route='admin/closure-requests'; break;
        case 'admin/admins.php': $route='admin/users'; break;
        case 'admin/import.php': $route='admin/import'; break;
        case 'admin/export.php': $route='admin/export'; break;
        case 'admin/audit.php': $route='admin/activity'; break;
        case 'admin/password.php': $route='admin/password'; break;
    }
    $suffix = $query ? ('?'.http_build_query($query)) : '';
    return trim($route,'/').$suffix;
}

function base_url(string $path=''): string {
    $base = rtrim(app_config()['base_url'] ?? '', '/');
    $clean = clean_route_path($path);
    return $base . ($clean !== '' ? '/' . ltrim($clean, '/') : '');
}
function redirect(string $url, int $status=302): never { header('Location: ' . $url, true, $status); exit; }
function now_sql(): string { return date('Y-m-d H:i:s'); }
function random_token(int $bytes=24): string { return bin2hex(random_bytes($bytes)); }

function is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function send_security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; frame-src 'self' https://www.google.com; object-src 'none'; img-src 'self' data: https://*.tile.openstreetmap.org https://tile.openstreetmap.org; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com; connect-src 'self' https://nominatim.openstreetmap.org; upgrade-insecure-requests");
}

/** Redirect visible legacy *.php GET URLs to their canonical clean route. */
function enforce_canonical_route(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)) return;
    $requestPath=(string)(parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH) ?? '');
    if (!preg_match('/\.php$/i',$requestPath)) return;

    $basePath=(string)(parse_url(app_config()['base_url'] ?? '',PHP_URL_PATH) ?? '');
    $basePath=rtrim($basePath,'/');
    $relative=$requestPath;
    if ($basePath!=='' && str_starts_with($requestPath,$basePath.'/')) $relative=substr($requestPath,strlen($basePath)+1);
    else $relative=ltrim($requestPath,'/');

    // Fresh-install setup has its own canonical /setup route.
    if ($relative==='setup.php') return;

    $legacy=$relative;
    if (!empty($_SERVER['QUERY_STRING'])) $legacy.='?'.$_SERVER['QUERY_STRING'];
    $target=base_url($legacy);
    $currentScheme=is_https_request()?'https':'http';
    $current=$currentScheme.'://'.($_SERVER['HTTP_HOST'] ?? '').($_SERVER['REQUEST_URI'] ?? '');
    if ($target!==$current) redirect($target,301);
}

/**
 * Start one consistent application session for public forms and admin.
 * Shared hosting sometimes has an unwritable global session path, so the app
 * uses its private storage/sessions directory when available.
 */
function start_app_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $cfg = app_config();
    $sessionName = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cfg['security']['session_name'] ?? 'hexa_rescue'));
    if ($sessionName === '') $sessionName = 'hexa_rescue';
    session_name($sessionName);

    $sessionDir = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionDir)) @mkdir($sessionDir, 0700, true);
    if (is_dir($sessionDir) && is_writable($sessionDir)) session_save_path($sessionDir);

    $cookiePath = parse_url(base_url(), PHP_URL_PATH);
    $cookiePath = is_string($cookiePath) && $cookiePath !== '' ? rtrim($cookiePath, '/') . '/' : '/';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_trans_sid', '0');

    if (!session_start()) throw new RuntimeException('PHP session could not be started. Check storage/sessions permissions.');
}

function csrf_token(): string {
    start_app_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = random_token(24);
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    start_app_session();
    $sessionToken = (string)($_SESSION['csrf'] ?? '');
    $postedToken = (string)($_POST['csrf'] ?? '');
    if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $_SESSION['csrf'] = random_token(24);
        http_response_code(419);
        exit('Invalid or expired security token. Please reload this page once and submit again.');
    }
}
function flash(string $type, string $message): void {
    start_app_session();
    $_SESSION['flash'][] = [$type, $message];
}
function pull_flashes(): array {
    start_app_session();
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}

send_security_headers();
enforce_canonical_route();

function clean_phone(string $phone): string {
    $phone = trim($phone);
    $phone = preg_replace('/[\s\-()]/', '', $phone);
    if (str_starts_with($phone, '00977')) $phone = '+977' . substr($phone, 5);
    if (preg_match('/^98\d{8}$/', $phone)) $phone = '+977' . $phone;
    return $phone;
}
function valid_phone(string $phone): bool {
    $p = clean_phone($phone);
    return (bool)preg_match('/^\+[1-9]\d{7,14}$/', $p);
}
function valid_latlng($lat,$lng): bool {
    return is_numeric($lat) && is_numeric($lng) && (float)$lat >= -90 && (float)$lat <= 90 && (float)$lng >= -180 && (float)$lng <= 180;
}
function public_rate_limit(): void {
    $limit = (int)(app_config()['security']['max_public_submissions_per_hour'] ?? 20);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = db()->prepare('SELECT COUNT(*) FROM submission_log WHERE ip_address=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $stmt->execute([$ip]);
    if ((int)$stmt->fetchColumn() >= $limit) { http_response_code(429); exit('Too many submissions. Please try again later.'); }
}
function log_public_submission(): void {
    $stmt = db()->prepare('INSERT INTO submission_log (ip_address,user_agent,created_at) VALUES (?,?,NOW())');
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,250)]);
}
function audit(?int $adminId, string $action, ?int $caseId=null, string $notes=''): void {
    $stmt = db()->prepare('INSERT INTO audit_log(admin_id,action,case_id,timestamp,notes) VALUES (?,?,?,NOW(),?)');
    $stmt->execute([$adminId,$action,$caseId,$notes]);
}
function case_code(?int $id=null): string {
    // Human-friendly, non-sequential public Case ID.
    // Alphabet excludes visually ambiguous 0/O and 1/I characters.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $raw = '';
        for ($i = 0; $i < 12; $i++) $raw .= $alphabet[random_int(0, $max)];
        $code = 'RN-' . substr($raw,0,4) . '-' . substr($raw,4,4) . '-' . substr($raw,8,4);

        // The UNIQUE index on cases.case_code is the final protection; this
        // pre-check simply avoids a collision before the UPDATE statement.
        try {
            $stmt = db()->prepare('SELECT 1 FROM cases WHERE case_code=? LIMIT 1');
            $stmt->execute([$code]);
            if (!$stmt->fetchColumn()) return $code;
        } catch (Throwable $e) {
            // During very early installation/migration the cases table may not
            // yet be available. The random space is still extremely large.
            return $code;
        }
    }

    throw new RuntimeException('Could not generate a unique Case ID. Please retry.');
}

function save_uploaded_photo(array $file): ?array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Photo upload failed.');
    $max = app_config()['uploads']['max_photo_bytes'];
    if (($file['size'] ?? 0) > $max) throw new RuntimeException('Photo is too large (max 8MB).');
    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Photo must be JPG, PNG or WebP. HEIC should be converted by the phone/browser first.');
    $ext = $allowed[$mime]; $name = date('YmdHis') . '_' . random_token(6) . '.' . $ext;
    $photoDir = app_config()['uploads']['photo_dir']; $thumbDir = app_config()['uploads']['thumb_dir'];
    if (!is_dir($photoDir)) mkdir($photoDir,0755,true); if (!is_dir($thumbDir)) mkdir($thumbDir,0755,true);
    $dest = $photoDir . '/' . $name; $thumb = $thumbDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'],$dest)) throw new RuntimeException('Could not store photo.');
    make_resized_image($dest,$dest,1400,1400,82); make_resized_image($dest,$thumb,360,360,78);
    return ['photo'=>'uploads/photos/'.$name,'thumb'=>'uploads/thumbs/'.$name];
}
function save_restricted_photo(array $file, string $kind='evidence'): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Photo upload failed.');
    $cfg=app_config();$max=$cfg['uploads']['max_photo_bytes']??(8*1024*1024);
    if (($file['size'] ?? 0) > $max) throw new RuntimeException('Photo is too large (max 8MB).');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime]))throw new RuntimeException('Photo must be JPG, PNG or WebP.');
    if(!in_array($kind,['evidence','family'],true))$kind='evidence';
    $dir=$cfg['uploads'][$kind.'_dir']??dirname(__DIR__).'/uploads/'.$kind;if(!is_dir($dir))mkdir($dir,0750,true);
    $name=date('YmdHis').'_'.random_token(10).'.'.$allowed[$mime];$dest=$dir.'/'.$name;if(!move_uploaded_file($file['tmp_name'],$dest))throw new RuntimeException('Could not store restricted photo.');
    make_resized_image($dest,$dest,1600,1600,82);@chmod($dest,0640);return 'uploads/'.$kind.'/'.$name;
}

function make_resized_image(string $src, string $dest, int $maxW, int $maxH, int $quality=82): void {
    if (!extension_loaded('gd')) { if ($src !== $dest) copy($src,$dest); return; }
    $info = @getimagesize($src); if (!$info) return;
    [$w,$h] = $info; $scale = min(1,$maxW/$w,$maxH/$h); $nw=max(1,(int)round($w*$scale)); $nh=max(1,(int)round($h*$scale));
    switch ($info['mime']) { case 'image/jpeg': $im=@imagecreatefromjpeg($src); break; case 'image/png': $im=@imagecreatefrompng($src); break; case 'image/webp': $im=@imagecreatefromwebp($src); break; default: return; }
    if (!$im) return; $out=imagecreatetruecolor($nw,$nh); imagealphablending($out,false); imagesavealpha($out,true); imagecopyresampled($out,$im,0,0,0,0,$nw,$nh,$w,$h);
    switch ($info['mime']) { case 'image/jpeg': imagejpeg($out,$dest,$quality); break; case 'image/png': imagepng($out,$dest,6); break; case 'image/webp': imagewebp($out,$dest,$quality); break; }
    imagedestroy($im); imagedestroy($out);
}

function role_level(string $role): int { return ['viewer'=>10,'operator'=>20,'approver'=>30,'superadmin'=>40][$role] ?? 0; }
function can_close(string $role): bool { return in_array($role,['approver','superadmin'],true); }
function can_create_admin(string $role): bool { return in_array($role,['approver','superadmin'],true); }
function can_manage_dvi(string $role): bool { return in_array($role,['operator','approver','superadmin'],true); }
function can_approve_identification(string $role): bool { return in_array($role,['approver','superadmin'],true); }

function render_lang(string $en,string $np): string {
    return '<span class="lang-en">'.e($en).'</span><span class="lang-np">'.e($np).'</span>';
}

/* v1.4 case tracking helpers */
function case_type_labels(): array {
    return [
        'missing'=>['Missing Person','हराइरहेको व्यक्ति'],
        'rescue_waiting'=>['Waiting for Rescue','उद्धारको प्रतीक्षामा'],
        'rescued'=>['Rescued Person','उद्धार गरिएको व्यक्ति'],
        'deceased'=>['Recovered / Unidentified Deceased','फेला परेको / पहिचान नखुलेको शव'],
    ];
}
function case_type_label(string $type, string $lang='en'): string {
    $m=case_type_labels(); $v=$m[$type]??[ucwords(str_replace('_',' ',$type)),ucwords(str_replace('_',' ',$type))];
    return $lang==='np'?$v[1]:$v[0];
}
function case_status_labels(): array {
    return [
        'draft'=>['Draft','ड्राफ्ट'],
        'open'=>['Open / Reported','खुला / दर्ता भएको'],
        'under_review'=>['Under Review','समीक्षामा'],
        'searching'=>['Search in Progress','खोजी जारी'],
        'located'=>['Located','स्थान पत्ता लागेको'],
        'found_alive'=>['Found Alive','जीवित फेला परेको'],
        'found_injured'=>['Found Injured','घाइते अवस्थामा फेला परेको'],
        'found_deceased'=>['Found Deceased','मृत अवस्थामा फेला परेको'],
        'rescue_dispatched'=>['Rescue Team Dispatched','उद्धार टोली पठाइएको'],
        'rescued_safe'=>['Rescued Safe','सुरक्षित उद्धार गरिएको'],
        'rescued_injured'=>['Rescued Injured','घाइते अवस्थामा उद्धार गरिएको'],
        'identity_unknown'=>['Identity Unknown','पहिचान खुलेको छैन'],
        'potential_match'=>['Potential Match','सम्भावित मिलान'],
        'family_contacted'=>['Family Contacted','परिवारसँग सम्पर्क'],
        'identification_review'=>['Identification Under Review','पहिचान समीक्षा हुँदै'],
        'forensic_confirmed'=>['Forensic Match Confirmed','फरेन्सिक मिलान पुष्टि'],
        'ready_handover'=>['Ready for Handover','हस्तान्तरणका लागि तयार'],
        'handed_over'=>['Handed Over','परिवारलाई हस्तान्तरण'],
        'reunited'=>['Reunited with Family','परिवारसँग पुनर्मिलन'],
        'shifted'=>['Shifted / Transferred','स्थानान्तरण गरिएको'],
        'close_requested'=>['Closure Requested','बन्द गर्न स्वीकृति मागिएको'],
        'closed'=>['Closed','बन्द गरिएको'],
    ];
}
function case_condition_labels(): array {
    return [
        'unknown'=>['Unknown / Not Confirmed','अज्ञात / पुष्टि नभएको'],
        'safe'=>['Safe','सुरक्षित'],
        'stable'=>['Stable','स्थिर'],
        'alive'=>['Alive','जीवित'],
        'minor_injury'=>['Minor Injury','सामान्य चोट'],
        'injured'=>['Injured','घाइते'],
        'semi_conscious'=>['Semi-conscious','अर्धचेत'],
        'serious'=>['Serious','गम्भीर'],
        'critical'=>['Critical','अत्यन्त गम्भीर'],
        'unconscious'=>['Unconscious','बेहोस'],
        'unable_communicate'=>['Unable to Communicate','सम्पर्क गर्न नसक्ने'],
        'deceased'=>['Deceased','मृत'],
    ];
}
function case_status_label(string $status, string $lang='en'): string {
    $m=case_status_labels(); $v=$m[$status]??[ucwords(str_replace('_',' ',$status)),ucwords(str_replace('_',' ',$status))];
    return $lang==='np'?$v[1]:$v[0];
}
function case_condition_label(?string $condition, string $lang='en'): string {
    if(!$condition) return '';
    $m=case_condition_labels(); $v=$m[$condition]??[ucwords(str_replace('_',' ',$condition)),ucwords(str_replace('_',' ',$condition))];
    return $lang==='np'?$v[1]:$v[0];
}
function public_status_html(string $status): string {
    return '<span class="lang-en">'.e(case_status_label($status,'en')).'</span><span class="lang-np">'.e(case_status_label($status,'np')).'</span>';
}
function public_condition_html(?string $condition): string {
    if(!$condition) return '';
    return '<span class="lang-en">'.e(case_condition_label($condition,'en')).'</span><span class="lang-np">'.e(case_condition_label($condition,'np')).'</span>';
}

function public_case_join_sql(): string {
    return 'LEFT JOIN rescued_person_details rd ON rd.case_id=c.id LEFT JOIN deceased_details dd ON dd.case_id=c.id';
}
function public_case_select_sql(): string {
    return 'c.*,rd.rescue_location,rd.current_institution_name,rd.institution_contact_phone,rd.institution_office_phone,rd.public_photo_allowed,rd.identity_status rescued_identity,rd.estimated_age_min r_age_min,rd.estimated_age_max r_age_max,dd.recovery_location dd_recovery_location,dd.current_mortuary,dd.identity_status deceased_identity,dd.estimated_age_min d_age_min,dd.estimated_age_max d_age_max,dd.public_identity_release,dd.official_identity_name';
}
function derive_public_case_card(array $r): array {
    $isDead=$r['type']==='deceased';
    $isRescued=$r['type']==='rescued';
    $publicName=$r['name'];
    $showPhoto=!empty($r['thumb_url']);
    $publicContact=$r['family_contact_phone'];
    $displayLocation=$r['last_seen_address'];
    $ageText=$r['age']!==null?(string)$r['age']:'';
    if($isRescued){
        if(($r['rescued_identity']??'')==='unknown'||$publicName==='')$publicName='Unknown Rescued Person / पहिचान नखुलेको उद्धार व्यक्ति';
        $showPhoto=$showPhoto&&!empty($r['public_photo_allowed']);
        $publicContact=$r['institution_office_phone']?:($r['institution_contact_phone']?:'');
        $displayLocation=$r['current_institution_name']?:($r['rescue_location']?:$r['last_seen_address']);
        if($ageText===''&&($r['r_age_min']||$r['r_age_max'])){$mn=$r['r_age_min'];$mx=$r['r_age_max'];$ageText=($mn&&$mx&&$mn==$mx)?(string)$mn:(($mn?:'?').'–'.($mx?:'?'));}
    } elseif($isDead){
        $showPhoto=false;
        $publicContact='';
        $publicName=(($r['deceased_identity']??'')==='confirmed'&&!empty($r['public_identity_release'])&&!empty($r['official_identity_name']))?$r['official_identity_name']:'Unidentified Deceased Person / पहिचान नखुलेको शव';
        $displayLocation=$r['dd_recovery_location']?:$r['current_mortuary'];
        if($ageText===''&&($r['d_age_min']||$r['d_age_max'])){$mn=$r['d_age_min'];$mx=$r['d_age_max'];$ageText=($mn&&$mx&&$mn==$mx)?(string)$mn:(($mn?:'?').'–'.($mx?:'?'));}
    }
    $ageGender=trim(($ageText!==''?$ageText.' yrs':'Age unknown').' · '.($r['gender']?:'Unknown'));
    return ['row'=>$r,'isDead'=>$isDead,'publicName'=>$publicName,'showPhoto'=>$showPhoto,'publicContact'=>$publicContact,'displayLocation'=>$displayLocation,'ageGender'=>$ageGender];
}
function public_case_icon(string $name): string {
    $icons=[
        'user'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'pin'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'phone'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    ];
    return $icons[$name]??'';
}
function render_public_case_card(array $card): void {
    $r=$card['row'];$isDead=$card['isDead'];
    echo '<article class="cb-card type-'.e($r['type']).'">';
    echo '<div class="cb-photo">';
    if($card['showPhoto']) echo '<img src="'.e(base_url($r['thumb_url'])).'" alt="'.e($card['publicName']).'" loading="lazy">';
    else echo '<div class="cb-photo-fallback">'.public_case_icon('user').'</div>';
    echo '<span class="cb-type-ribbon">'.e(case_type_label($r['type'])).'</span></div>';
    echo '<div class="cb-body"><div class="cb-toprow"><span class="status status-'.e($r['status']).'">'.public_status_html($r['status']).'</span></div>';
    echo '<h3 class="cb-name">'.e($card['publicName']).'</h3><div class="cb-code">'.e($r['case_code']).'</div>';
    echo '<ul class="cb-meta"><li>'.public_case_icon('user').'<span>'.e($card['ageGender']).'</span></li><li>'.public_case_icon('pin').'<span>'.e($card['displayLocation']?:'Location not published').'</span></li></ul>';
    if($card['publicContact']) echo '<a class="cb-contact" href="tel:'.e($card['publicContact']).'">'.public_case_icon('phone').'<span>'.e($card['publicContact']).'</span></a>';
    elseif($isDead) echo '<div class="cb-restricted">Photo and personal contact details are withheld out of respect for the deceased.</div>';
    echo '</div><a class="cb-cta" href="'.e(base_url('track/'.$r['case_code'])).'">View Full Status →</a></article>';
}


function missing_person_context_label(?string $value, string $lang='en'): string {
    $m=[
      'local'=>['Local','स्थानीय'],
      'worker'=>['Worker','कामदार'],
      'tourist'=>['Tourist','पर्यटक'],
      'student'=>['Student','विद्यार्थी'],
      'other'=>['Other','अन्य'],
    ];
    $v=$m[$value??'']??[$value?:'Not recorded',$value?:'उल्लेख छैन']; return $lang==='np'?$v[1]:$v[0];
}
function rescued_condition_label(?string $value, string $lang='en'): string {
    $m=[
      'safe'=>['Safe','सकुशल'],
      'injured'=>['Injured','घाइते'],
      'semi_conscious'=>['Semi-conscious','अर्धचेत'],
      'unconscious'=>['Unconscious','अचेत'],
    ];
    $v=$m[$value??'']??[ucwords(str_replace('_',' ',(string)$value)),ucwords(str_replace('_',' ',(string)$value))]; return $lang==='np'?$v[1]:$v[0];
}
function rescued_by_label(?string $value): string {
    return [
      'nepal_army'=>'Nepal Army / नेपाली सेना','nepal_police'=>'Nepal Police / नेपाल प्रहरी','apf'=>'APF / सशस्त्र प्रहरी',
      'shelter_house'=>'Shelter House / आश्रय गृह','hospital'=>'Hospital / अस्पताल','local_volunteer'=>'Local Volunteer / Rescuer / स्थानीय उद्धारकर्ता',
      'local_government'=>'Local Government / स्थानीय तह','ngo'=>'NGO/INGO','other'=>'Other / अन्य',
    ][$value??'']??ucwords(str_replace('_',' ',(string)$value));
}
function deceased_recovered_by_label(?string $value): string {
    return ['nepal_army'=>'Nepal Army / नेपाली सेना','nepal_police'=>'Nepal Police / नेपाल प्रहरी','apf'=>'APF / सशस्त्र प्रहरी','local_volunteers'=>'Local Volunteers / स्थानीय स्वयंसेवक','other'=>'Other / अन्य'][$value??'']??ucwords(str_replace('_',' ',(string)$value));
}

function family_request_code(): string {
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for($a=0;$a<10;$a++){
        $raw=''; for($i=0;$i<9;$i++)$raw.=$alphabet[random_int(0,strlen($alphabet)-1)];
        $code='FR-'.substr($raw,0,3).'-'.substr($raw,3,3).'-'.substr($raw,6,3);
        try{$q=db()->prepare('SELECT 1 FROM family_match_requests WHERE request_code=?');$q->execute([$code]);if(!$q->fetchColumn())return $code;}catch(Throwable $e){return $code;}
    }
    throw new RuntimeException('Could not generate family request code.');
}

function sms_configured(): bool {
    $cfg=app_config()['sms']??[];
    return !empty($cfg['otp_webhook_url']);
}
function send_otp_sms(string $phone,string $code): bool {
    $cfg=app_config()['sms']??[]; $url=trim((string)($cfg['otp_webhook_url']??''));
    if($url==='')return false;
    $payload=json_encode(['phone'=>$phone,'message'=>'Rescue Nepal verification code: '.$code,'code'=>$code],JSON_UNESCAPED_UNICODE);
    $headers="Content-Type: application/json\r\n";
    if(!empty($cfg['bearer_token']))$headers.='Authorization: Bearer '.trim((string)$cfg['bearer_token'])."\r\n";
    $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>$headers,'content'=>$payload,'timeout'=>8,'ignore_errors'=>true]]);
    $r=@file_get_contents($url,false,$ctx); return $r!==false;
}
function create_phone_otp(string $phone,string $purpose='family_match'): bool {
    $phone=clean_phone($phone); if(!valid_phone($phone) || !sms_configured())return false;
    $code=(string)random_int(100000,999999); $hash=password_hash($code,PASSWORD_DEFAULT);
    db()->prepare('INSERT INTO phone_otps(phone,purpose,code_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute([$phone,$purpose,$hash]);
    return send_otp_sms($phone,$code);
}
function verify_phone_otp(string $phone,string $code,string $purpose='family_match'): bool {
    $phone=clean_phone($phone); if(!preg_match('/^\d{6}$/',$code))return false;
    $q=db()->prepare('SELECT * FROM phone_otps WHERE phone=? AND purpose=? AND verified_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1');
    $q->execute([$phone,$purpose]);$o=$q->fetch(); if(!$o || (int)$o['attempts']>=5)return false;
    db()->prepare('UPDATE phone_otps SET attempts=attempts+1 WHERE id=?')->execute([$o['id']]);
    if(!password_verify($code,$o['code_hash']))return false;
    db()->prepare('UPDATE phone_otps SET verified_at=NOW() WHERE id=?')->execute([$o['id']]);return true;
}

function normalize_search_text(?string $v): string {
    $v=mb_strtolower(trim((string)$v),'UTF-8');
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function text_similarity(?string $a,?string $b): float {
    $a=normalize_search_text($a);$b=normalize_search_text($b); if($a===''||$b==='')return 0.0;
    if($a===$b)return 100.0; if(str_contains($a,$b)||str_contains($b,$a))return 88.0;
    similar_text($a,$b,$pct); return (float)$pct;
}
function token_overlap_score(?string $a,?string $b): float {
    $aa=array_values(array_unique(array_filter(explode(' ',normalize_search_text($a)))));$bb=array_values(array_unique(array_filter(explode(' ',normalize_search_text($b)))));
    if(!$aa||!$bb)return 0.0; $inter=count(array_intersect($aa,$bb)); return 100.0*$inter/max(count($aa),count($bb));
}
function public_case_search_score(array $c,string $q): float {
    if($q==='')return 1.0; $best=0.0;
    foreach([$c['case_code']??'', $c['name']??'', $c['family_contact_phone']??'', $c['from_location']??'', $c['last_seen_address']??'', $c['where_found']??'', $c['current_location']??''] as $v){$best=max($best,text_similarity($q,(string)$v),token_overlap_score($q,(string)$v));}
    return $best;
}

function run_case_reconciliation(int $sourceCaseId, ?int $adminId=null): int {
    $q=db()->prepare('SELECT c.*,rd.estimated_age_min r_age_min,rd.estimated_age_max r_age_max,rd.rescue_location r_location,rd.distinguishing_marks r_marks,dd.estimated_age_min d_age_min,dd.estimated_age_max d_age_max,dd.recovery_location d_location,CONCAT_WS(" ",dd.tattoos,dd.scars,dd.birthmarks) d_marks FROM cases c LEFT JOIN rescued_person_details rd ON rd.case_id=c.id LEFT JOIN deceased_details dd ON dd.case_id=c.id WHERE c.id=? AND c.deleted_at IS NULL');
    $q->execute([$sourceCaseId]);$src=$q->fetch(); if(!$src || !in_array($src['type'],['rescued','deceased'],true))return 0;
    $miss=db()->query("SELECT * FROM cases WHERE type='missing' AND deleted_at IS NULL AND status NOT IN ('closed') ORDER BY updated_at DESC LIMIT 3000")->fetchAll();
    $count=0;
    foreach($miss as $m){
        $score=0.0;$reasons=[];
        if(($src['gender']??'Unknown')!=='Unknown' && ($m['gender']??'Unknown')!=='Unknown'){
            if($src['gender']===$m['gender']){$score+=20;$reasons[]='Gender consistent';}else{$score-=35;$reasons[]='Gender conflict';}
        }
        $amin=$src['type']==='rescued'?$src['r_age_min']:$src['d_age_min'];$amax=$src['type']==='rescued'?$src['r_age_max']:$src['d_age_max'];
        if($src['age']!==null){$amin=$amax=(int)$src['age'];}
        if($m['age']!==null && $amin!==null && $amax!==null){$a=(int)$m['age'];if($a>=(int)$amin-2&&$a<=(int)$amax+2){$score+=20;$reasons[]='Age range consistent';}else{$score-=12;$reasons[]='Age range differs';}}
        if(!empty($src['name'])){$ns=text_similarity($src['name'],$m['name']);if($ns>=85){$score+=30;$reasons[]='Name strongly similar';}elseif($ns>=65){$score+=18;$reasons[]='Name partly similar';}}
        $loc=$src['type']==='rescued'?$src['r_location']:$src['d_location'];$ls=token_overlap_score($loc,$m['last_seen_address'].' '.$m['from_location']);if($ls>=50){$score+=15;$reasons[]='Location terms overlap';}elseif($ls>=20){$score+=8;$reasons[]='Some location overlap';}
        if(!empty($src['last_contacted_bs'])&&!empty($m['last_contacted_bs'])&&$src['last_contacted_bs']===$m['last_contacted_bs']){$score+=5;$reasons[]='Date consistent';}
        $score=max(0,min(100,$score)); if($score<20)continue;
        db()->prepare("INSERT INTO case_matches(source_case_id,candidate_case_id,match_score,reasons_json,status,created_by_admin) VALUES (?,?,?,?,'suggested',?) ON DUPLICATE KEY UPDATE match_score=VALUES(match_score),reasons_json=VALUES(reasons_json),created_by_admin=VALUES(created_by_admin),created_at=NOW()")
          ->execute([$sourceCaseId,$m['id'],$score,json_encode($reasons,JSON_UNESCAPED_UNICODE),$adminId]);$count++;
    }
    return $count;
}
