<?php
require dirname(__DIR__) . '/app/bootstrap.php';
start_page('เริ่มต้นกับ CampusJobs', 'workspace-page account-choice', false);
?>
<h1>คุณกำลังมองหาอะไร?</h1>
<p class="muted">เลือกประเภทบัญชีเพื่อเริ่มต้นใช้งาน</p>
<div class="account-options">
<section><h2>หางานระหว่างเรียน</h2><p>สมัครด้วยบัญชี Microsoft หรือ Google ของมหาวิทยาลัย แล้วกรอกโปรไฟล์เพื่อเริ่มสมัครงาน</p><a class="button" href="<?= e(url('student/login.php')) ?>">สมัครบัญชีนักศึกษา</a></section>
<section><h2>หาคนมาร่วมงาน</h2><p>สร้างบัญชีนายจ้าง ลงประกาศ และติดตามผู้สมัครของคุณได้ในที่เดียว</p><a class="button button--outline" href="<?= e(url('employer/register.php')) ?>">สมัครบัญชีนายจ้าง</a></section>
</div>
<?php end_page(); ?>
