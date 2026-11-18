<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/work-skills.php';
require_once dirname(__DIR__) . '/app/job-categories.php';
$catalog = job_subcategories();
$selectedSubs = [];
$student=identity('student');
$error=null; $where=["j.status='Open'"]; $params=[];
$categories=query('SELECT category_id,category_name FROM JOB_CATEGORY ORDER BY category_name')->fetchAll();
$selectedCategories = [];
$availableDays=[];
$availability=[];
try {
    foreach((array)($_GET['available_day']??[]) as $day) {
        if (!is_string($day) || !preg_match('/^[1-7]$/D',$day)) throw new InvalidArgumentException('วันทำงานไม่ถูกต้อง');
        $availableDays[]=(int)$day;
    }
    $availableDays=array_values(array_unique($availableDays));
    $from=input('available_from',$_GET); $to=input('available_to',$_GET);
    if(input('use_availability',$_GET)==='1') {
        if (!$student) throw new InvalidArgumentException('เข้าสู่ระบบเพื่อใช้เวลาว่างจากโปรไฟล์');
        $availability=query('SELECT weekday,start_time,end_time FROM STUDENT_AVAILABILITY WHERE student_id=?',[$student])->fetchAll(PDO::FETCH_UNIQUE);
        if (!$availability) throw new InvalidArgumentException('กรุณาเพิ่มเวลาว่างในโปรไฟล์ก่อน');
    } elseif ($availableDays || $from!=='' || $to!=='') {
        if (!$availableDays) throw new InvalidArgumentException('กรุณาเลือกวันที่สะดวกทำงาน');
        if (($from==='')!==($to==='')) throw new InvalidArgumentException('กรุณาระบุเวลาเริ่มและสิ้นสุดให้ครบ');
        foreach([$from,$to] as $time) if ($time!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)) throw new InvalidArgumentException('เวลาไม่ถูกต้อง');
        if ($from!=='' && $from >= $to) throw new InvalidArgumentException('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม');
        foreach($availableDays as $day) $availability[$day]=['start_time'=>$from?:'00:00','end_time'=>$to?:'24:00'];
    }
    if ($availability) {
        $matching=[];
        $shiftGroups=[];
        foreach(query("SELECT s.*,j.days_per_week FROM JOB_SHIFT s JOIN JOB j USING(job_id) WHERE j.status='Open'")->fetchAll() as $shift) $shiftGroups[$shift['job_id']][]=$shift;
        foreach($shiftGroups as $jobId=>$slots) {
            $covered=count(array_filter($slots,fn($slot)=>shift_fits_availability($slot,$availability)));
            $required=(int)($slots[0]['days_per_week']??count($slots));
            if ($covered >= $required) $matching[]=$jobId;
        }
        if ($matching) {
            $where[]='j.job_id IN ('.implode(',',array_fill(0,count($matching),'?')).')';
            array_push($params,...$matching);
        } else $where[]='1=0';
    }
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
    $requested = $_GET['category_id'] ?? [];
    foreach ((array)$requested as $value) {
        if ($value === '') continue;
        if (!is_string($value)) throw new InvalidArgumentException('ประเภทงานไม่ถูกต้อง');
        $selectedCategories[] = positive_id($value);
    }
    if (!$selectedCategories && input('category', $_GET) !== '') {
        foreach ($categories as $category) {
            if ($category['category_name'] === input('category', $_GET)) {
                $selectedCategories[] = (int)$category['category_id'];
                break;
            }
        }
    }
    $selectedCategories = array_values(array_unique($selectedCategories));
    $requestedSubs = (array)($_GET['subcategory'] ?? []);
    $allowedSubs = array_merge(...array_values($catalog));
    foreach ($requestedSubs as $sub) {
        if (!is_string($sub) || !in_array($sub,$allowedSubs,true)) throw new InvalidArgumentException('ประเภทงานย่อยไม่ถูกต้อง');
        $selectedSubs[] = $sub;
    }
    $selectedSubs = array_values(array_unique($selectedSubs));
    $categoryConditions = [];
    foreach (['j.category_id'=>$selectedCategories,'j.subcategory'=>$selectedSubs] as $column=>$values) {
        if (!$values) continue;
        $categoryConditions[] = $column.' IN ('.implode(',',array_fill(0,count($values),'?')).')';
        array_push($params,...$values);
    }
    if ($categoryConditions) $where[] = '('.implode(' OR ',$categoryConditions).')';
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
$page=max(1,min(100000,(int)input('page',$_GET))); $size=12;
$count=$error?0:(int)query('SELECT COUNT(*) FROM JOB j WHERE '.implode(' AND ',$where),$params)->fetchColumn();
$page=min($page,max(1,(int)ceil($count/$size))); $offset=($page-1)*$size;
$sort = input('sort', $_GET);
$order = $sort === 'wage' ? 'j.hourly_wage DESC, j.job_id DESC' : 'j.created_at DESC, j.job_id DESC';
$jobs=$error?[]:query('SELECT j.*,e.company_name,c.category_name FROM JOB j JOIN EMPLOYER e ON e.employer_id=j.employer_id JOIN JOB_CATEGORY c ON c.category_id=j.category_id WHERE '.implode(' AND ',$where).' ORDER BY '.$order.' LIMIT '.$size.' OFFSET '.$offset,$params)->fetchAll();
if ($jobs) {
    $jobIds=array_column($jobs,'job_id');
    $jobShifts=[];
    foreach(query('SELECT job_id,start_time,end_time FROM JOB_SHIFT WHERE job_id IN ('.implode(',',array_fill(0,count($jobIds),'?')).')',$jobIds)->fetchAll() as $shift) $jobShifts[$shift['job_id']][]=$shift;
    foreach($jobs as &$result) {
        if(isset($jobShifts[$result['job_id']])) {
            $result['work_schedule']=job_schedule_summary($jobShifts[$result['job_id']],$result['days_per_week']);
            $result['hours_per_day']=null;
        }
    }
    unset($result);
}
if (!$error) $_SESSION['job_search']=http_build_query($_GET);
start_page('ค้นหางาน', 'search-page', false);
?>
<header class="job-finder"><h1>ค้นหางานพาร์ทไทม์</h1><div class="finder-bar"><p><label for="q">ตำแหน่งงาน บริษัท หรือทักษะ</label><input form="job-filters" id="q" name="q" type="search" placeholder="เช่น บาริสต้า หรือ Excel" value="<?= e(input('q',$_GET)) ?>"></p><p><label for="location">สถานที่ทำงาน</label><input form="job-filters" id="location" name="location" placeholder="เช่น บางมด หรือกรุงเทพฯ" maxlength="255" value="<?= e(input('location',$_GET)) ?>"></p><button type="submit" form="job-filters">ค้นหางาน</button></div></header>
<?php error_box($error); ?>
<div class="search-layout">
<details class="job-filters" open><summary>ตัวกรองงาน</summary>
<form id="job-filters" method="get" class="panel" action="<?= e(url('jobs.php')) ?>">

