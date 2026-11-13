<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$verified = $_SESSION['verified_student'] ?? null;
if (!$verified || $verified['expires'] < time()) {
    unset($_SESSION['verified_student']);
    go('student/login.html#student');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $name = input('full_name');
        required_text($name, 150, 'ชื่อและนามสกุล');
        try {
            query('INSERT INTO STUDENT (university_username, full_name, email) VALUES (?,?,?)',
                [$verified['username'], $name, $verified['email']]);
        } catch (PDOException $ex) {
            if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) throw $ex;
        }
        $id = query('SELECT student_id FROM STUDENT WHERE university_username=?', [$verified['username']])->fetchColumn();
        if (!$id) throw new InvalidArgumentException('อีเมลนี้ถูกใช้แล้ว');
        sign_in_student((int) $id);
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
}
start_page('ยืนยันโปรไฟล์นักศึกษา', 'workspace-page');
error_box($error);
?>
<p><?= e($verified['email']) ?></p>
<form method="post" class="panel">
  <?php csrf_field(); field('full_name', 'ชื่อและนามสกุล', input('full_name'), 'text', true, 150); ?>
  <button>ยืนยันและสร้างบัญชี</button>
</form>
<?php end_page(); ?>
