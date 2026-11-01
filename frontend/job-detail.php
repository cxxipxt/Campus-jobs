<?php
require dirname(__DIR__) . '/app/bootstrap.php';
try { $id=positive_id(input('job_id',$_GET)); } catch(InvalidArgumentException $ex) { http_response_code(404); start_page('ไม่พบประกาศงาน'); end_page(); exit; }
$job=query('SELECT j.*,e.company_name,e.email,e.phone,e.address,c.category_name FROM JOB j JOIN EMPLOYER e ON e.employer_id=j.employer_id JOIN JOB_CATEGORY c ON c.category_id=j.category_id WHERE j.job_id=?',[$id])->fetch();
if(!$job) { http_response_code(404); start_page('ไม่พบประกาศงาน'); end_page(); exit; }
$skills=query('SELECT skill_name FROM JOB_SKILL WHERE job_id=? ORDER BY skill_name',[$id])->fetchAll(PDO::FETCH_COLUMN);
start_page($job['job_title']);
?>
<p><a href="<?= e(url('jobs.php')) ?>">กลับไปค้นหางาน</a></p><article>
<h2><?= e($job['company_name']) ?></h2><dl>
<?php foreach(['category_name'=>'ประเภทงาน','location'=>'สถานที่','work_schedule'=>'วันและเวลางาน'] as $key=>$label): ?><dt><?= e($label) ?></dt><dd><?= e($job[$key]) ?></dd><?php endforeach; ?>
<dt>ค่าจ้าง</dt><dd><?= e(number_format((float)$job['hourly_wage'],2)) ?> บาท/ชั่วโมง</dd><dt>สถานะ</dt><dd><?= $job['status']==='Open'?'เปิดรับสมัคร':'ปิดรับสมัครแล้ว' ?></dd></dl>
<h2>รายละเอียด</h2><p><?= nl2br(e($job['description'])) ?></p><h2>ทักษะที่ต้องการ</h2>
<?php if(!$skills): ?><p>ไม่ระบุทักษะเพิ่มเติม</p><?php else: ?><ul><?php foreach($skills as $skill): ?><li><?= e($skill) ?></li><?php endforeach; ?></ul><?php endif; ?>
<h2>ติดต่อนายจ้าง</h2><p><?= e($job['email']) ?><?= $job['phone']?' · '.e($job['phone']):'' ?></p><p><?= nl2br(e($job['address']??'')) ?></p>
</article>
<?php if($job['status']!=='Open'): ?><p class="notice">งานนี้ปิดรับสมัครแล้ว ประวัติเดิมยังอยู่</p><?php else: ?>
<p>ระบบสมัครและบันทึกงานกำลังเชื่อมต่อในส่วนของเตอร์</p><button disabled>สมัครงาน</button> <button disabled>บันทึกงาน</button>
<?php endif; ?>
<p><a href="<?= e(url('student/login.html')) ?>">เข้าสู่ระบบนักศึกษาด้วย Microsoft หรือ Google</a> · <a href="<?= e(url('my-applications.php')) ?>">การสมัครของฉัน</a></p>
<?php end_page(); ?>
