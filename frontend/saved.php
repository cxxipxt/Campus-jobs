<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$student = identity('student');

if (!$student) {
    http_response_code(401);
    start_page('งานที่บันทึกไว้');
    echo '<p>กรุณาเข้าสู่ระบบนักศึกษาก่อนดูงานที่บันทึก</p>';
    echo '<a href="' . e(url('student/login.html')) . '">เข้าสู่ระบบนักศึกษา</a>';
    end_page();
    exit;
}

// ยกเลิกการบันทึกงาน
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);

    if ($jobId && $jobId > 0) {
        query(
            'DELETE FROM SAVED_JOB WHERE student_id = ? AND job_id = ?',
            [$student, $jobId]
        );
    }

    go('saved.php?removed=1');
}

// ดึงรายการงานที่นักศึกษาคนนี้บันทึกไว้
$savedJobs = query(
    'SELECT
        s.saved_at,
        j.job_id,
        j.job_title,
        j.hourly_wage,
        j.location,
        j.status AS job_status,
        e.company_name
     FROM SAVED_JOB s
     JOIN JOB j ON j.job_id = s.job_id
     JOIN EMPLOYER e ON e.employer_id = j.employer_id
     WHERE s.student_id = ?
     ORDER BY s.saved_at DESC',
    [$student]
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>งานที่บันทึกไว้ | Campus-jobs</title>
    <link rel="stylesheet" href="<?= e(url('assets/styles/mik.css')) ?>">
</head>
<body>
<a class="skip" href="#main">ข้ามไปเนื้อหาหลัก</a>

<header>
    <a class="brand" href="<?= e(url('dashboard.php')) ?>">Campus-jobs</a>
    <nav aria-label="เมนูนักศึกษา">
        <a href="<?= e(url('dashboard.php')) ?>">ภาพรวม</a>
        <a href="<?= e(url('jobs.php')) ?>">ค้นหางาน</a>
        <a href="<?= e(url('my-applications.php')) ?>">การสมัครของฉัน</a>
        <a href="<?= e(url('saved.php')) ?>" aria-current="page">งานที่บันทึกไว้</a>
        <a href="<?= e(url('student/profile.php')) ?>">โปรไฟล์</a>
        <form method="post" action="<?= e(url('student/logout.php')) ?>">
            <?php csrf_field(); ?>
            <button type="submit">ออกจากระบบ</button>
        </form>
    </nav>
</header>

<main id="main">
    <h1>งานที่บันทึกไว้</h1>

<?php if (isset($_GET['removed'])): ?>
    <p class="notice">ยกเลิกการบันทึกงานเรียบร้อยแล้ว</p>
<?php endif; ?>

<?php if (!$savedJobs): ?>

    <section>
        <h2>ยังไม่มีงานที่บันทึก</h2>
        <p>ลองค้นหางานที่สนใจและบันทึกไว้ได้เลย</p>
        <a href="<?= e(url('jobs.php')) ?>">ค้นหางานเพิ่มเติม</a>
    </section>

<?php else: ?>

    <section>
        <h2>รายการงานของคุณ</h2>

        <?php foreach ($savedJobs as $job): ?>
            <article class="panel">
                <h3>
                    <a href="<?= e(url('job-detail.php?job_id=' . $job['job_id'])) ?>">
                        <?= e($job['job_title']) ?>
                    </a>
                </h3>

                <p>
                    <?= e($job['company_name']) ?>
                    · <?= e($job['location']) ?>
                    · <?= e(number_format((float)$job['hourly_wage'], 2)) ?> บาท/ชั่วโมง
                </p>

                <p>
                    สถานะงาน:
                    <?= $job['job_status'] === 'Open' ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร' ?>
                </p>

                <p>
                    วันที่บันทึก: <?= e(thai_time($job['saved_at'])) ?>
                </p>

                <form method="post" action="<?= e(url('saved.php')) ?>"
                      onsubmit="return confirm('ต้องการยกเลิกการบันทึกงานนี้หรือไม่?')">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="job_id"
                           value="<?= e($job['job_id']) ?>">
                    <button type="submit">ยกเลิกบันทึก</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>

<?php endif; ?>

</main>
<footer>
    <p>Campus-jobs · งานพาร์ทไทม์สำหรับนักศึกษา</p>
</footer>
</body>
</html>
