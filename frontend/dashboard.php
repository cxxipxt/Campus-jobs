<?php
require dirname(__DIR__) . '/app/bootstrap.php';

// ตรวจสอบว่าผู้ใช้เป็นนักศึกษาที่ Login แล้ว
$id = student();

// ดึงข้อมูลโปรไฟล์นักศึกษา
$profile = query(
    'SELECT full_name, email, major
     FROM STUDENT
     WHERE student_id = ?',
    [$id]
)->fetch();

if (!$profile) {
    http_response_code(404);
    exit('ไม่พบบัญชีนักศึกษา');
}

// นับจำนวนการสมัครงานและแยกตามสถานะ
$summary = query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'Accepted'), 0) AS accepted
     FROM APPLICATION
     WHERE student_id = ?",
    [$id]
)->fetch();

// ดึงการสมัครงานล่าสุด 3 รายการ
$recentApplications = query(
    'SELECT
        a.application_id,
        a.applied_at,
        a.status,
        j.job_id,
        j.job_title,
        e.company_name
     FROM APPLICATION a
     JOIN JOB j ON a.job_id = j.job_id
     JOIN EMPLOYER e ON j.employer_id = e.employer_id
     WHERE a.student_id = ?
     ORDER BY a.applied_at DESC, a.application_id DESC
     LIMIT 3',
    [$id]
)->fetchAll();

// ดึงงานที่บันทึกไว้ล่าสุด 3 รายการ
$savedJobs = query(
    'SELECT
        s.saved_at,
        j.job_id,
        j.job_title,
        j.hourly_wage,
        j.location,
        e.company_name
     FROM SAVED_JOB s
     JOIN JOB j ON s.job_id = j.job_id
     JOIN EMPLOYER e ON j.employer_id = e.employer_id
     WHERE s.student_id = ?
     ORDER BY s.saved_at DESC
     LIMIT 3',
    [$id]
)->fetchAll();

// แปลงสถานะเป็นภาษาไทย
$statusLabels = statuses();


?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ภาพรวม | Campus-jobs</title>
    <link rel="stylesheet" href="<?= e(url('assets/styles/mik.css')) ?>">
</head>
<body>
<a class="skip" href="#main">ข้ามไปเนื้อหาหลัก</a>

<header>
    <a class="brand" href="<?= e(url('dashboard.php')) ?>">Campus-jobs</a>

    <nav aria-label="เมนูนักศึกษา">
        <a href="<?= e(url('dashboard.php')) ?>" aria-current="page">ภาพรวม</a>
        <a href="<?= e(url('jobs.php')) ?>">ค้นหางาน</a>
        <a href="<?= e(url('my-applications.php')) ?>">การสมัครของฉัน</a>
        <a href="<?= e(url('saved.php')) ?>">งานที่บันทึกไว้</a>
        <a href="<?= e(url('student/profile.php')) ?>">โปรไฟล์</a>

        <form method="post" action="<?= e(url('student/logout.php')) ?>">
            <?php csrf_field(); ?>
            <button type="submit">ออกจากระบบ</button>
        </form>
    </nav>
</header>

<main id="main">
    <h1>ภาพรวม</h1>
<?php

?>

<p>สวัสดี, <?= e($profile['full_name']) ?></p>
<p class="muted">นี่คือภาพรวมกิจกรรมการสมัครงานของคุณ</p>

<div class="grid">

    <!-- เมนูบัญชีนักศึกษา -->
    <article>
        <h2>ข้อมูลนักศึกษา</h2>

        <h3><?= e($profile['full_name']) ?></h3>

        <p><?= e($profile['major'] ?: 'ยังไม่ได้ระบุสาขา') ?></p>
        <p><?= e($profile['email']) ?></p>

        <p>
            <a href="<?= e(url('student/profile.php')) ?>">
                แก้ไขโปรไฟล์
            </a>
        </p>
        <nav aria-label="เมนูบัญชี">
            <a href="<?= e(url('dashboard.php')) ?>">ภาพรวม</a>
            <a href="<?= e(url('my-applications.php')) ?>">การสมัครของฉัน</a>
            <a href="<?= e(url('saved.php')) ?>">งานที่บันทึกไว้</a>
            <a href="<?= e(url('student/profile.php')) ?>">โปรไฟล์</a>
        </nav>
    </article>

    <!-- สรุปการสมัคร -->
    <article>
        <h2>สรุปการสมัคร</h2>

        <dl>
            <dt>สมัครแล้วทั้งหมด</dt>
            <dd><?= (int) $summary['total'] ?> รายการ</dd>

            <dt>รอพิจารณา</dt>
            <dd><?= (int) $summary['pending'] ?> รายการ</dd>

            <dt>ผ่านการคัดเลือก</dt>
            <dd><?= (int) $summary['accepted'] ?> รายการ</dd>
        </dl>
    </article>

</div>

<!-- การสมัครล่าสุด -->
<section aria-labelledby="recent-title">
    <h2 id="recent-title">การสมัครล่าสุด</h2>

    <?php if (!$recentApplications): ?>
        <p class="muted">คุณยังไม่มีประวัติการสมัครงาน</p>
    <?php else: ?>

        <div class="table-wrap">
            <table>
                <caption>ใบสมัคร 3 รายการล่าสุดของคุณ</caption>

                <thead>
                    <tr>
                        <th scope="col">ตำแหน่งงาน</th>
                        <th scope="col">นายจ้าง</th>
                        <th scope="col">วันที่สมัคร</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($recentApplications as $application): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('job-detail.php?job_id=' . (int) $application['job_id'])) ?>">
                                    <?= e($application['job_title']) ?>
                                </a>
                            </td>

                            <td><?= e($application['company_name']) ?></td>

                            <td>
                                <?= e(thai_time($application['applied_at'])) ?>
                            </td>

                            <td>
                                <?= e($statusLabels[$application['status']] ?? $application['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

    <p>
        <a href="<?= e(url('my-applications.php')) ?>">
            ดูการสมัครทั้งหมด
        </a>
    </p>
</section>

<!-- งานที่บันทึกไว้ -->
<section aria-labelledby="saved-title">
    <h2 id="saved-title">งานที่บันทึกไว้</h2>

    <?php if (!$savedJobs): ?>
        <p class="muted">คุณยังไม่มีงานที่บันทึกไว้</p>
    <?php else: ?>

        <ul>
            <?php foreach ($savedJobs as $job): ?>
                <li>
                    <a href="<?= e(url('job-detail.php?job_id=' . (int) $job['job_id'])) ?>">
                        <?= e($job['job_title']) ?>
                    </a>

                    <p>
                        <?= e($job['company_name']) ?>
                        · <?= number_format((float) $job['hourly_wage'], 2) ?> บาท/ชั่วโมง
                    </p>

                    <p class="muted"><?= e($job['location']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

    <p>
        <a href="<?= e(url('saved.php')) ?>">
            ดูงานที่บันทึกทั้งหมด
        </a>
    </p>
</section>

</main>

<footer>
    <p>Campus-jobs · งานพาร์ทไทม์สำหรับนักศึกษา</p>
</footer>

</body>
</html>
