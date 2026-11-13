<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$id = require_student();

// Get student profile.
$profile = query(
    'SELECT *
     FROM STUDENT
     WHERE student_id = ?',
    [$id]
)->fetch();

if (!$profile) {
    http_response_code(404);
    exit('ไม่พบโปรไฟล์');
}

// Get linked OAuth providers.
$linkedProviders = query(
    'SELECT provider
     FROM STUDENT_OAUTH
     WHERE student_id = ?',
    [$id]
)->fetchAll(PDO::FETCH_COLUMN);

// Get student skills.
$skills = implode(
    "\n",
    query(
        'SELECT skill_name
         FROM STUDENT_SKILL
         WHERE student_id = ?
         ORDER BY skill_name',
        [$id]
    )->fetchAll(PDO::FETCH_COLUMN)
);

$error = null;

// Handle profile updates.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();

    $skills = input('skills');

    try {
        // Validate profile information.
        $name = input('full_name');
        required_text($name, 150, 'ชื่อและนามสกุล');

        $major = optional_text(input('major'), 150, 'สาข');
        $phone = optional_text(input('phone'), 20, 'เบอร์โทร');
        $resume = student_resume_link(input('resume_url'));

        // Split skills by line.
        $lines = array_unique(
            array_filter(
                array_map(
                    'trim',
                    preg_split('/\R/u', $skills) ?: []
                ),
                static fn($line) => $line !== ''
            )
        );

        if (count($lines) > 30) {
            throw new InvalidArgumentException(
                'เพิ่มได้สูงสุด 30 ทักษะ'
            );
        }

        foreach ($lines as $line) {
            required_text($line, 100, 'ทักษะ');
        }

        // Save profile and skills in one transaction.
        db()->beginTransaction();

        try {
            query(
                'SELECT student_id
                 FROM STUDENT
                 WHERE student_id = ?
                 FOR UPDATE',
                [$id]
            );

            query(
                'UPDATE STUDENT
                 SET full_name = ?,
                     major = ?,
                     phone = ?,
                     resume_url = ?
                 WHERE student_id = ?',
                [$name, $major, $phone, $resume, $id]
            );

            query(
                'DELETE FROM STUDENT_SKILL
                 WHERE student_id = ?',
                [$id]
            );

            foreach ($lines as $line) {
                query(
                    'INSERT INTO STUDENT_SKILL
                     (student_id, skill_name)
                     VALUES (?, ?)',
                    [$id, $line]
                );
            }

            db()->commit();

        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            throw $ex;
        }

        $_SESSION['flash'] = 'บันทึกโปรไฟล์แล้ว';
        go('student/profile.php');

    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();

    } catch (Throwable $ex) {
        $error = 'ไม่สามารถบันทึกโปรไฟล์ได้ กรุณาลองใหม่อีกครั้ง';
    }
}

start_page('โปรไฟล์นักศึกษา', 'workspace-page');

error_box($error);

// Keep submitted values when validation fails.
$value = static fn($key) =>
    $error ? input($key) : ($profile[$key] ?? '');

?>

<p><?= e($profile['email']) ?></p>

<section class="panel">
    <h2>การเชื่อมต่อบัญชี</h2>

    <p>
        คุณสามารถเชื่อมบัญชีมหาวิทยาลัยผ่าน Microsoft
        และ Google เพื่อใช้เข้าสู่ระบบได้
    </p>

    <div>
        <h3>Microsoft</h3>

        <?php if (in_array('microsoft', $linkedProviders, true)): ?>
            <p>สถานะ: เชื่อมต่อแล้ว</p>
        <?php else: ?>
            <p>สถานะ: ยังไม่ได้เชื่อมต่อ</p>

            <form
                action="<?= e(url('student/oauth-start.php')) ?>"
                method="post"
            >
                <?php csrf_field(); ?>

                <input type="hidden" name="action" value="link">
                <input type="hidden" name="provider" value="microsoft">

                <button type="submit">เชื่อมบัญชี Microsoft</button>
            </form>
        <?php endif; ?>
    </div>

    <div>
        <h3>Google</h3>

        <?php if (in_array('google', $linkedProviders, true)): ?>
            <p>สถานะ: เชื่อมต่อแล้ว</p>
        <?php else: ?>
            <p>สถานะ: ยังไม่ได้เชื่อมต่อ</p>

            <form
                action="<?= e(url('student/oauth-start.php')) ?>"
                method="post"
            >
                <?php csrf_field(); ?>

                <input type="hidden" name="action" value="link">
                <input type="hidden" name="provider" value="google">

                <button type="submit">เชื่อมบัญชี Google</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<h2>ข้อมูลส่วนตัว</h2>

<form method="post" class="panel">
    <?php
    csrf_field();

    field(
        'full_name',
        'ชื่อและนามสกุล',
        $value('full_name'),
        'text',
        true,
        150
    );

    field(
        'major',
        'สาขา',
        $value('major'),
        'text',
        false,
        150
    );

    field(
        'phone',
        'เบอร์โทร',
        $value('phone'),
        'tel',
        false,
        20
    );

    field(
        'resume_url',
        'ลิงก์เรซูเม่',
        $value('resume_url'),
        'url',
        false,
        2048
    );
    ?>

    <p>
        <label for="skills">ทักษะ (หนึ่งทักษะต่อบรรทัด)</label>

        <textarea
            id="skills"
            name="skills"
            maxlength="3050"
        ><?= e($skills) ?></textarea>
    </p>

    <button type="submit">บันทึกโปรไฟล์</button>
</form>

<?php end_page(); ?>
