<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$id = require_student();
$name = query('SELECT full_name FROM STUDENT WHERE student_id=?', [$id])->fetchColumn();
if ($name === false) { http_response_code(404); exit('ไม่พบโปรไฟล์'); }
$profileSetup = !empty($_SESSION['student_profile_setup']);
$counts = query('SELECT status, COUNT(*) FROM APPLICATION WHERE student_id=? GROUP BY status', [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = query('SELECT a.status,a.applied_at,j.job_title,j.job_id,e.company_name FROM APPLICATION a JOIN JOB j ON j.job_id=a.job_id JOIN EMPLOYER e ON e.employer_id=j.employer_id WHERE a.student_id=? ORDER BY a.applied_at DESC,a.application_id DESC LIMIT 5', [$id])->fetchAll();
$saved = query('SELECT COUNT(*) FROM SAVED_JOB WHERE student_id=?', [$id])->fetchColumn();
$savedJobs = query('SELECT j.job_id,j.job_title,j.location,j.hourly_wage,j.status,e.company_name FROM SAVED_JOB s JOIN JOB j ON j.job_id=s.job_id JOIN EMPLOYER e ON e.employer_id=j.employer_id WHERE s.student_id=? ORDER BY s.saved_at DESC,j.job_id DESC LIMIT 5', [$id])->fetchAll();
start_page('ภาพรวมของฉัน', 'workspace-page student-dashboard');
?>
<p>สวัสดี <?= e($name) ?></p>
<?php if ($profileSetup): ?>
<section class="panel" aria-labelledby="profile-setup-title">
<h2 id="profile-setup-title">ตั้งค่าโปรไฟล์นักศึกษา</h2>
<p>บัญชีมหาวิทยาลัยของคุณยืนยันแล้ว กรอกข้อมูลโปรไฟล์เพื่อเริ่มใช้งาน CampusJobs</p>
<form method="post" action="<?= e(url('student/profile.php')) ?>">
<?php csrf_field(); ?>
<?php field('full_name', 'ชื่อและนามสกุล', (string) $name, 'text', true, 150); ?>
<?php field('major', 'สาขา', '', 'text', false, 150); ?>
<?php field('phone', 'เบอร์โทร', '', 'tel', false, 20); ?>
<?php field('resume_url', 'ลิงก์เรซูเม่', '', 'url', false, 2048); ?>
<p><label for="skills">ทักษะ (หนึ่งทักษะต่อบรรทัด)</label><textarea id="skills" name="skills" maxlength="3050"></textarea></p>
<button type="submit">บันทึกโปรไฟล์</button>
</form>
</section>
<?php endif; ?>
<section aria-labelledby="recent-title">
<h2 id="recent-title">การสมัครล่าสุด</h2>
<?php if (!$recent): ?><p>ยังไม่มีใบสมัคร <a href="<?= e(url('jobs.php')) ?>">ค้นหางาน</a></p><?php endif; ?>
<ul class="dashboard-applications">
<?php foreach ($recent as $row): ?>
<li><div><h3><a href="<?= e(url('job-detail.php?job_id='.$row['job_id'])) ?>"><?= e($row['job_title']) ?></a></h3><p><?= e($row['company_name']) ?> · <?= e(thai_time($row['applied_at'])) ?></p></div><span class="status-pill"><?= e(statuses()[$row['status']]) ?></span></li>
<?php endforeach; ?>
</ul>
<a href="<?= e(url('my-applications.php')) ?>">ดูการสมัครทั้งหมด</a>
</section>
<section><h2>สรุปการสมัคร</h2><p>ทั้งหมด <?= e(array_sum($counts)) ?> ใบสมัคร</p>
<ul><?php foreach (statuses() as $status => $label): ?><li><?= e($label) ?> <?= e($counts[$status] ?? 0) ?></li><?php endforeach; ?></ul>
<p><a href="<?= e(url('saved.php')) ?>">งานที่บันทึกไว้ (<?= e($saved) ?>)</a></p>
</section>
<section class="dashboard-saved" aria-labelledby="saved-jobs-title">
<h2 id="saved-jobs-title">งานที่บันทึกล่าสุด</h2>
<?php if (!$savedJobs): ?><p>ยังไม่มีงานที่บันทึกไว้ <a href="<?= e(url('jobs.php')) ?>">ค้นหางาน</a></p><?php else: ?>
<ul>
<?php foreach ($savedJobs as $job): ?>
<li><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a><br><?= e($job['company_name']) ?> · <?= e($job['location']) ?> · <?= e(number_format((float)$job['hourly_wage'], 2)) ?> บาท/ชั่วโมง · <?= $job['status'] === 'Open' ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร' ?></li>
<?php endforeach; ?>
</ul>
<a href="<?= e(url('saved.php')) ?>">ดูงานที่บันทึกทั้งหมด</a>
<?php endif; ?>
</section>
<?php end_page(); ?>
