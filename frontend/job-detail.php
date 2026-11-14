<?php
require dirname(__DIR__) . '/app/bootstrap.php';
try { $id=positive_id(input('job_id',$_GET)); } catch(InvalidArgumentException $ex) { http_response_code(404); start_page('ไม่พบประกาศงาน'); end_page(); exit; }
$job=query('SELECT j.*,e.company_name,e.email,e.phone,e.address,c.category_name FROM JOB j JOIN EMPLOYER e ON e.employer_id=j.employer_id JOIN JOB_CATEGORY c ON c.category_id=j.category_id WHERE j.job_id=?',[$id])->fetch();
if(!$job) { http_response_code(404); start_page('ไม่พบประกาศงาน'); end_page(); exit; }
$skills=query('SELECT skill_name FROM JOB_SKILL WHERE job_id=? ORDER BY skill_name',[$id])->fetchAll(PDO::FETCH_COLUMN);
$student = identity('student');
$applied = $student && query('SELECT 1 FROM APPLICATION WHERE student_id=? AND job_id=?', [$student,$id])->fetchColumn();
$saved = $student && query('SELECT 1 FROM SAVED_JOB WHERE student_id=? AND job_id=?', [$student,$id])->fetchColumn();
start_page($job['job_title'], 'detail-page');
?>
<p><a href="<?= e(url('jobs.php')) ?>">กลับไปค้นหางาน</a></p><div class="detail-layout"><article>
<h2><?= e($job['company_name']) ?></h2><dl>
<?php foreach(['category_name'=>'ประเภทงาน','location'=>'สถานที่','work_schedule'=>'วันและเวลางาน'] as $key=>$label): ?><dt><?= e($label) ?></dt><dd><?= e($job[$key]) ?></dd><?php endforeach; ?>
<dt>ค่าจ้าง</dt><dd><?= e(number_format((float)$job['hourly_wage'],2)) ?> บาท/ชั่วโมง</dd><dt>สถานะ</dt><dd><?= $job['status']==='Open'?'เปิดรับสมัคร':'ปิดรับสมัครแล้ว' ?></dd></dl>
<h2>รายละเอียด</h2><p><?= nl2br(e($job['description'])) ?></p><h2>ทักษะที่ต้องการ</h2>
<?php if(!$skills): ?><p>ไม่ระบุทักษะเพิ่มเติม</p><?php else: ?><ul><?php foreach($skills as $skill): ?><li><?= e($skill) ?></li><?php endforeach; ?></ul><?php endif; ?>
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
<p><a class="button" href="<?= e(url('student/login.html#student')) ?>">เข้าสู่ระบบนักศึกษา</a></p>
<?php endif; ?>
</section></div>
<?php end_page(); ?>
