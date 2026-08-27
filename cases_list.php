<?php
require_once __DIR__.'/includes/layout.php';

$type=$_GET['type']??'';
if(!in_array($type,['','missing','rescue_waiting','rescued','deceased'],true))$type='';
$page=max(1,(int)($_GET['page']??1));
$perPage=24;

$where=['c.deleted_at IS NULL','c.is_public=1',"c.status<>'draft'"];$args=[];
if($type!==''){$where[]='c.type=?';$args[]=$type;}
$whereSql=implode(' AND ',$where);

$cnt=db()->prepare('SELECT COUNT(*) FROM cases c WHERE '.$whereSql);$cnt->execute($args);
$total=(int)$cnt->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));
$page=min($page,$totalPages);
$offset=($page-1)*$perPage;

$sql='SELECT '.public_case_select_sql().' FROM cases c '.public_case_join_sql().' WHERE '.$whereSql.' ORDER BY c.updated_at DESC LIMIT '.$perPage.' OFFSET '.$offset;
$st=db()->prepare($sql);$st->execute($args);
$cards=array_map('derive_public_case_card',$st->fetchAll());

$typeTabs=['' => 'All Cases','missing'=>case_type_label('missing'),'rescue_waiting'=>case_type_label('rescue_waiting'),'rescued'=>case_type_label('rescued'),'deceased'=>case_type_label('deceased')];

page_header('Browse Cases');
?>
<section class="cb-hero"><div><h1><?=render_lang('Browse Public Cases','सार्वजनिक केसहरू हेर्नुहोस्')?></h1><p><?=render_lang('All open missing, rescue-request, rescued and deceased cases currently published. No search needed — scroll through the latest records.','सार्वजनिक गरिएका सबै बेपत्ता, उद्धार अनुरोध, उद्धार भएका र मृतक केसहरू। खोज्नु नपर्ने — सिधै सूची हेर्नुहोस्।')?></p></div></section>

<nav class="cb-tabs">
<?php foreach($typeTabs as $val=>$label):?>
<a class="cb-tab <?=$type===$val?'active':''?>" href="<?=e(base_url('cases'.($val!==''?'?type='.$val:'')))?>"><?=e($label)?></a>
<?php endforeach;?>
</nav>

<div class="section-title"><div><h2><?=e($total)?> <?=render_lang('case(s) published','सार्वजनिक केस')?></h2></div></div>

<?php if($cards):?>
<div class="cb-grid">
<?php foreach($cards as $card) render_public_case_card($card);?>
</div>
<?php if($totalPages>1):?>
<div class="pagination">
<?php if($page>1):?><a href="<?=e(base_url('cases?'.http_build_query(['type'=>$type,'page'=>$page-1])))?>">← Previous</a><?php endif;?>
<span class="active">Page <?=e($page)?> of <?=e($totalPages)?></span>
<?php if($page<$totalPages):?><a href="<?=e(base_url('cases?'.http_build_query(['type'=>$type,'page'=>$page+1])))?>">Next →</a><?php endif;?>
</div>
<?php endif;?>
<?php else:?>
<div class="card empty-search-result"><h3><?=render_lang('No public cases found.','कुनै सार्वजनिक केस भेटिएन।')?></h3></div>
<?php endif;?>
<?php page_footer(); ?>
