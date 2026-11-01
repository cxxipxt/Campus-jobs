<?php
require dirname(__DIR__,2) . '/app/bootstrap.php';
$owner=employer();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try { $id=positive_id(input('job_id')); }
    catch (InvalidArgumentException $ex) { http_response_code(400); exit(e($ex->getMessage())); }
    // Only closing is currently supported; a closed job cannot silently reopen.
    query("UPDATE JOB SET status='Closed' WHERE job_id=? AND employer_id=? AND status='Open'",[$id,$owner]);
    $_SESSION['flash']='ดำเนินการปิดรับสมัครแล้ว'; go('employer/jobs.php');
}
$jobs=query('SELECT j.*, (SELECT COUNT(*) FROM APPLICATION a WHERE a.job_id=j.job_id) AS applicant_count FROM JOB j WHERE j.employer_id=? ORDER BY j.created_at DESC,j.job_id DESC',[$owner])->fetchAll();
start_page('จัดการประกาศงาน');
?>
<p><a class="button" href="<?= e(url('employer/job-form.php')) ?>">สร้างประกาศใหม่</a></p>
<?php if (!$jobs): ?><section><h2>ยังไม่มีประกาศงาน</h2><p>สร้างประกาศแรกเพื่อเริ่มรับสมัครนักศึกษา</p></section><?php endif; ?>
<div class="table-wrap"><table><caption>ประกาศงานของคุณ</caption><thead><tr><th>ตำแหน่ง</th><th>สถานะ</th><th>ผู้สมัคร</th><th>จัดการ</th></tr></thead><tbody>
<?php foreach($jobs as $job): ?><tr>
<td><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></td><td><?= e($job['status']) ?></td><td><?= e($job['applicant_count']) ?> คน</td>
<td><a href="<?= e(url('employer/job-form.php?job_id='.$job['job_id'])) ?>">แก้ไข</a>
<?php if($job['status']==='Open'): ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="job_id" value="<?= e($job['job_id']) ?>"><button>ปิดรับสมัคร</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<p>ปิดรับแทนการลบ เพื่อเก็บประวัติใบสมัครเดิม หน้าพิจารณาผู้สมัครอยู่ในส่วนงานของเตอร์</p>
<?php end_page(); ?>
