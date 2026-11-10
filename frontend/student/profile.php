<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$id = student();
$error = null;

// ดึงข้อมูลนักศึกษาจากฐานข้อมูล
$profile = query(
    'SELECT university_username, full_name, email, phone, major, resume_url
     FROM STUDENT
     WHERE student_id = ?',
    [$id]
)->fetch();

if (!$profile) {
    http_response_code(401);
    exit('ไม่พบบัญชีนักศึกษา');
}

// เมื่อกดบันทึกโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    try {
        $fullName = input('full_name');
        $phone = optional_text(input('phone'), 20, 'เบอร์โทร');
        $major = optional_text(input('major'), 150, 'สาขา');
        $resumeUrl = optional_text(input('resume_url'), 2048, 'ลิงก์ Resume');

        required_text($fullName, 150, 'ชื่อ–นามสกุล');

        if ($resumeUrl !== null) {
            if (
                !filter_var($resumeUrl, FILTER_VALIDATE_URL) ||
                !in_array(
                    strtolower((string) parse_url($resumeUrl, PHP_URL_SCHEME)),
                    ['http', 'https'],
                    true
                )
            ) {
                throw new InvalidArgumentException('กรุณากรอกลิงก์ Resume ให้ถูกต้อง');
            }
        }

        query(
            'UPDATE STUDENT
             SET full_name = ?, phone = ?, major = ?, resume_url = ?
             WHERE student_id = ?',
            [$fullName, $phone, $major, $resumeUrl, $id]
        );

        $_SESSION['flash'] = 'บันทึกข้อมูลโปรไฟล์แล้ว';
        go('student/profile.php');

    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
    }
}

start_page('โปรไฟล์นักศึกษา');
error_box($error);
?>

<p>รหัสนักศึกษาในระบบ: <?= e($profile['university_username']) ?></p>
<p>อีเมลบัญชี: <?= e($profile['email']) ?></p>

<form method="post" class="panel">
    <?php csrf_field(); ?>

    <?php
    field(
        'full_name',
        'ชื่อ–นามสกุล',
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? input('full_name')
            : $profile['full_name'],
        'text',
        true,
        150
    );

    field(
        'phone',
        'เบอร์โทร (ไม่บังคับ)',
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? input('phone')
            : ($profile['phone'] ?? ''),
        'tel',
        false,
        20
    );

    field(
        'major',
        'สาขา (ไม่บังคับ)',
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? input('major')
            : ($profile['major'] ?? ''),
        'text',
        false,
        150
    );

    field(
        'resume_url',
        'ลิงก์ Resume (ไม่บังคับ)',
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? input('resume_url')
            : ($profile['resume_url'] ?? ''),
        'url',
        false,
        2048
    );
    ?>

    <button type="submit">บันทึกโปรไฟล์</button>
</form>

<?php end_page(); ?>
