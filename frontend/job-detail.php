<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/work-skills.php';
try { $id=positive_id(input('job_id',$_GET)); } catch(InvalidArgumentException $ex) { http_response_code(404); start_page('ไม่พบประกาศงาน'); end_page(); exit; }
$job=query('SELECT j.*,e.company_name,e.email,e.phone,e.address,c.category_name FROM JOB j JOIN EMPLOYER e ON e.employer_id=j.employer_id JOIN JOB_CATEGORY c ON c.category_id=j.category_id WHERE j.job_id=?',[$id])->fetch();
if(!$job) { http_response_code(404); start_page('ไม่พบประกาศงาน'); end_page(); exit; }
$skills=['required'=>[],'preferred'=>[]];
foreach(query('SELECT skill_type,skill_name FROM JOB_SKILL WHERE job_id=? ORDER BY skill_name',[$id])->fetchAll() as $row) $skills[$row['skill_type']][]=$row['skill_name'];
$shifts=query('SELECT weekday,start_time,end_time FROM JOB_SHIFT WHERE job_id=? ORDER BY weekday',[$id])->fetchAll();
if ($shifts) $job['work_schedule']=job_schedule_summary($shifts,$job['days_per_week']);
$student = identity('student');
$applied = $student && query('SELECT 1 FROM APPLICATION WHERE student_id=? AND job_id=?', [$student,$id])->fetchColumn();
$saved = $student && query('SELECT 1 FROM SAVED_JOB WHERE student_id=? AND job_id=?', [$student,$id])->fetchColumn();
start_page($job['job_title'], 'detail-page');
?>
<p><a href="<?= e(url('jobs.php'.(!empty($_SESSION['job_search'])?'?'.$_SESSION['job_search']:''))) ?>">กลับไปค้นหางาน</a></p><div class="detail-layout"><article>
<h2><?= e($job['company_name']) ?></h2><dl>
<?php foreach(['category_name'=>'ประเภทงาน','location'=>'สถานที่','work_schedule'=>'วันและเวลางาน'] as $key=>$label): ?><dt><?= e($label) ?></dt><dd><?= e($job[$key]) ?><?= $key==='category_name' && !empty($job['subcategory']) ? ' / '.e($job['subcategory']) : '' ?></dd><?php endforeach; ?>
<dt>ค่าจ้าง</dt><dd><?= e(number_format((float)$job['hourly_wage'],2)) ?> บาท/ชั่วโมง</dd><dt>สถานะ</dt><dd><?= $job['status']==='Open'?'เปิดรับสมัคร':'ปิดรับสมัครแล้ว' ?></dd></dl>
<h2>รายละเอียด</h2><p><?= nl2br(e($job['description'])) ?></p>
<?php if($shifts): ?><h2>กะงานรายสัปดาห์</h2><p><?= $job['days_per_week']?'เลือก '.e($job['days_per_week']).' วันต่อสัปดาห์':'ทำทุกวันที่ระบุ' ?></p><ul><?php foreach($shifts as $shift): ?><li><?=e(work_weekdays()[$shift['weekday']])?> <?=e(substr($shift['start_time'],0,5))?>–<?=e(sprintf('%02d%s',(int)substr($shift['end_time'],0,2)%24,substr($shift['end_time'],2,3))).((int)substr($shift['end_time'],0,2)>=24?' (วันถัดไป)':'')?></li><?php endforeach; ?></ul><?php endif; ?>
<?php foreach(['required'=>'ทักษะจำเป็น','preferred'=>'ทักษะเสริม'] as $type=>$label): ?><h2><?=e($label)?></h2><?php if(!$skills[$type]): ?><p>ไม่ระบุ</p><?php else: ?><ul><?php foreach($skills[$type] as $skill): ?><li><?= e($skill) ?></li><?php endforeach; ?></ul><?php endif; endforeach; ?>
<h2>ติดต่อนายจ้าง</h2><p><?= e($job['email']) ?><?= $job['phone']?' · '.e($job['phone']):'' ?></p><p><?= nl2br(e($job['address']??'')) ?></p>
</article>
<section class="detail-action" aria-label="การสมัครงาน"><p class="rate">฿<?= e(number_format((float)$job['hourly_wage'],2)) ?><small>/ชม.</small></p><p class="muted"><?= e($job['work_schedule']) ?></p><h2>สนใจงานนี้</h2>
<?php if ($job['status'] !== 'Open'): ?><p>งานนี้ปิดรับสมัครแล้ว</p><?php endif; ?>
<?php if ($applied): ?><p><a href="<?= e(url('my-applications.php')) ?>">ดูสถานะใบสมัครของคุณ</a></p><?php endif; ?>
<?php if ($student): ?>
<?php if (!$applied && $job['status'] === 'Open'): ?>
<form method="post" action="<?= e(url('student/job-action.php')) ?>">
  <?php csrf_field(); ?><input type="hidden" name="job_id" value="<?= e($id) ?>">
  <button name="action" value="apply">สมัครงาน</button>
</form>
<?php endif; ?>
<?php if ($saved): ?>
<form method="post" action="<?= e(url('student/job-action.php')) ?>">
  <?php csrf_field(); ?><input type="hidden" name="job_id" value="<?= e($id) ?>">
  <button class="button--outline" name="action" value="unsave">ยกเลิกบันทึก</button>
</form>
<?php elseif ($job['status'] === 'Open'): ?>
<form method="post" action="<?= e(url('student/job-action.php')) ?>">
  <?php csrf_field(); ?><input type="hidden" name="job_id" value="<?= e($id) ?>">
  <button class="button--outline" name="action" value="save">บันทึกงาน</button>
</form>
<?php endif; ?>
<?php elseif ($job['status'] === 'Open'): ?>
<p><a class="button" href="<?= e(url('student/login.php?job_id='.$id)) ?>">เข้าสู่ระบบนักศึกษา</a></p>
<?php endif; ?>
</section></div>
<?php end_page(); ?>