<?php if($student): ?><p><label><input type="checkbox" name="my_skills" value="1" <?=input('my_skills',$_GET)==='1'?'checked':''?>> ตรงกับทักษะของฉันอย่างน้อยหนึ่งทักษะ</label></p><?php endif; ?>
<fieldset class="availability-filter"><legend>เวลาที่สะดวกทำงาน</legend>
<?php if($student): ?><label><input type="checkbox" name="use_availability" value="1" <?= input('use_availability',$_GET)==='1'?'checked':'' ?>> ใช้เวลาว่างจากโปรไฟล์</label><?php endif; ?>
<div class="available-days"><?php foreach(work_weekdays() as $day=>$label): ?><label><input type="checkbox" name="available_day[]" value="<?=$day?>" <?=in_array($day,$availableDays,true)?'checked':''?>><?=e($label)?></label><?php endforeach; ?></div>
<div class="availability-times"><label>เริ่มว่าง<input type="time" name="available_from" value="<?=e(input('available_from',$_GET))?>"></label><label>ว่างถึง<input type="time" name="available_to" value="<?=e(input('available_to',$_GET))?>"></label></div>
<p class="filter-help">ช่วงเวลาเดียวกันสำหรับวันที่เลือก เว้นเวลาไว้เพื่อเลือกทั้งวัน แสดงงานที่ทำได้ครบจำนวนวันตามประกาศและมีกะระบุไว้ เมื่อใช้โปรไฟล์จะยึดเวลาจากโปรไฟล์</p>
</fieldset>
<div class="category-tree"><strong>ประเภทงาน</strong>
<details data-category-picker><summary>เลือกประเภทงาน <span data-cat-count></span></summary>
<div class="category-popup">
<label>ค้นหาประเภทงาน<input type="search" data-cat-search placeholder="เช่น บาริสต้า"></label>
<button type="button" data-cat-clear hidden>ทั้งหมด · ล้างการเลือก</button>
<?php foreach ($categories as $category): $cid=(int)$category['category_id']; $parent=$category['category_name']; $children=$catalog[$parent]??[]; ?>
<div class="cat-group"><div class="cat-heading"><label><input type="checkbox" name="category_id[]" value="<?= $cid ?>" <?= in_array($cid,$selectedCategories,true)?'checked':'' ?>><?= e($parent) ?></label>
<button type="button" data-expand aria-label="<?= e('แสดงประเภทย่อยของ'.$parent) ?>" aria-expanded="true">⌄</button></div>
<div class="cat-children"><?php foreach ($children as $child): ?><label><input type="checkbox" name="subcategory[]" value="<?= e($child) ?>" <?= in_array($child,$selectedSubs,true)?'checked':'' ?>><?= e($child) ?></label><?php endforeach; ?></div></div>
<?php endforeach; ?><p data-cat-empty hidden>ไม่พบประเภทงานตามคำค้น</p></div></details>
<div data-cat-tags></div></div>

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
    $args = array_filter(['category_id'=>$selectedCategories,'subcategory'=>$selectedSubs,'available_day'=>$availableDays]);
    foreach (['q','location','min_wage','max_wage','min_hours','max_hours','my_skills','available_from','available_to','use_availability'] as $field) {
        $v=input($field,$_GET); if ($v !== '') $args[$field]=$v;
    }
    if ($key !== '') $args['sort']=$key;
    $queryString=http_build_query($args); ?>
