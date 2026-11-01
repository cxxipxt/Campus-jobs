<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$error=null; $where=["j.status='Open'"]; $params=[];
try {
    $q=input('q',$_GET); $location=input('location',$_GET);
    if(mb_strlen($q,'UTF-8')>150 || mb_strlen($location,'UTF-8')>255) throw new InvalidArgumentException('คำค้นหายาวเกินไป');
    // LOCATE searches literal text; user % and _ are not wildcards.
    if($q!=='') { $where[]='LOCATE(?,j.job_title)>0'; $params[]=$q; }
    if($location!=='') { $where[]='LOCATE(?,j.location)>0'; $params[]=$location; }
    if(input('category_id',$_GET)!=='') { $where[]='j.category_id=?'; $params[]=positive_id(input('category_id',$_GET)); }
    $min=input('min_wage',$_GET); $max=input('max_wage',$_GET);
    if($min!=='') { $where[]='j.hourly_wage>=?'; $params[]=money($min); }
    if($max!=='') { $where[]='j.hourly_wage<=?'; $params[]=money($max); }
    if($min!=='' && $max!=='' && (float)$min>(float)$max) throw new InvalidArgumentException('ค่าจ้างขั้นต่ำต้องไม่มากกว่าค่าสูงสุด');
} catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
$categories=query('SELECT category_id,category_name FROM JOB_CATEGORY ORDER BY category_name')->fetchAll();
$page=max(1,min(100000,(int)input('page',$_GET))); $size=12;
$count=$error?0:(int)query('SELECT COUNT(*) FROM JOB j WHERE '.implode(' AND ',$where),$params)->fetchColumn();
$page=min($page,max(1,(int)ceil($count/$size))); $offset=($page-1)*$size;
$jobs=$error?[]:query('SELECT j.*,e.company_name,c.category_name FROM JOB j JOIN EMPLOYER e ON e.employer_id=j.employer_id JOIN JOB_CATEGORY c ON c.category_id=j.category_id WHERE '.implode(' AND ',$where).' ORDER BY j.created_at DESC,j.job_id DESC LIMIT '.$size.' OFFSET '.$offset,$params)->fetchAll();
start_page('ค้นหางาน'); error_box($error);
?>
<form method="get" class="panel" action="<?= e(url('jobs.php')) ?>">
<?php field('q','ชื่อตำแหน่ง',input('q',$_GET),'search',false,150); field('location','สถานที่',input('location',$_GET),'text',false); ?>
<p><label for="category_id">ประเภทงาน</label><select id="category_id" name="category_id"><option value="">ทุกประเภท</option><?php foreach($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= input('category_id',$_GET)===(string)$category['category_id']?'selected':'' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select></p>
<?php foreach(['min_wage'=>'ค่าจ้างขั้นต่ำ','max_wage'=>'ค่าจ้างสูงสุด'] as $key=>$label): ?><p><label for="<?= e($key) ?>"><?= e($label) ?> (บาท/ชั่วโมง)</label><input id="<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="0" max="99999999.99" step="0.01" value="<?= e(input($key,$_GET)) ?>"></p><?php endforeach; ?>
<button>ค้นหางาน</button> <a href="<?= e(url('jobs.php')) ?>">ล้างเงื่อนไข</a></form>
<h2>พบ <?= e($count) ?> ประกาศ</h2>
<?php if(!$jobs && !$error): ?><p>ไม่พบงานตามเงื่อนไข ลองเปลี่ยนคำค้นหาหรือลดเงื่อนไข</p><?php endif; ?>
<div class="grid"><?php foreach($jobs as $job): ?><article><h2><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></h2><p><?= e($job['company_name']) ?> · <?= e($job['category_name']) ?></p><p><?= e($job['location']) ?></p><p><?= e(number_format((float)$job['hourly_wage'],2)) ?> บาท/ชั่วโมง</p><p><?= e($job['work_schedule']) ?></p></article><?php endforeach; ?></div>
<nav aria-label="แบ่งหน้าผลการค้นหา"><?php foreach([$page-1=>'ก่อนหน้า',$page+1=>'ถัดไป'] as $next=>$label): if($next<1 || $next>ceil($count/$size))continue; $args=[]; foreach(['q','location','category_id','min_wage','max_wage'] as $key)$args[$key]=input($key,$_GET); $args['page']=$next; ?><a href="<?= e(url('jobs.php?'.http_build_query($args))) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<?php end_page(); ?>
