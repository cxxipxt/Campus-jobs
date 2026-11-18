<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/work-skills.php';
$student=identity('student');
$error=null; $where=["j.status='Open'"]; $params=[];
try {
    $q=input('q',$_GET); $location=input('location',$_GET);
    if(mb_strlen($q,'UTF-8')>150 || mb_strlen($location,'UTF-8')>255) throw new InvalidArgumentException('คำค้นหายาวเกินไป');
    // LOCATE searches literal text; user % and _ are not wildcards.
    if($q!=='') {
        $terms=[$q];
        $canonical=canonical_skill($q);
        if(isset(work_skill_catalog()[$canonical])) $terms=array_unique([$q,$canonical,...work_skill_catalog()[$canonical]]);
        $matches=[];
        foreach($terms as $term) { $matches[]='LOCATE(?,s.skill_name)>0'; $params[]=$term; }
        $where[]='(EXISTS(SELECT 1 FROM JOB_SKILL s WHERE s.job_id=j.job_id AND ('.implode(' OR ',$matches).')) OR LOCATE(?,j.job_title)>0 OR LOCATE(?,j.description)>0 OR EXISTS(SELECT 1 FROM EMPLOYER employer WHERE employer.employer_id=j.employer_id AND LOCATE(?,employer.company_name)>0))';
        array_push($params,$q,$q,$q);
    }
    if($student && input('my_skills',$_GET)==='1') {
        $terms=[];
        foreach(query('SELECT skill_name FROM STUDENT_SKILL WHERE student_id=?',[$student])->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $canonical=canonical_skill($name);
            foreach([$canonical,...(work_skill_catalog()[$canonical]??[])] as $term) $terms[skill_key($term)]=true;
        }
        if($terms) {
            $where[]="EXISTS (SELECT 1 FROM JOB_SKILL s WHERE s.job_id=j.job_id AND LOWER(TRIM(REGEXP_REPLACE(s.skill_name, '[[:space:]]+', ' '))) IN (".implode(',',array_fill(0,count($terms),'?')).'))';
            array_push($params,...array_keys($terms));
        } else $where[]='1=0';
    }
    if($location!=='') { $where[]='LOCATE(?,j.location)>0'; $params[]=$location; }
    if(input('category_id',$_GET)!=='') { $where[]='j.category_id=?'; $params[]=positive_id(input('category_id',$_GET)); }
    foreach (['min_hours'=>'>=', 'max_hours'=>'<='] as $key=>$operator) {
        $value=input($key,$_GET);
        if ($value !== '') { $hours=money($value); if ((float)$hours < 0 || (float)$hours > 24) throw new InvalidArgumentException('ชั่วโมงทำงานต้องอยู่ระหว่าง 0–24'); $where[]='j.hours_per_day'.$operator.'?'; $params[]=$hours; }
    }
    if (input('min_hours',$_GET)!=='' && input('max_hours',$_GET)!=='' && (float)input('min_hours',$_GET)>(float)input('max_hours',$_GET)) throw new InvalidArgumentException('ชั่วโมงขั้นต่ำต้องไม่มากกว่าชั่วโมงสูงสุด');
    $min=input('min_wage',$_GET); $max=input('max_wage',$_GET);
    if($min!=='') { $where[]='j.hourly_wage>=?'; $params[]=money($min); }
    if($max!=='') { $where[]='j.hourly_wage<=?'; $params[]=money($max); }
    if($min!=='' && $max!=='' && (float)$min>(float)$max) throw new InvalidArgumentException('ค่าจ้างขั้นต่ำต้องไม่มากกว่าค่าสูงสุด');
} catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
$categories=query('SELECT category_id,category_name FROM JOB_CATEGORY ORDER BY category_name')->fetchAll();
if (input('category_id', $_GET) === '' && input('category', $_GET) !== '') {
    foreach ($categories as $category) {
        if ($category['category_name'] === input('category', $_GET)) {
            $_GET['category_id'] = (string) $category['category_id'];
            $where[] = 'j.category_id=?';
            $params[] = $category['category_id'];
            break;
        }
    }
}
$page=max(1,min(100000,(int)input('page',$_GET))); $size=12;
$count=$error?0:(int)query('SELECT COUNT(*) FROM JOB j WHERE '.implode(' AND ',$where),$params)->fetchColumn();
$page=min($page,max(1,(int)ceil($count/$size))); $offset=($page-1)*$size;
$sort = input('sort', $_GET);
$order = $sort === 'wage' ? 'j.hourly_wage DESC, j.job_id DESC' : 'j.created_at DESC, j.job_id DESC';
$jobs=$error?[]:query('SELECT j.*,e.company_name,c.category_name FROM JOB j JOIN EMPLOYER e ON e.employer_id=j.employer_id JOIN JOB_CATEGORY c ON c.category_id=j.category_id WHERE '.implode(' AND ',$where).' ORDER BY '.$order.' LIMIT '.$size.' OFFSET '.$offset,$params)->fetchAll();
start_page('ค้นหางาน', 'search-page', false);
?>
<header class="job-finder"><h1>ค้นหางานพาร์ทไทม์</h1><div class="finder-bar"><p><label for="q">คำค้นหา</label><input form="job-filters" id="q" name="q" type="search" placeholder="เช่น บาริสต้า ติวเตอร์" value="<?= e(input('q',$_GET)) ?>"></p><p><label for="location">สถานที่ทำงาน</label><input form="job-filters" id="location" name="location" placeholder="จังหวัด เขต หรือย่าน" maxlength="255" value="<?= e(input('location',$_GET)) ?>"></p><button type="submit" form="job-filters">ค้นหางาน</button></div></header>
<?php error_box($error); ?>
<div class="search-layout">
<details class="job-filters" open><summary>ตัวกรองงาน</summary>
<form id="job-filters" method="get" class="panel" action="<?= e(url('jobs.php')) ?>">

