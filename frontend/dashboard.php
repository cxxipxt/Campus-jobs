<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$id = require_student();
$name = query('SELECT full_name FROM STUDENT WHERE student_id=?', [$id])->fetchColumn();
if ($name === false) { http_response_code(404); exit('ไม่พบโปรไฟล์'); }
$counts = query('SELECT status, COUNT(*) FROM APPLICATION WHERE student_id=? GROUP BY status', [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = query('SELECT a.status,a.applied_at,j.job_title,j.job_id,e.company_name FROM APPLICATION a JOIN JOB j ON j.job_id=a.job_id JOIN EMPLOYER e ON e.employer_id=j.employer_id WHERE a.student_id=? ORDER BY a.applied_at DESC,a.application_id DESC LIMIT 5', [$id])->fetchAll();
$saved = query('SELECT COUNT(*) FROM SAVED_JOB WHERE student_id=?', [$id])->fetchColumn();
start_page('ภาพรวมของฉัน', 'workspace-page student-dashboard');
?>
<p>สวัสดี <?= e($name) ?></p>
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
<?php end_page(); ?>
