<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$id = require_student();
$jobs = query('SELECT j.job_id,j.job_title,j.location,j.hourly_wage,j.status FROM SAVED_JOB s JOIN JOB j ON j.job_id=s.job_id WHERE s.student_id=? ORDER BY s.saved_at DESC,j.job_id DESC', [$id])->fetchAll();
start_page('งานที่บันทึกไว้', 'workspace-page');
?>
<?php if (!$jobs): ?><p>ยังไม่มีงานที่บันทึก <a href="<?= e(url('jobs.php')) ?>">ค้นหางาน</a></p><?php endif; ?>
<div class="card-grid">
<?php foreach ($jobs as $job): ?>
<article>
  <h2><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></h2>
  <p><?= e($job['location']) ?> · <?= e($job['hourly_wage']) ?> บาท/ชั่วโมง</p>
  <p><?= $job['status'] === 'Open' ? 'เปิดรับสมัคร' : 'ปิดรับสมัครแล้ว' ?></p>
  <form method="post" action="<?= e(url('student/job-action.php')) ?>">
    <?php csrf_field(); ?><input type="hidden" name="job_id" value="<?= e($job['job_id']) ?>">
    <input type="hidden" name="return" value="saved"><button name="action" value="unsave">ยกเลิกบันทึก</button>
  </form>
</article>
<?php endforeach; ?>
</div>
<?php end_page(); ?>
