<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
admin();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $employerId = positive_id(input('employer_id'));
        $decision = input('decision');
        if (!in_array($decision, ['verify', 'reject'], true)) {
            throw new InvalidArgumentException('คำสั่งตรวจสอบไม่ถูกต้อง');
        }

        $employer = query(
            "SELECT company_name,email,phone,contact_email,address FROM EMPLOYER
             WHERE employer_id=? AND verification_status='Pending'",
            [$employerId]
        )->fetch();
        if (!$employer) {
            $_SESSION['flash'] = 'รายการนี้ถูกตรวจสอบไปแล้ว หรือไม่พบข้อมูลนายจ้าง';
            go('admin/verification.php');
        }
        if ($decision === 'verify') {
            foreach ([
                'company_name' => 'ชื่อบริษัท',
                'email' => 'อีเมลบัญชี',
                'phone' => 'เบอร์โทร',
                'contact_email' => 'อีเมลติดต่อ',
                'address' => 'ที่อยู่',
            ] as $field => $label) {
                if (!is_string($employer[$field]) || trim($employer[$field]) === '') {
                    throw new InvalidArgumentException("ข้อมูล$labelไม่ครบ ไม่สามารถอนุมัติบัญชีนี้ได้");
                }
            }
            if (!filter_var($employer['email'], FILTER_VALIDATE_EMAIL) ||
                !filter_var($employer['contact_email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('อีเมลบัญชีหรืออีเมลติดต่อไม่ถูกต้อง ไม่สามารถอนุมัติบัญชีนี้ได้');
            }
            $updated = query(
                "UPDATE EMPLOYER SET verification_status='Verified',rejection_reason=NULL,verified_at=UTC_TIMESTAMP()
                 WHERE employer_id=? AND verification_status='Pending'
                   AND company_name <=> ? AND email <=> ? AND phone <=> ?
                   AND contact_email <=> ? AND address <=> ?",
                [$employerId,$employer['company_name'],$employer['email'],$employer['phone'],$employer['contact_email'],$employer['address']]
            )->rowCount();
            $_SESSION['flash'] = $updated
                ? 'อนุมัติบัญชีนายจ้างแล้ว'
                : 'ข้อมูลบัญชีเปลี่ยนระหว่างตรวจสอบ กรุณาโหลดรายการใหม่';
        } else {
            $reason = input('rejection_reason');
            required_text($reason, 1000, 'เหตุผลที่ปฏิเสธ');
            $updated = query(
                "UPDATE EMPLOYER SET verification_status='Rejected',rejection_reason=?,verified_at=NULL
                 WHERE employer_id=? AND verification_status='Pending'
                   AND company_name <=> ? AND email <=> ? AND phone <=> ?
                   AND contact_email <=> ? AND address <=> ?",
                [$reason,$employerId,$employer['company_name'],$employer['email'],$employer['phone'],$employer['contact_email'],$employer['address']]
            )->rowCount();
            $_SESSION['flash'] = $updated
                ? 'ปฏิเสธบัญชีและบันทึกเหตุผลแล้ว'
                : 'ข้อมูลบัญชีเปลี่ยนระหว่างตรวจสอบ กรุณาโหลดรายการใหม่';
        }
        go('admin/verification.php');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}

$employers = query(
    "SELECT employer_id,username,company_name,business_type,business_type_other,
            email,contact_email,phone,address
     FROM EMPLOYER
     WHERE verification_status='Pending'
       AND company_name IS NOT NULL AND CHAR_LENGTH(TRIM(company_name)) > 0
       AND business_type IS NOT NULL AND CHAR_LENGTH(TRIM(business_type)) > 0
       AND (business_type <> 'อื่น ๆ' OR
            (business_type_other IS NOT NULL AND CHAR_LENGTH(TRIM(business_type_other)) > 0))
       AND phone IS NOT NULL AND CHAR_LENGTH(TRIM(phone)) > 0
       AND contact_email IS NOT NULL AND CHAR_LENGTH(TRIM(contact_email)) > 0
       AND address IS NOT NULL AND CHAR_LENGTH(TRIM(address)) > 0
     ORDER BY employer_id"
)->fetchAll();

start_page('ตรวจสอบบัญชีนายจ้าง', 'workspace-page admin-page');
error_box($error);
?>
<div class="page-tools">
  <p class="muted">ตรวจชื่อกิจการ เบอร์โทร อีเมลติดต่อ และที่อยู่ก่อนอนุมัติ</p>
  <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?php csrf_field(); ?><button>ออกจากระบบ Admin</button></form>
</div>
<?php if (!$employers): ?><section class="panel"><h2>ไม่มีบัญชีที่มีข้อมูลครบพร้อมตรวจสอบ</h2></section><?php endif; ?>
<?php foreach ($employers as $employer): ?>
<section class="panel">
  <h2><?= e($employer['company_name'] ?? 'ยังไม่ระบุชื่อกิจการ') ?></h2>
  <dl>
    <dt>ชื่อผู้ใช้</dt><dd><?= e($employer['username'] ?? '—') ?></dd>
    <dt>อีเมลบัญชี</dt><dd><?= e($employer['email']) ?></dd>
    <dt>อีเมลติดต่อ</dt><dd><?= e($employer['contact_email'] ?? 'ยังไม่ระบุ') ?></dd>
    <dt>เบอร์โทร</dt><dd><?= e($employer['phone'] ?? 'ยังไม่ระบุ') ?></dd>
    <dt>ประเภทธุรกิจ</dt><dd><?= e($employer['business_type'] === 'อื่น ๆ' ? $employer['business_type_other'] : ($employer['business_type'] ?? 'ยังไม่ระบุ')) ?></dd>
    <dt>ที่อยู่</dt><dd><?= nl2br(e($employer['address'] ?? 'ยังไม่ระบุ')) ?></dd>
  </dl>
  <form method="post">
    <?php csrf_field(); ?><input type="hidden" name="employer_id" value="<?= e($employer['employer_id']) ?>">
    <input type="hidden" name="decision" value="verify">
    <button type="submit">อนุมัติและยืนยันบัญชี</button>
  </form>
  <form method="post">
    <?php csrf_field(); ?><input type="hidden" name="employer_id" value="<?= e($employer['employer_id']) ?>">
    <input type="hidden" name="decision" value="reject">
    <p><label for="rejection_reason_<?= e($employer['employer_id']) ?>">เหตุผลที่ปฏิเสธ (แจ้งให้นายจ้างทราบ)</label>
      <textarea id="rejection_reason_<?= e($employer['employer_id']) ?>" name="rejection_reason" maxlength="1000" required></textarea></p>
    <button type="submit">ปฏิเสธและแจ้งเหตุผล</button>
  </form>
</section>
<?php endforeach; ?>
<?php end_page(); ?>
