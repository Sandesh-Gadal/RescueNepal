<?php
require_once __DIR__.'/../includes/auth.php';
$admin=require_admin(['operator','approver','superadmin']);
$kind=$_GET['kind']??'';$id=(int)($_GET['id']??0);$relative='';
if($kind==='dvi'){$q=db()->prepare("SELECT photo_url FROM cases WHERE id=? AND type='deceased' AND deleted_at IS NULL");$q->execute([$id]);$relative=(string)($q->fetchColumn()?:'');}
elseif($kind==='dvi-photo'){$q=db()->prepare("SELECT cm.file_path FROM case_media cm JOIN cases c ON c.id=cm.case_id WHERE cm.id=? AND cm.media_kind='deceased_photo' AND c.type='deceased' AND c.deleted_at IS NULL");$q->execute([$id]);$relative=(string)($q->fetchColumn()?:'');}
elseif($kind==='family'){$q=db()->prepare('SELECT photo_url FROM family_match_requests WHERE id=?');$q->execute([$id]);$relative=(string)($q->fetchColumn()?:'');}
else{http_response_code(404);exit('Not found');}
$prefix=in_array($kind,['dvi','dvi-photo'],true)?'uploads/evidence/':'uploads/family/';if($relative===''||!str_starts_with($relative,$prefix)){http_response_code(404);exit('Not found');}
$root=realpath(dirname(__DIR__));$file=realpath($root.'/'.$relative);$allowedDir=realpath($root.'/'.rtrim($prefix,'/'));if(!$file||!$allowedDir||!str_starts_with($file,$allowedDir.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit('Not found');}
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit('Unsupported');}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow, noarchive');header('Content-Disposition: inline; filename="restricted-image.'.($mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg')).'"');audit($admin['id'],'view_restricted_media',$kind==='dvi'?$id:null,$kind.' media '.$id);readfile($file);exit;
