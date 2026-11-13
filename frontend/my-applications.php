<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$student=identity('student');
if(!$student) { http_response_code(401); start_page('การสมัครของฉัน', 'workspace-page applications-page'); echo '<p>กรุณาเข้าสู่ระบบนักศึกษาก่อนดูประวัติ</p><a href="'.e(url('student/login.html')).'">เข้าสู่ระบบนักศึกษา</a>'; end_page(); exit; }
$page=max(1,min(100000,(int)input('page',$_GET))); $size=20;
$count=(int)query('SELECT COUNT(*) FROM APPLICATION WHERE student_id=?',[$student])->fetchColumn();
$page=min($page,max(1,(int)ceil($count/$size))); $offset=($page-1)*$size;
$applications=query('SELECT a.applied_at,a.status,j.job_id,j.job_title,j.status AS job_status,e.company_name FROM APPLICATION a JOIN JOB j ON j.job_id=a.job_id JOIN EMPLOYER e ON e.employer_id=j.employer_id WHERE a.student_id=? ORDER BY a.applied_at DESC,a.application_id DESC LIMIT '.$size.' OFFSET '.$offset,[$student])->fetchAll();
start_page('การสมัครของฉัน', 'workspace-page applications-page');
if(!$applications) echo '<p>ยังไม่ได้สมัครงาน <a href="'.e(url('jobs.php')).'">ค้นหางาน</a></p>';
?>
<div class="table-wrap"><table><caption>ใบสมัครของคุณ — เวลาไทย</caption><thead><tr><th>ตำแหน่ง</th><th>นายจ้าง</th><th>วันที่สมัคร</th><th>ผลการสมัคร</th><th>สถานะงาน</th></tr></thead><tbody>
<?php foreach($applications as $row): ?><tr><td data-label="ตำแหน่ง"><a href="<?= e(url('job-detail.php?job_id='.$row['job_id'])) ?>"><?= e($row['job_title']) ?></a></td><td data-label="นายจ้าง"><?= e($row['company_name']) ?></td><td data-label="วันที่สมัคร"><?= e(thai_time($row['applied_at'])) ?></td><td data-label="ผลการสมัคร"><span class="status-pill"><?= e(statuses()[$row['status']]??$row['status']) ?></span></td><td data-label="สถานะงาน"><?= $row['job_status']==='Open'?'เปิดรับสมัคร':'ปิดรับสมัคร' ?></td></tr><?php endforeach; ?></tbody></table></div>
<nav aria-label="แบ่งหน้าประวัติ"><?php if($page>1): ?><a href="?page=<?= $page-1 ?>">ก่อนหน้า</a><?php endif; ?><?php if($page*$size<$count): ?><a href="?page=<?= $page+1 ?>">ถัดไป</a><?php endif; ?></nav>
<section class="career-note"><div><h2>เตรียมตัวก่อนสัมภาษณ์</h2><p>ทบทวนรายละเอียดงาน เตรียมตัวอย่างจากประสบการณ์ของคุณ และเช็กวันเวลากับนายจ้างล่วงหน้า</p></div></section>
<?php end_page(); ?>
