<?php
require_once __DIR__ . '/helpers.php';

function page_header(string $title, bool $admin=false): void {
    start_app_session();
    $GLOBALS['_hexa_admin_layout']=$admin;
    $appRaw=(string)app_config()['app_name'];
    $appDisplay=preg_replace('/^\+\s*/','',$appRaw) ?: 'Rescue & Missing Persons Registry';
    $app=e($appDisplay);
    $full=e($title.' - '.$appDisplay);
    if($admin || $title==='Admin Login'){ header('X-Robots-Tag: noindex, nofollow, noarchive'); header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#ffffff">'.(($admin || $title==='Admin Login')?'<meta name="robots" content="noindex,nofollow,noarchive">':'').'<title>'.$full.'</title>';
    echo '<link rel="stylesheet" href="'.e(base_url('assets/app.css?v=2.4.0')).'">';
    echo '<link rel="stylesheet" href="'.e(base_url('assets/simple-ui.css?v=2.4.0')).'">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"></head><body>';

    echo '<header class="simple-header"><div class="container simple-header-row">';
    echo '<a class="simple-site-brand" href="'.e(base_url()).'" aria-label="'.$app.'">';
    echo '<img class="simple-site-logo" src="'.e(base_url('assets/images/rescue-nepal-logo.jpg?v=1.6.3')).'" alt="Rescue Nepal">';
    echo '<span class="simple-site-title">'.$app.'</span></a>';
    echo '<div class="simple-header-actions"><label class="language-control"><span>Language</span><select id="langMode" aria-label="Language"><option value="both">English + नेपाली</option><option value="en">English</option><option value="np">नेपाली</option></select></label>';
    if($admin){
        echo '<a class="simple-text-link desktop-only" href="'.e(base_url('admin/logout.php')).'">Logout</a>';
    }
    echo '</div></div></header>';

    if($admin){
        echo '<nav class="simple-admin-nav no-print"><div class="container simple-admin-nav-row">';
        echo '<a href="'.e(base_url('admin/dashboard.php')).'">Cases</a>';
        echo '<a href="'.e(base_url('admin/operator_help.php')).'">New Report</a>';
        echo '<a href="'.e(base_url('admin/family_requests.php')).'">Family Requests</a>';
        echo '<a class="mobile-only" href="'.e(base_url('admin/logout.php')).'">Logout</a>';
        echo '</div></nav>';
    } elseif($title !== 'Home') {
        echo '<nav class="simple-public-nav no-print"><div class="container simple-public-nav-row">';
        echo '<a href="'.e(base_url()).'">Home</a>';
        echo '<a href="'.e(base_url('find')).'">Find Family</a>';
        echo '<a href="'.e(base_url('track.php')).'">Track Case</a>';
        echo '</div></nav>';
    }

    echo '<main class="container simple-main">';
    foreach(pull_flashes() as [$type,$msg]) echo '<div class="alert alert-'.e($type).'">'.e($msg).'</div>';
}

function page_footer(): void {
    echo '</main>';
    if(!empty($GLOBALS['_hexa_admin_layout'])){
        echo '<nav class="simple-mobile-admin-nav no-print"><a href="'.e(base_url('admin/dashboard.php')).'">Cases</a><a href="'.e(base_url('admin/operator_help.php')).'">New Report</a><a href="'.e(base_url('admin/logout.php')).'">Logout</a></nav>';
    }
    echo '<footer class="simple-footer"><div class="container">Rescue Nepal · Missing, Rescue, DVI & Family Reunification Registry</div></footer>';
    echo '<script src="'.e(base_url('assets/app.js?v=2.4.0')).'"></script></body></html>';
}
