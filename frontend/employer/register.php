<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$error = null;
$businessTypes = ['ร้านอาหารและเครื่องดื่ม', 'ค้าปลีกและค้าส่ง', 'อีคอมเมิร์ซ', 'บริการ', 'โรงแรมและการท่องเที่ยว', 'เทคโนโลยี', 'การศึกษา', 'การผลิต', 'โลจิสติกส์และขนส่ง', 'สุขภาพและความงาม', 'การเงินและประกันภัย', 'สื่อและโฆษณา', 'อีเวนต์และบันเทิง', 'อสังหาริมทรัพย์และก่อสร้าง', 'เกษตรและอาหาร', 'หน่วยงานรัฐและองค์กรไม่แสวงหากำไร', 'อื่น ๆ'];
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
        $company = optional_text(input('company_name'), 150, 'ชื่อบริษัท');
        $phone = optional_text(input('phone'), 20, 'โทรศัพท์');
        $businessType = input('business_type');
        if (!in_array($businessType, $businessTypes, true)) throw new InvalidArgumentException('กรุณาเลือกลักษณะธุรกิจ');
        $businessTypeOther = null;
        if ($businessType === 'อื่น ๆ') {
            $businessTypeOther = input('business_type_other');
            required_text($businessTypeOther, 100, 'ลักษณะธุรกิจอื่น ๆ');
        }
        $contactEmail = input('contact_email');
        required_text($contactEmail, 255, 'อีเมลติดต่อ');
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('อีเมลติดต่อไม่ถูกต้อง');
        query(
            'INSERT INTO EMPLOYER (username,email,password_hash,company_name,business_type,business_type_other,phone,contact_email) VALUES (?,?,?,?,?,?,?,?)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), $company, $businessType, $businessTypeOther, $phone, $contactEmail]
        );
        $id = (int) db()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION = ['auth' => ['role' => 'employer', 'id' => $id], 'flash' => 'สมัครสมาชิกสำเร็จ'];
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
    <p class="company-auth-note">ใช้ชื่อผู้ใช้และรหัสผ่านเพื่อเข้าสู่ระบบ</p>
    <form class="company-auth-fields" method="post" action="<?= e(url('employer/register.php')) ?>">
      <?php csrf_field(); ?>
      <p><label for="username">ชื่อผู้ใช้</label><input id="username" type="text" name="username" autocomplete="username" maxlength="80" value="<?= e(input('username')) ?>" required></p>
      <p><label for="password">รหัสผ่าน (12–72 ไบต์)</label><input id="password" type="password" name="password" autocomplete="new-password" required></p>
      <p><label for="password_confirmation">ยืนยันรหัสผ่าน</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></p>
      <p><label for="email">อีเมลสำหรับบัญชี</label><input id="email" type="email" name="email" autocomplete="email" maxlength="255" value="<?= e(input('email')) ?>" required></p>
      <p><label for="company_name">ชื่อบริษัทหรือร้านค้า (ไม่บังคับ)</label><input id="company_name" type="text" name="company_name" autocomplete="organization" maxlength="150" value="<?= e(input('company_name')) ?>"></p>
      <p><label for="business_type">ลักษณะธุรกิจ</label><select id="business_type" name="business_type" required><option value="">เลือกลักษณะธุรกิจ</option><?php foreach ($businessTypes as $type): ?><option value="<?= e($type) ?>" <?= input('business_type') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></p>
      <p id="business-type-other"<?= input('business_type') === 'อื่น ๆ' ? '' : ' hidden' ?>><label for="business_type_other">ระบุลักษณะธุรกิจ (เมื่อเลือกอื่น ๆ)</label><input id="business_type_other" name="business_type_other" maxlength="100" value="<?= e(input('business_type_other')) ?>"<?= input('business_type') === 'อื่น ๆ' ? ' required' : '' ?>></p>
      <p><label for="phone">โทรศัพท์ (ไม่บังคับ)</label><input id="phone" type="tel" name="phone" autocomplete="tel" maxlength="20" value="<?= e(input('phone')) ?>"></p>
      <p><label for="contact_email">อีเมลติดต่อ</label><input id="contact_email" type="email" name="contact_email" autocomplete="section-contact email" maxlength="255" value="<?= e(input('contact_email')) ?>" required></p>
      <button type="submit">สมัครสมาชิก</button>
    </form>
    <p class="company-auth-link">เป็นสมาชิกอยู่แล้ว? <a href="<?= e(url('employer/login.php')) ?>">เข้าสู่ระบบ</a></p>
  </div>
</div>
<script src="<?= e(url('assets/business-type.js')) ?>" defer></script>
<?php end_page(); ?>
