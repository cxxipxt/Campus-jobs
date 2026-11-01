<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $company = input('company_name'); required_text($company, 150, 'ชื่อบริษัท');
        $email = input('email'); required_text($email, 255, 'อีเมล');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('อีเมลไม่ถูกต้อง');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (str_contains($password, "\0") || strlen($password) < 12 || strlen($password) > 72) throw new InvalidArgumentException('รหัสผ่านต้องยาว 12–72 ไบต์ และไม่มีอักขระว่างพิเศษ');
        if ($password !== ($_POST['password_confirmation'] ?? null)) throw new InvalidArgumentException('รหัสผ่านไม่ตรงกัน');
        $phone = optional_text(input('phone'), 20, 'เบอร์โทร');
        $address = optional_text(input('address'), 10000, 'ที่อยู่');
        query('INSERT INTO EMPLOYER (company_name,email,password_hash,phone,address) VALUES (?,?,?,?,?)', [$company, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $address]);
        $_SESSION['flash'] = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ'; go('employer/login.php');
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
      catch (PDOException $ex) { if (($ex->errorInfo[1] ?? 0) !== 1062) throw $ex; $error = 'อีเมลนี้ถูกใช้สมัครนายจ้างแล้ว'; }
}
start_page('สมัครสมาชิกนายจ้าง'); error_box($error);
?>
<form class="panel" method="post">
<?php csrf_field(); field('company_name','ชื่อบริษัทหรือร้านค้า',input('company_name'),'text',true,150); field('email','อีเมล',input('email'),'email'); ?>
<p><label for="password">รหัสผ่าน (12–72 ไบต์)</label><input id="password" name="password" type="password" autocomplete="new-password" required></p>
<p><label for="password_confirmation">ยืนยันรหัสผ่าน</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></p>
<?php field('phone','เบอร์โทร (ไม่บังคับ)',input('phone'),'tel',false,20); ?>
<p><label for="address">ที่อยู่ (ไม่บังคับ)</label><textarea id="address" name="address" maxlength="10000"><?= e(input('address')) ?></textarea></p>
<button>สมัครสมาชิก</button></form>
<p><a href="<?= e(url('employer/login.php')) ?>">มีบัญชีแล้ว เข้าสู่ระบบ</a></p>
<?php end_page(); ?>
