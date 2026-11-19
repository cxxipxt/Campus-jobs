<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $username = input('username');
        required_text($username, 80, 'ชื่อผู้ใช้');
        $email = input('email'); required_text($email, 255, 'อีเมล');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('อีเมลไม่ถูกต้อง');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (str_contains($password, "\0") || strlen($password) < 12 || strlen($password) > 72) throw new InvalidArgumentException('รหัสผ่านต้องยาว 12–72 ไบต์ และไม่มีอักขระว่างพิเศษ');
        if ($password !== ($_POST['password_confirmation'] ?? null)) throw new InvalidArgumentException('รหัสผ่านไม่ตรงกัน');
        query(
            "INSERT INTO EMPLOYER (username,email,password_hash,verification_status) VALUES (?,?,?,'Draft')",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );
        $id = (int) db()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION = [
            'auth' => ['role' => 'employer', 'id' => $id],
            'flash' => 'สมัครสมาชิกสำเร็จ กรุณากรอกข้อมูลบริษัทและข้อมูลติดต่อเพื่อส่งให้ Admin ตรวจสอบ',
        ];
        go('employer/profile.php');
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
      catch (PDOException $ex) { if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) throw $ex; $error = 'ชื่อผู้ใช้หรืออีเมลบัญชีนี้ถูกใช้แล้ว'; }
}
start_page('สมัครสมาชิกสำหรับบริษัท', 'company-auth-page', false);
?>
<div class="company-auth">

  <div class="company-auth-card">
    <h1>สมัครสมาชิกสำหรับบริษัท</h1>
    <?php error_box($error); ?>
    <p class="company-auth-note">สร้างบัญชีก่อน แล้วกรอกข้อมูลบริษัทและข้อมูลติดต่อในหน้าโปรไฟล์เพื่อส่งให้ Admin ตรวจสอบ</p>
    <form class="company-auth-fields" method="post" action="<?= e(url('employer/register.php')) ?>">
      <?php csrf_field(); ?>
      <p><label for="username">ชื่อผู้ใช้</label><input id="username" type="text" name="username" autocomplete="username" maxlength="80" value="<?= e(input('username')) ?>" required></p>
      <p><label for="password">รหัสผ่าน</label><input id="password" type="password" name="password" autocomplete="new-password" required></p>
      <p><label for="password_confirmation">ยืนยันรหัสผ่าน</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></p>
      <p><label for="email">อีเมลสำหรับบัญชี</label><input id="email" type="email" name="email" autocomplete="email" maxlength="255" value="<?= e(input('email')) ?>" required></p>
      <button type="submit">สมัครสมาชิก</button>
    </form>
    <p class="company-auth-link">เป็นสมาชิกอยู่แล้ว? <a href="<?= e(url('employer/login.php')) ?>">เข้าสู่ระบบ</a></p>
  </div>
</div>
<?php end_page(); ?>
