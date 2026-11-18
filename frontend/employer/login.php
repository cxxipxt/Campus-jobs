<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/login-limit.php';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginPanel = 'employer';
    check_csrf();
    $username = input('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $wait=employer_login_wait($username,$_SERVER['REMOTE_ADDR']??'unknown');
    if ($wait>0) {
        http_response_code(429);
        header('Retry-After: '.$wait);
        $error='ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณาลองอีกครั้งใน '.(int)ceil($wait/60).' นาที';
    } else {
    $account = query('SELECT employer_id,password_hash,company_name FROM EMPLOYER WHERE username = ?', [$username])->fetch();
    if (!$account && filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $account = query('SELECT employer_id,password_hash,company_name FROM EMPLOYER WHERE username IS NULL AND email = ?', [$username])->fetch();
    }
    // A fixed valid dummy hash keeps a missing account on the password verification path.
    $valid = password_verify($password, $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    if (isset($account['password_hash']) && $valid) {
        session_regenerate_id(true);
        $_SESSION = ['auth' => ['role' => 'employer', 'id' => (int)$account['employer_id']], 'flash' => 'เข้าสู่ระบบสำเร็จ'];
        go($account['company_name'] === null ? 'employer/profile.php' : 'employer/jobs.php');
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
start_page('เข้าสู่ระบบ', 'company-auth-page student-login-page', false);
?>
<div class="company-auth" data-login-tabs data-default-panel="<?= e($loginPanel ?? 'employer') ?>">
  <nav class="company-auth-tabs" aria-label="ประเภทบัญชี">
    <a href="#student" aria-controls="student">หางาน</a>
    <a href="#employer" aria-controls="employer">หาคน</a>
  </nav>
  <div class="company-auth-card company-login-card">
    <div id="student" data-login-panel<?= ($loginPanel ?? 'employer') === 'student' ? '' : ' hidden' ?>>
    <h1>เข้าสู่ระบบสำหรับนักศึกษา</h1>
    <p class="company-auth-note">ใช้บัญชีอีเมลนักศึกษา KMUTT</p>
    <?php if (isset($_SESSION['student_login_notice'])): ?>
      <div role="alert" class="student-login-notice">
        <strong>เข้าสู่ระบบนักศึกษายังไม่สำเร็จ</strong>
        <p><?= e($_SESSION['student_login_notice']) ?></p>
      </div>
      <?php unset($_SESSION['student_login_notice']); ?>
    <?php endif; ?>
    <div class="student-providers">
      <a class="provider-button" href="<?= e(url('student/oauth-start.php?provider=microsoft')) ?>">
        <span class="provider-mark microsoft-mark" aria-hidden="true"></span>
        <span>เข้าสู่ระบบด้วย Microsoft <small>@kmutt.ac.th</small></span>
      </a>
      <a class="provider-button" href="<?= e(url('student/oauth-start.php?provider=google')) ?>">
        <span class="provider-mark google-mark" aria-hidden="true">G</span>
        <span>เข้าสู่ระบบด้วย Google <small>@mail.kmutt.ac.th</small></span>
      </a></div>
    </div>
    <div id="employer" data-login-panel<?= ($loginPanel ?? 'employer') === 'student' ? ' hidden' : '' ?>>
    <h1>เข้าสู่ระบบสำหรับบริษัท</h1>
    <?php error_box($error); ?>
    <form class="company-auth-fields" method="post" action="<?= e(url('employer/login.php')) ?>">
      <?php csrf_field(); ?>
      <p><label for="username">ชื่อผู้ใช้</label><input id="username" name="username" autocomplete="username" maxlength="255" value="<?= e(input('username')) ?>" required></p>
      <p><label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" required></p>
      <button type="submit">เข้าสู่ระบบ</button>
    </form>
    <p class="company-auth-note">บัญชีเดิมที่ยังไม่มีชื่อผู้ใช้ ให้กรอกอีเมลบัญชีในช่องชื่อผู้ใช้</p>
    <p class="company-auth-link">ยังไม่มีบัญชี? <a href="<?= e(url('employer/register.php')) ?>">สมัครสมาชิก</a></p>
    </div>
  </div>
</div>
<?php end_page(); ?>
