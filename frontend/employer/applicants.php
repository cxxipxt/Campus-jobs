<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$owner = employer();
try { $id = positive_id(input('job_id', $_GET)); }
catch (InvalidArgumentException $ex) { http_response_code(404); exit('ไม่พบประกาศงาน'); }
$job = owned_job($id, $owner);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try { $application = positive_id(input('application_id')); }
    catch (InvalidArgumentException $ex) { http_response_code(400); exit('รหัสใบสมัครไม่ถูกต้อง'); }
    $status = input('status');
    if (!isset(statuses()[$status])) { http_response_code(400); exit('สถานะไม่ถูกต้อง'); }
    db()->beginTransaction();
    try {
        $authorizedApplication = query(
            'SELECT a.application_id
             FROM APPLICATION a
             JOIN JOB j ON j.job_id = a.job_id
             WHERE a.application_id = ? AND a.job_id = ? AND j.employer_id = ?
             FOR UPDATE',
            [$application, $id, $owner]
        )->fetchColumn();

        if ($authorizedApplication === false) {
            db()->rollBack();
            http_response_code(404);
            exit('ไม่พบใบสมัคร');
        }

        query(
            'UPDATE APPLICATION a
             JOIN JOB j ON j.job_id = a.job_id
             SET a.status = ?
             WHERE a.application_id = ? AND a.job_id = ? AND j.employer_id = ?',
            [$status, $application, $id, $owner]
        );
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $ex;
    }
    $_SESSION['flash'] = 'บันทึกสถานะใบสมัครแล้ว';
    go('employer/applicants.php?job_id='.$id);
}
$applicants = query('SELECT a.application_id,a.status,a.applied_at,s.student_id,s.full_name,s.email,s.major,s.resume_url FROM APPLICATION a JOIN STUDENT s ON s.student_id=a.student_id WHERE a.job_id=? ORDER BY a.applied_at DESC,a.application_id DESC', [$id])->fetchAll();
start_page('ผู้สมัครงาน', 'workspace-page');
?>
<p><a href="<?= e(url('employer/jobs.php')) ?>">กลับไปจัดการประกาศ</a></p>
<h2><?= e($job['job_title']) ?></h2>
<?php if (!$applicants): ?><p>ยังไม่มีผู้สมัครงานนี้</p><?php endif; ?>
<?php foreach ($applicants as $applicant): ?>
<article>
<h3><?= e($applicant['full_name']) ?></h3>
<p><?= e($applicant['email']) ?> · <?= e($applicant['major'] ?? 'ไม่ระบุสาขา') ?></p>
<?php $skills = query('SELECT skill_name FROM STUDENT_SKILL WHERE student_id=? ORDER BY skill_name', [$applicant['student_id']])->fetchAll(PDO::FETCH_COLUMN); ?>
<?php if ($skills): ?><p>ทักษะ <?= e(implode(', ', $skills)) ?></p><?php endif; ?>
<?php if (preg_match('~^https?://~', $applicant['resume_url'] ?? '')): ?><p><a href="<?= e($applicant['resume_url']) ?>">ดูเรซูเม่</a></p><?php endif; ?>
<form method="post">
<?php csrf_field(); ?><input type="hidden" name="application_id" value="<?= e($applicant['application_id']) ?>">
<p><label for="status-<?= e($applicant['application_id']) ?>">สถานะใบสมัคร</label><select id="status-<?= e($applicant['application_id']) ?>" name="status">
<?php foreach (statuses() as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === $applicant['status'] ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
</select></p><button>บันทึกสถานะ</button>
</form>
</article>
<?php endforeach; ?>
<?php end_page(); ?>
