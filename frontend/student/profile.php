<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$id = require_student();
$profile = query('SELECT * FROM STUDENT WHERE student_id=?', [$id])->fetch();
if (!$profile) { http_response_code(404); exit('ไม่พบโปรไฟล์'); }
$skills = implode("\n", query('SELECT skill_name FROM STUDENT_SKILL WHERE student_id=? ORDER BY skill_name', [$id])->fetchAll(PDO::FETCH_COLUMN));
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $skills = input('skills');
    try {
        $name = input('full_name');
        required_text($name, 150, 'ชื่อและนามสกุล');
        $major = optional_text(input('major'), 150, 'สาขา');
        $phone = optional_text(input('phone'), 20, 'เบอร์โทร');
        $resume = student_resume_link(input('resume_url'));
        $lines = array_unique(array_filter(array_map('trim', preg_split('/\R/u', $skills) ?: []), static fn($line) => $line !== ''));
        if (count($lines) > 30) throw new InvalidArgumentException('เพิ่มได้สูงสุด 30 ทักษะ');
        foreach ($lines as $line) required_text($line, 100, 'ทักษะ');
        db()->beginTransaction();
        try {
            query('SELECT student_id FROM STUDENT WHERE student_id=? FOR UPDATE', [$id]);
            query('UPDATE STUDENT SET full_name=?, major=?, phone=?, resume_url=? WHERE student_id=?', [$name,$major,$phone,$resume,$id]);
            query('DELETE FROM STUDENT_SKILL WHERE student_id=?', [$id]);
            foreach ($lines as $line) {
                query('INSERT INTO STUDENT_SKILL (student_id,skill_name) VALUES (?,?) ON DUPLICATE KEY UPDATE skill_name=STUDENT_SKILL.skill_name', [$id,$line]);
            }
            db()->commit();
        } catch (Throwable $ex) { db()->rollBack(); throw $ex; }
        $_SESSION['flash'] = 'บันทึกโปรไฟล์แล้ว';
        go('student/profile.php');
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
}
start_page('โปรไฟล์นักศึกษา', 'workspace-page');
error_box($error);
$value = static fn($key) => $error ? input($key) : ($profile[$key] ?? '');
?>
<p><?= e($profile['email']) ?></p>
<form method="post" class="panel">
  <?php csrf_field();
  field('full_name','ชื่อและนามสกุล',$value('full_name'),'text',true,150);
  field('major','สาขา',$value('major'),'text',false,150);
  field('phone','เบอร์โทร',$value('phone'),'tel',false,20);
  field('resume_url','ลิงก์เรซูเม่',$value('resume_url'),'url',false,2048); ?>
  <p><label for="skills">ทักษะ (หนึ่งทักษะต่อบรรทัด)</label><textarea id="skills" name="skills" maxlength="3050"><?= e($skills) ?></textarea></p>
  <button>บันทึกโปรไฟล์</button>
</form>
<?php end_page(); ?>
