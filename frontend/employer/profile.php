<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$id = employer(); $error = null;
$profile = query('SELECT company_name,email,business_type,business_type_other,phone,contact_email,address,verification_status,rejection_reason FROM EMPLOYER WHERE employer_id=?', [$id])->fetch();
if (!$profile) { http_response_code(401); exit('ไม่พบบัญชีนายจ้าง'); }
$businessTypes = employer_business_types();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $company = input('company_name'); required_text($company,150,'ชื่อบริษัท');
        $businessType = input('business_type');
        if (!in_array($businessType, $businessTypes, true)) throw new InvalidArgumentException('กรุณาเลือกลักษณะธุรกิจ');
        $businessTypeOther = null;
        if ($businessType === 'อื่น ๆ') {
            $businessTypeOther = input('business_type_other');
            required_text($businessTypeOther,100,'ลักษณะธุรกิจอื่น ๆ');
        }
        $phone = input('phone'); required_text($phone,20,'เบอร์โทร');
        $contactEmail = input('contact_email'); required_text($contactEmail,255,'อีเมลติดต่อ');
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('อีเมลติดต่อไม่ถูกต้อง');
        $address = input('address'); required_text($address,10000,'ที่อยู่');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $profile = query(
                'SELECT company_name,business_type,business_type_other,phone,contact_email,address,verification_status
                 FROM EMPLOYER WHERE employer_id=? FOR UPDATE',
                [$id]
            )->fetch();
            if (!$profile) throw new InvalidArgumentException('ไม่พบบัญชีนายจ้าง');
            $changed = $company !== $profile['company_name'] || $businessType !== $profile['business_type'] ||
                $businessTypeOther !== $profile['business_type_other'] || $phone !== $profile['phone'] ||
                $contactEmail !== $profile['contact_email'] || $address !== $profile['address'];
            $submitForReview = in_array($profile['verification_status'], ['Draft', 'Rejected'], true) ||
                ($profile['verification_status'] === 'Verified' && $changed);
            $verificationUpdate = $submitForReview
                ? ",verification_status='Pending',rejection_reason=NULL,verified_at=NULL"
                : '';
            query(
                'UPDATE EMPLOYER SET company_name=?,business_type=?,business_type_other=?,phone=?,contact_email=?,address=?' .
                $verificationUpdate . ' WHERE employer_id=?',
                [$company,$businessType,$businessTypeOther,$phone,$contactEmail,$address,$id]
            );
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }
        $_SESSION['flash'] = $submitForReview
            ? 'บันทึกข้อมูลแล้ว บัญชีถูกส่งให้ Admin ตรวจสอบ'
            : ($profile['verification_status'] === 'Pending'
                ? 'บันทึกข้อมูลแล้ว บัญชียังคงรอ Admin ตรวจสอบ'
                : 'บันทึกข้อมูลบริษัทแล้ว');
        go('employer/profile.php');
    } catch (InvalidArgumentException $ex) { $error=$ex->getMessage(); }
}
start_page('ข้อมูลบริษัท', 'workspace-page company-page'); error_box($error);
?>
<section class="company-cover"><div aria-hidden="true"></div><h2><?= e($profile['company_name'] ?? 'กรอกข้อมูลบริษัทหรือร้านค้า') ?></h2><p>อีเมลบัญชี: <?= e($profile['email']) ?></p></section>
<section class="panel"><h2>สถานะบัญชี: <?= e(['Draft'=>'ยังไม่ส่งตรวจสอบ','Pending'=>'รอ Admin อนุมัติ','Verified'=>'ยืนยันแล้ว','Rejected'=>'ถูกปฏิเสธ'][$profile['verification_status']] ?? $profile['verification_status']) ?></h2>
<?php if ($profile['verification_status'] === 'Draft'): ?><p>กรอกข้อมูลบริษัทและข้อมูลติดต่อให้ครบ แล้วบันทึกเพื่อส่งให้ Admin ตรวจสอบ</p><?php elseif ($profile['verification_status'] === 'Rejected'): ?><p class="error">เหตุผลที่ปฏิเสธ: <?= e($profile['rejection_reason']) ?></p><p>แก้ไขข้อมูลด้านล่างและบันทึกเพื่อส่งให้ Admin ตรวจสอบอีกครั้ง</p><?php elseif ($profile['verification_status'] === 'Pending'): ?><p>บัญชีของคุณจะลงประกาศงานได้หลัง Admin ตรวจสอบและอนุมัติ</p><?php endif; ?></section>
<?php $formBusinessType = $_SERVER['REQUEST_METHOD'] === 'POST' ? input('business_type') : ($profile['business_type'] ?? ''); ?>
<form method="post" class="panel"><?php csrf_field(); field('company_name','ชื่อบริษัท',$_SERVER['REQUEST_METHOD']==='POST'?input('company_name'):($profile['company_name']??''),'text',true,150); ?>
<p><label for="business_type">ลักษณะธุรกิจ</label><select id="business_type" name="business_type" required><option value="">เลือกลักษณะธุรกิจ</option><?php foreach ($businessTypes as $type): ?><option value="<?= e($type) ?>" <?= $formBusinessType === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></p>
<p id="business-type-other"<?= $formBusinessType === 'อื่น ๆ' ? '' : ' hidden' ?>><label for="business_type_other">ระบุลักษณะธุรกิจอื่น ๆ</label><input id="business_type_other" name="business_type_other" maxlength="100" value="<?= e($_SERVER['REQUEST_METHOD']==='POST'?input('business_type_other'):($profile['business_type_other']??'')) ?>"<?= $formBusinessType === 'อื่น ๆ' ? ' required' : '' ?>></p>
<?php field('phone','เบอร์โทร',$_SERVER['REQUEST_METHOD']==='POST'?input('phone'):($profile['phone']??''),'tel',true,20); field('contact_email','อีเมลติดต่อ',$_SERVER['REQUEST_METHOD']==='POST'?input('contact_email'):($profile['contact_email']??''),'email',true,255); ?>
<p><label for="address">ที่อยู่</label><textarea id="address" name="address" maxlength="10000" required><?= e($_SERVER['REQUEST_METHOD']==='POST'?input('address'):($profile['address']??'')) ?></textarea></p>
<button><?= in_array($profile['verification_status'], ['Draft','Rejected'], true) ? 'บันทึกและส่งให้ Admin ตรวจสอบ' : 'บันทึกข้อมูล' ?></button></form>
<script src="<?= e(url('assets/business-type.js')) ?>" defer></script>
<?php end_page(); ?>