<?php if($student): ?><p><label><input type="checkbox" name="my_skills" value="1" <?=input('my_skills',$_GET)==='1'?'checked':''?>> ตรงกับทักษะของฉันอย่างน้อยหนึ่งทักษะ</label></p><?php endif; ?>
<p><label for="category_id">ประเภทงาน</label><select id="category_id" name="category_id"><option value="">ทุกประเภท</option><?php foreach($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= input('category_id',$_GET)===(string)$category['category_id']?'selected':'' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select></p>
<?php foreach ([['ค่าจ้างต่อชั่วโมง (บาท)','min_wage','max_wage',[0,40,50,60,80,100,150,200,300,500,1000]],['เวลาทำงานต่อวัน (ชั่วโมง)','min_hours','max_hours',[0,2,3,4,5,6,7,8,10,12,24]]] as [$legend,$minimum,$maximum,$values]): ?>
<fieldset class="filter-range"><legend><?= e($legend) ?></legend><div class="filter-range-fields">
<?php foreach ([$minimum=>'ต่ำสุด',$maximum=>'สูงสุด'] as $key=>$label): $selected=input($key,$_GET); $choices=$values; if($selected!=='' && is_numeric($selected) && !in_array((float)$selected,$choices)) { $choices[]=(float)$selected; sort($choices); } ?>
<div><label for="<?= e($key) ?>"><?= e($label) ?></label><select id="<?= e($key) ?>" name="<?= e($key) ?>" data-custom-number data-number-max="<?= str_contains($key, 'hours') ? '24' : '99999999.99' ?>"><option value="">ไม่จำกัด</option><?php foreach($choices as $value): ?><option value="<?= e($value) ?>" <?= $selected!=='' && (float)$selected===$value*1.0?'selected':'' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
<?php endforeach; ?></div></fieldset><?php endforeach; ?>
<p class="filter-help">เมื่อกรองชั่วโมง จะแสดงเฉพาะงานที่ระบุชั่วโมงทำงานต่อวัน</p>
<input type="hidden" name="sort" value="<?= $sort === 'wage' ? 'wage' : '' ?>">
<div class="filter-actions"><button>ใช้ตัวกรอง</button><a href="<?= e(url('jobs.php')) ?>">ล้างตัวกรอง</a></div></form>
</details>
<div class="search-results">
<div class="search-results-header">
<h2>พบ <?= e($count) ?> ประกาศ</h2>
<nav class="sort-switch" aria-label="เรียงผลการค้นหา"><span>เรียงตาม</span><div>
<?php foreach ([''=>'ล่าสุด','wage'=>'ค่าจ้างสูงสุด'] as $key=>$label):
    $args = [];
    foreach (['q','location','category_id','category','min_wage','max_wage','min_hours','max_hours','my_skills'] as $field) {
        $v=input($field,$_GET); if ($v !== '') $args[$field]=$v;
    }
    if ($key !== '') $args['sort']=$key;
    $queryString=http_build_query($args); ?>
<a href="<?= e(url('jobs.php'.($queryString ? '?'.$queryString : ''))) ?>" <?= (($sort==='wage'?'wage':'')===$key)?'aria-current="true"':'' ?>><?= e($label) ?></a>
<?php endforeach; ?></div></nav>
</div>
<?php if(!$jobs && !$error): ?><section class="finder-empty"><?php if (count($where)>1): ?><h3>ยังไม่มีงานที่ตรงกับการค้นหานี้</h3><p>ลองใช้คำค้นที่กว้างขึ้น หรือดูประกาศในพื้นที่อื่น</p><a href="<?= e(url('jobs.php')) ?>">ดูทุกประกาศ</a><?php else: ?><h3>ยังไม่มีประกาศที่เปิดรับ</h3><p>เมื่อมีงานเปิดรับใหม่ คุณจะเห็นตำแหน่งและค่าจ้างที่นี่</p><?php endif; ?></section><?php endif; ?>
<div class="active-job-filters" data-active-job-filters hidden></div>
<div class="grid"><?php foreach($jobs as $job): ?><article class="job-card"><div class="job-card-copy"><span class="status-pill"><?= e($job['category_name']) ?></span><h3><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></h3><p><?= e($job['company_name']) ?></p><p><?= e($job['location']) ?></p><p class="job-meta"><?= $job['hours_per_day'] !== null ? e($job['hours_per_day']).' ชม./วัน · ' : '' ?><?= e($job['work_schedule']) ?></p></div><div class="job-card-actions"><p class="rate">฿<?= e(number_format((float)$job['hourly_wage'],2)) ?><small>/ชม.</small></p><a class="button" href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>">รายละเอียด</a></div></article><?php endforeach; ?></div>
<nav aria-label="แบ่งหน้าผลการค้นหา"><?php foreach([$page-1=>'ก่อนหน้า',$page+1=>'ถัดไป'] as $next=>$label): if($next<1 || $next>ceil($count/$size))continue; $args=[]; foreach(['q','location','category_id','min_wage','max_wage','min_hours','max_hours','sort','my_skills'] as $key)$args[$key]=input($key,$_GET); $args['page']=$next; ?><a href="<?= e(url('jobs.php?'.http_build_query($args))) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
</div></div>
<script src="<?= e(url('assets/job-filters.js')) ?>" defer></script>
<?php end_page(); ?>