<a href="<?= e(url('jobs.php'.($queryString ? '?'.$queryString : ''))) ?>" <?= (($sort==='wage'?'wage':'')===$key)?'aria-current="true"':'' ?>><?= e($label) ?></a>
<?php endforeach; ?></div></nav>
</div>
<?php if(!$jobs && !$error): ?><section class="finder-empty"><?php if (count($where)>1): ?><h3>ยังไม่มีงานที่ตรงกับการค้นหานี้</h3><p>ลองใช้คำค้นที่กว้างขึ้น หรือดูประกาศในพื้นที่อื่น</p><a href="<?= e(url('jobs.php')) ?>">ดูทุกประกาศ</a><?php else: ?><h3>ยังไม่มีประกาศที่เปิดรับ</h3><p>เมื่อมีงานเปิดรับใหม่ คุณจะเห็นตำแหน่งและค่าจ้างที่นี่</p><?php endif; ?></section><?php endif; ?>
<div class="active-job-filters" data-active-job-filters hidden></div>
<div class="grid"><?php foreach($jobs as $job): ?><article class="job-card"><div class="job-card-copy"><span class="status-pill"><?= e(implode(' / ',array_filter([$job['category_name'],$job['subcategory']]))) ?></span><h3><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></h3><p><?= e($job['company_name']) ?></p><p><?= e($job['location']) ?></p><p class="job-meta"><?= $job['hours_per_day'] !== null ? e($job['hours_per_day']).' ชม./วัน · ' : '' ?><?= e($job['work_schedule']) ?></p></div><div class="job-card-actions"><p class="rate">฿<?= e(number_format((float)$job['hourly_wage'],2)) ?><small>/ชม.</small></p><a class="button" href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>">รายละเอียด</a></div></article><?php endforeach; ?></div>
<nav aria-label="แบ่งหน้าผลการค้นหา"><?php foreach([$page-1=>'ก่อนหน้า',$page+1=>'ถัดไป'] as $next=>$label): if($next<1 || $next>ceil($count/$size))continue; $args=array_filter(['category_id'=>$selectedCategories,'subcategory'=>$selectedSubs,'available_day'=>$availableDays]); foreach(['q','location','min_wage','max_wage','min_hours','max_hours','sort','my_skills','available_from','available_to','use_availability'] as $key)$args[$key]=input($key,$_GET); $args['page']=$next; ?><a href="<?= e(url('jobs.php?'.http_build_query($args))) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
</div></div>
<script src="<?= e(url('assets/job-filters.js?v=2')) ?>" defer></script>
<script src="<?= e(url('assets/category-tree.js')) ?>" defer></script>
<?php end_page(); ?>
