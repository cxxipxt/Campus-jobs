<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
admin();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $employerId = positive_id(input('employer_id'));
        $action = input('action');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($action === 'approve') {
                $profile = query(
                    "SELECT company_name,business_type,business_type_other,phone,contact_email,address
                     FROM EMPLOYER WHERE employer_id=? AND verification_status='Pending' FOR UPDATE",
                    [$employerId]
                )->fetch();
                if (!$profile || !employer_profile_complete($profile)) {
                    throw new InvalidArgumentException('ข้อมูลนายจ้างไม่ครบหรือสถานะไม่ใช่ Pending');
                }
                $updated = query(
                    "UPDATE EMPLOYER SET verification_status='Verified',rejection_reason=NULL,verified_at=UTC_TIMESTAMP()
                     WHERE employer_id=? AND verification_status='Pending'",
                    [$employerId]
                )->rowCount();
                if ($updated !== 1) throw new InvalidArgumentException('สถานะบัญชีเปลี่ยนระหว่างตรวจสอบ กรุณาโหลดหน้าใหม่');
                $_SESSION['flash'] = 'อนุมัติบัญชีนายจ้างแล้ว';
            } elseif ($action === 'reject') {
                $reason = input('rejection_reason');
                required_text($reason, 1000, 'เหตุผลที่ปฏิเสธ');
                $updated = query(
                    "UPDATE EMPLOYER SET verification_status='Rejected',rejection_reason=?,verified_at=NULL
                     WHERE employer_id=? AND verification_status='Pending'",
                    [$reason, $employerId]
                )->rowCount();
                if ($updated !== 1) throw new InvalidArgumentException('สถานะบัญชีเปลี่ยนระหว่างตรวจสอบ กรุณาโหลดหน้าใหม่');
                $_SESSION['flash'] = 'ปฏิเสธบัญชีนายจ้างและแจ้งเหตุผลแล้ว';
            } else {
                throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }
        go('admin/verification.php');
    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
    }
}

$pending = query(
    "SELECT employer_id,username,company_name,business_type,business_type_other,email,contact_email,phone,address
     FROM EMPLOYER WHERE verification_status='Pending' ORDER BY employer_id"
)->fetchAll();
start_page('ตรวจสอบบัญชีนายจ้าง', 'workspace-page admin-page'); error_box($error);
?>
<div class="verification-overview">
  <p>ตรวจสอบข้อมูลกิจการและช่องทางติดต่อก่อนตัดสินใจอนุมัติ</p>
  <p class="verification-count"><strong><?= count($pending) ?></strong> บัญชีรอตรวจ</p>
</div>
<?php if (!$pending): ?>
<section class="verification-empty"><h2>ไม่มีบัญชีที่รอตรวจสอบ</h2><p>เมื่อมีนายจ้างส่งข้อมูลเข้ามา รายการจะแสดงที่หน้านี้</p></section>
<?php else: ?>
<div class="verification-list">
<?php foreach ($pending as $profile): ?>
<article class="verification-card">
  <header class="verification-card-header">
    <div><span class="verification-status">รอตรวจสอบ</span><h2><?= e($profile['company_name']) ?></h2></div>
    <span class="verification-id">รายการ #<?= e($profile['employer_id']) ?></span>
  </header>
  <dl class="verification-details">
    <div><dt>ชื่อผู้ใช้</dt><dd><?= e($profile['username'] ?? 'บัญชี Google') ?></dd></div>
    <div><dt>ลักษณะธุรกิจ</dt><dd><?= e($profile['business_type'] === 'อื่น ๆ' ? $profile['business_type_other'] : $profile['business_type']) ?></dd></div>
    <div><dt>อีเมลบัญชี</dt><dd><a href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a></dd></div>
    <div><dt>อีเมลติดต่อ</dt><dd><a href="mailto:<?= e($profile['contact_email']) ?>"><?= e($profile['contact_email']) ?></a></dd></div>
    <div><dt>เบอร์โทรศัพท์</dt><dd><a href="tel:<?= e($profile['phone']) ?>"><?= e($profile['phone']) ?></a></dd></div>
    <div class="verification-address"><dt>ที่อยู่บริษัท</dt><dd><?= nl2br(e($profile['address'])) ?></dd></div>
  </dl>
  <div class="verification-actions">
    <form method="post" class="verification-approve-form">
      <?php csrf_field(); ?>
      <input type="hidden" name="employer_id" value="<?= e($profile['employer_id']) ?>">
      <input type="hidden" name="action" value="approve">
      <button type="submit">อนุมัติและยืนยันบัญชี</button>
    </form>
    <details class="verification-reject">
      <summary>ปฏิเสธคำขอ</summary>
      <form method="post" class="verification-reject-form">
        <?php csrf_field(); ?>
        <input type="hidden" name="employer_id" value="<?= e($profile['employer_id']) ?>">
        <input type="hidden" name="action" value="reject">
        <label for="reason-<?= e($profile['employer_id']) ?>">เหตุผลที่ปฏิเสธ (นายจ้างจะเห็นข้อความนี้)</label>
        <textarea id="reason-<?= e($profile['employer_id']) ?>" name="rejection_reason" maxlength="1000" required></textarea>
        <button type="submit">ยืนยันการปฏิเสธ</button>
      </form>
    </details>
  </div>
</article>
<?php endforeach; ?>
 </div>
<?php endif; ?>
<?php end_page(); ?>
