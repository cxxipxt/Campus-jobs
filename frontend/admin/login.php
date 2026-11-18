<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
if (identity('admin')) go('admin/verification.php');

$error = null;
$username = getenv('ADMIN_USERNAME');
$passwordHash = getenv('ADMIN_PASSWORD_HASH');
$configured = is_string($username) && $username !== '' && is_string($passwordHash) && $passwordHash !== '';
if (!$configured) {
    http_response_code(503);
    $error = 'ระบบ Admin ยังไม่ได้ตั้งค่า ADMIN_USERNAME และ ADMIN_PASSWORD_HASH';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    check_csrf();
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (hash_equals($username, input('username')) && password_verify($password, $passwordHash)) {
        session_regenerate_id(true);
        $_SESSION = ['auth' => ['role' => 'admin', 'id' => 1]];
        go('admin/verification.php');
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

start_page('เข้าสู่ระบบ Admin', 'company-auth-page', false);
?>
<div class="company-auth">
  <div class="company-auth-card">
    <h1>เข้าสู่ระบบ Admin</h1>
    <?php error_box($error); ?>
    <?php if ($configured): ?>
    <form class="company-auth-fields" method="post">
      <?php csrf_field(); ?>
      <p><label for="username">ชื่อผู้ใช้ Admin</label><input id="username" name="username" autocomplete="username" required></p>
      <p><label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" required></p>
      <button type="submit">เข้าสู่ระบบ</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php end_page(); ?>
