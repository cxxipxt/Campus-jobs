<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$id = employer(); $error = null;
$profile = query('SELECT company_name,email,phone,address FROM EMPLOYER WHERE employer_id=?', [$id])->fetch();
if (!$profile) { http_response_code(401); exit('ไม่พบบัญชีนายจ้าง'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $company = input('company_name'); required_text($company,150,'ชื่อบริษัท');
        $phone = optional_text(input('phone'),20,'เบอร์โทร'); $address = optional_text(input('address'),10000,'ที่อยู่');
        query('UPDATE EMPLOYER SET company_name=?,phone=?,address=? WHERE employer_id=?',[$company,$phone,$address,$id]);
        $_SESSION['flash']='บันทึกข้อมูลบริษัทแล้ว'; go('employer/profile.php');
    } catch (InvalidArgumentException $ex) { $error=$ex->getMessage(); }
}
start_page('ข้อมูลบริษัท'); error_box($error);
?>
<p>อีเมลบัญชี: <?= e($profile['email']) ?></p>
<form method="post" class="panel"><?php csrf_field(); field('company_name','ชื่อบริษัท',$_SERVER['REQUEST_METHOD']==='POST'?input('company_name'):$profile['company_name'],'text',true,150); field('phone','เบอร์โทร (ไม่บังคับ)',$_SERVER['REQUEST_METHOD']==='POST'?input('phone'):($profile['phone']??''),'tel',false,20); ?>
<p><label for="address">ที่อยู่ (ไม่บังคับ)</label><textarea id="address" name="address" maxlength="10000"><?= e($_SERVER['REQUEST_METHOD']==='POST'?input('address'):($profile['address']??'')) ?></textarea></p>
<button>บันทึกข้อมูล</button></form>
<?php end_page(); ?>
