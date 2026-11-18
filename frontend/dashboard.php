<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/work-skills.php';
$id = require_student();
$student = query('SELECT full_name, email, major FROM STUDENT WHERE student_id = ?', [$id])->fetch();
if (!$student) { http_response_code(404); exit('ไม่พบโปรไฟล์'); }
$setup = !empty($_SESSION['student_profile_setup']);
$counts = query('SELECT status, COUNT(*) FROM APPLICATION WHERE student_id=? GROUP BY status', [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = query('SELECT a.status,a.applied_at,j.job_title,j.job_id,e.company_name FROM APPLICATION a JOIN JOB j ON j.job_id=a.job_id JOIN EMPLOYER e ON e.employer_id=j.employer_id WHERE a.student_id=? ORDER BY a.applied_at DESC,a.application_id DESC LIMIT 5', [$id])->fetchAll();
$savedCount = query('SELECT COUNT(*) FROM SAVED_JOB WHERE student_id=?', [$id])->fetchColumn();
$savedJobs = query('SELECT j.job_id,j.job_title,j.location,j.hourly_wage,j.status FROM SAVED_JOB s JOIN JOB j ON j.job_id=s.job_id WHERE s.student_id=? ORDER BY s.saved_at DESC,j.job_id DESC LIMIT 5', [$id])->fetchAll();
$availability = query('SELECT COUNT(*) FROM STUDENT_AVAILABILITY WHERE student_id=?', [$id])->fetchColumn();
$skillCount = query('SELECT COUNT(*) FROM STUDENT_SKILL WHERE student_id=?', [$id])->fetchColumn();
$matches = $setup ? [] : dashboard_recommendations($id);
start_page('ภาพรวมของฉัน', 'student-hub', false);
?>
<header class="hub-cover">
  <div class="hub-identity">
    <p class="hub-kicker">โปรไฟล์นักศึกษา</p>
    <h1><?= e($student['full_name']) ?></h1>
    <p><?= e($student['email']) ?><?php if ($student['major']): ?> · <?= e($student['major']) ?><?php endif; ?></p>
    <?php if (!$setup): ?><a class="button" href="<?= e(url('student/profile.php')) ?>">แก้ไขโปรไฟล์</a><?php endif; ?>
  </div>
  <img src="<?= e(url('assets/campus-tutoring.jpg')) ?>" alt="" width="540" height="300">
</header>
<?php if ($setup): ?>
<section class="hub-panel">
  <h2>ยืนยันชื่อที่จะใช้สมัครงาน</h2>
  <p>นายจ้างจะเห็นชื่อนี้ในใบสมัคร คุณเพิ่มเวลาว่างและทักษะได้หลังบันทึก</p>
  <form method="post" action="<?= e(url('student/profile.php')) ?>">
    <?php csrf_field(); ?><input type="hidden" name="section" value="basic">
    <?php field('full_name','ชื่อและนามสกุล',$student['full_name'],'text',true,150); ?>
    <button>บันทึกและเริ่มใช้งาน</button>
  </form>
</section>
<?php else: ?>
<nav class="hub-shortcuts" aria-label="ภาพรวมงานของฉัน">
<?php foreach ([
  ['#applications','ใบสมัครของฉัน',array_sum($counts).' ใบสมัคร',(($counts['Pending'] ?? 0) + ($counts['Reviewing'] ?? 0)).' อยู่ระหว่างพิจารณา · '.($counts['Accepted'] ?? 0).' ได้รับเลือก'],
  ['#recommended','งานที่แนะนำ',count($matches).' งานแนะนำ','ดูเหตุผลที่งานเหมาะกับคุณ'],
  ['#saved','งานที่บันทึก',$savedCount.' งาน','กลับไปดูประกาศที่สนใจ'],
  [url('student/profile.php#availability'),'เวลาว่างของฉัน',$availability.' วันต่อสัปดาห์','แก้ไขช่วงเวลาที่สะดวกทำงาน'],
] as [$href,$title,$metric,$caption]): ?>
<a href="<?= e($href) ?>"><span><?= e($title) ?></span><strong><?= e($metric) ?></strong><small><?= e($caption) ?></small></a>
<?php endforeach; ?>
</nav>
<div class="hub-grid">
<section class="hub-panel" id="recommended">
  <div class="hub-title"><h2>งานที่แนะนำ</h2><a href="<?= e(url('jobs.php')) ?>">ค้นหางานทั้งหมด</a></div>
  <?php if (!$matches): ?>
  <p><?= !$skillCount && !$availability ? 'เพิ่มทักษะหรือเวลาว่าง เพื่อดูประกาศที่เหมาะกับคุณ' : 'ยังไม่มีงานเปิดรับที่ตรงกับข้อมูลของคุณ' ?></p>
  <?php endif; ?>
  <?php foreach ($matches as $job): ?>
  <article class="hub-row">
    <h3><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></h3>
    <p>฿<?= e(number_format((float)$job['hourly_wage'], 2)) ?> / ชั่วโมง</p>
    <?php if ($job['matched_count']): ?><p class="hub-reason">ตรง <?= e($job['matched_count']) ?> จาก <?= e($job['total_skills']) ?> ทักษะ · <?= e($job['matched_names']) ?></p><?php endif; ?>
    <?php if ($job['area_match']): ?><p class="hub-reason">อยู่ในพื้นที่ที่คุณสนใจ</p><?php endif; ?>
    <?php if ($job['wage_match']): ?><p class="hub-reason">ถึงค่าจ้างขั้นต่ำที่คุณตั้งไว้</p><?php endif; ?>
    <?php if ($job['shift_match']): ?><p class="hub-reason">กะงานตรงกับเวลาว่าง</p><?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$skillCount): ?><a href="<?= e(url('student/profile.php#skills')) ?>">เพิ่มทักษะที่ใช้ทำงาน</a><?php endif; ?>
  <?php if (!$availability): ?><a href="<?= e(url('student/profile.php#availability')) ?>">เพิ่มเวลาว่างเพื่อดูงานที่ตรงกะ</a><?php endif; ?>
</section>
<section class="hub-panel" id="applications">
  <div class="hub-title"><h2>ใบสมัครล่าสุด</h2><a href="<?= e(url('my-applications.php')) ?>">ดูทั้งหมด</a></div>
  <?php if (!$recent): ?><p>เมื่อคุณสมัครงาน ติดตามผลจากนายจ้างได้ที่นี่</p><?php endif; ?>
  <?php foreach ($recent as $row): ?>
  <article class="hub-row">
    <span class="status-pill" data-status="<?= e($row['status']) ?>"><?= e(statuses()[$row['status']]) ?></span>
    <h3><a href="<?= e(url('job-detail.php?job_id='.$row['job_id'])) ?>"><?= e($row['job_title']) ?></a></h3>
    <p><?= e($row['company_name']) ?> · <?= e(thai_time($row['applied_at'])) ?></p>
  </article>
  <?php endforeach; ?>
</section>
<section class="hub-panel" id="saved">
  <div class="hub-title"><h2>งานที่บันทึกล่าสุด</h2><a href="<?= e(url('saved.php')) ?>">ดูทั้งหมด</a></div>
  <?php if (!$savedJobs): ?><p>กดบันทึกบนหน้ารายละเอียดงานเพื่อกลับมาดูภายหลัง</p><?php endif; ?>
  <?php foreach ($savedJobs as $job): ?>
  <article class="hub-row">
    <h3><a href="<?= e(url('job-detail.php?job_id='.$job['job_id'])) ?>"><?= e($job['job_title']) ?></a></h3>
    <p><?= e($job['location']) ?> · ฿<?= e(number_format((float)$job['hourly_wage'], 2)) ?>/ชม. · <?= $job['status']==='Open'?'เปิดรับสมัคร':'ปิดรับสมัคร' ?></p>
  </article>
  <?php endforeach; ?>
</section>
</div>
<?php endif; end_page(); ?>
