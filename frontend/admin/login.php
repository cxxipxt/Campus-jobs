<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/login-limit.php';

$error = null;
$configuredUsername = getenv('ADMIN_USERNAME');
$configuredHash = getenv('ADMIN_PASSWORD_HASH');
$configured = is_string($configuredUsername) && $configuredUsername !== '' &&
    is_string($configuredHash) && $configuredHash !== '';
if ($configured) $configured = (password_get_info($configuredHash)['algo'] ?? null) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = input('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!$configured) {
        $error = 'ระบบผู้ดูแลยังไม่ได้ตั้งค่า ADMIN_USERNAME หรือ ADMIN_PASSWORD_HASH ที่ถูกต้อง';
    } else {
        $wait = employer_login_wait('admin:' . $username, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($wait > 0) {
            http_response_code(429);
            header('Retry-After: ' . $wait);
            $error = 'ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณาลองอีกครั้งใน ' . (int) ceil($wait / 60) . ' นาที';
        } else {
            $validPassword = password_verify($password, $configuredHash);
            if (hash_equals($configuredUsername, $username) && $validPassword) {
                session_regenerate_id(true);
                $_SESSION = ['auth' => ['role' => 'admin', 'id' => 1]];
                go('admin/verification.php');
            }
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

start_page('เข้าสู่ระบบผู้ดูแล', 'company-auth-page', false);
?>
<div class="company-auth"><div class="company-auth-card">
<h1>เข้าสู่ระบบผู้ดูแล</h1><?php error_box($error); ?>
<form class="company-auth-fields" method="post"><?php csrf_field(); ?>
<p><label for="username">ชื่อผู้ใช้</label><input id="username" name="username" autocomplete="username" maxlength="80" required></p>
<p><label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" required></p>
<button type="submit">เข้าสู่ระบบ</button></form>
</div></div>
<?php end_page(); ?>
