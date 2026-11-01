<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = input('email');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $account = query('SELECT employer_id,password_hash FROM EMPLOYER WHERE email = ?', [$email])->fetch();
    // A fixed valid dummy hash keeps a missing account on the password verification path.
    $valid = password_verify($password, $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    if ($account && $valid) {
        session_regenerate_id(true);
        $_SESSION = ['auth' => ['role' => 'employer', 'id' => (int)$account['employer_id']], 'flash' => 'เข้าสู่ระบบสำเร็จ'];
        go('employer/jobs.php');
    }
    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}
start_page('เข้าสู่ระบบนายจ้าง'); error_box($error);
?>
<form class="panel" method="post"><?php csrf_field(); field('email','อีเมล',input('email'),'email'); ?>
<p><label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" required></p>
<button>เข้าสู่ระบบ</button></form>
<p><a href="<?= e(url('employer/register.php')) ?>">สมัครสมาชิกนายจ้าง</a></p>
<?php end_page(); ?>
