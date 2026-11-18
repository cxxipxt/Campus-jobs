<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/work-skills.php';

$id = require_student();
$profile = query('SELECT * FROM STUDENT WHERE student_id=?', [$id])->fetch();
if (!$profile) {
    http_response_code(404);
    exit('ไม่พบโปรไฟล์');
}
$providers = query('SELECT provider FROM STUDENT_OAUTH WHERE student_id=?', [$id])->fetchAll(PDO::FETCH_COLUMN);
$skills = implode("\n", query('SELECT skill_name FROM STUDENT_SKILL WHERE student_id=? ORDER BY skill_name', [$id])->fetchAll(PDO::FETCH_COLUMN));
$days = work_weekdays();
$availability = array_column(query('SELECT weekday,start_time,end_time FROM STUDENT_AVAILABILITY WHERE student_id=?', [$id])->fetchAll(), null, 'weekday');
$longFields = ['activities' => 'กิจกรรมที่เข้าร่วม', 'experience' => 'ประสบการณ์ทำงาน', 'languages' => 'ภาษาที่ใช้ได้', 'certificates' => 'ประกาศนียบัตร'];
$error = null;
$errorSection = 'basic';
$sections = ['basic' => 'ข้อมูลส่วนตัว', 'education' => 'การศึกษาและกิจกรรม', 'work' => 'ประสบการณ์และผลงาน', 'skills' => 'ทักษะและภาษา', 'preferences' => 'งานที่สนใจ', 'availability' => 'เวลาที่สะดวก', 'accounts' => 'บัญชีที่เชื่อมต่อ'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $editSkills = array_key_exists('skills', $_POST);
    if ($editSkills) $skills = input('skills');
    $editAvailability = input('edit_availability') === '1';
    if ($editAvailability) {
        $availability = [];
        foreach ($days as $day => $label) {
            if (input("available_$day") === '1') {
                $availability[$day] = ['start_time' => input("start_$day"), 'end_time' => input("end_$day")];
            }
        }
    }
    try {
        $name = input('full_name');
        required_text($name, 150, 'ชื่อและนามสกุล');
        $updates = ['full_name' => $name];
        $errorSection = 'education';
        if (isset($_POST['major'])) $updates['major'] = optional_text(input('major'), 150, 'สาขา');
        $errorSection = 'basic';
        if (isset($_POST['phone'])) $updates['phone'] = optional_text(input('phone'), 20, 'เบอร์โทร');
        $errorSection = 'work';
        if (isset($_POST['resume_url'])) $updates['resume_url'] = student_resume_link(input('resume_url'));
        foreach ($longFields as $key => $label) {
            if (array_key_exists($key, $_POST)) {
                $errorSection = ['activities'=>'education', 'experience'=>'work', 'languages'=>'skills', 'certificates'=>'skills'][$key];
                $updates[$key] = optional_text(input($key), 3000, $label);
            }
        }
        if (isset($_POST['preferred_area'])) {
            $errorSection = 'preferences';
            $updates['preferred_area'] = optional_text(input('preferred_area'), 150, 'พื้นที่ทำงานที่ต้องการ');
        }
        if (isset($_POST['portfolio_url'])) {
            $errorSection = 'work';
            $portfolio = optional_text(input('portfolio_url'), 2048, 'ลิงก์ผลงาน');
            if ($portfolio !== null && (!filter_var($portfolio, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $portfolio))) {
                throw new InvalidArgumentException('ลิงก์ผลงานต้องเป็น URL แบบ HTTP หรือ HTTPS');
            }
            $updates['portfolio_url'] = $portfolio;
        }
        if (isset($_POST['min_wage'])) {
            $errorSection = 'preferences';
            $updates['min_wage'] = input('min_wage') === '' ? null : money(input('min_wage'));
        }
        if (isset($_POST['study_year'])) {
            $errorSection = 'education';
            $year = input('study_year');
            if ($year !== '' && !preg_match('/^[1-8]$/D', $year)) {
                throw new InvalidArgumentException('กรุณาเลือกชั้นปี');
            }
            $updates['study_year'] = $year === '' ? null : $year;
        }
        $uniqueSkills = [];
        $errorSection = 'skills';
        foreach ($editSkills ? (preg_split('/\R/u', $skills) ?: []) : [] as $line) {
            if (trim($line) === '') continue;
            $skill = canonical_skill(trim($line));
            required_text($skill, 100, 'ทักษะ');
            $uniqueSkills[mb_strtolower($skill)] = $skill;
        }
        if (count($uniqueSkills) > 30) throw new InvalidArgumentException('เพิ่มได้สูงสุด 30 ทักษะ');
        if ($editAvailability) {
            $errorSection = 'availability';
            foreach ($availability as $day => $slot) {
                $timeFormat = '/^(?:[01]\d|2[0-3]):[0-5]\d$/D';
                if (!preg_match($timeFormat, $slot['start_time']) || !preg_match($timeFormat, $slot['end_time']) || $slot['start_time'] >= $slot['end_time']) {
                    throw new InvalidArgumentException('กรุณาระบุเวลาเริ่มและเวลาสิ้นสุดของวัน' . $days[$day] . ' โดยเวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม');
                }
            }
        }

        db()->beginTransaction();
        query('SELECT student_id FROM STUDENT WHERE student_id=? FOR UPDATE', [$id]);
        $assignments = implode('=?, ', array_keys($updates)) . '=?';
        query("UPDATE STUDENT SET $assignments, profile_completed=1 WHERE student_id=?", [...array_values($updates), $id]);
        if ($editSkills) {
            query('DELETE FROM STUDENT_SKILL WHERE student_id=?', [$id]);
            foreach ($uniqueSkills as $skill) {
                query('INSERT INTO STUDENT_SKILL (student_id,skill_name) VALUES (?,?)', [$id, $skill]);
            }
        }
        if ($editAvailability) {
            query('DELETE FROM STUDENT_AVAILABILITY WHERE student_id=?', [$id]);
            foreach ($availability as $day => $slot) {
                query('INSERT INTO STUDENT_AVAILABILITY (student_id,weekday,start_time,end_time) VALUES (?,?,?,?)', [$id,$day,$slot['start_time'],$slot['end_time']]);
            }
        }
        db()->commit();
        $_SESSION['student_profile_setup'] = false;
        $_SESSION['flash'] = 'บันทึกโปรไฟล์แล้ว';
        resume_student_job();
        $section = input('active_section');
        go('student/profile.php' . (isset($sections[$section]) ? '#' . $section : ''));
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        if (!$ex instanceof InvalidArgumentException) $errorSection = input('active_section');
        $error = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'ไม่สามารถบันทึกโปรไฟล์ได้ กรุณาลองใหม่อีกครั้ง';
    }
}
$value = static fn(string $key): string => $error && isset($_POST[$key]) ? input($key) : (string)($profile[$key] ?? '');
$longField = static function(string $key) use ($value, $longFields): void { ?>
<p><label for="<?= e($key) ?>"><?= e($longFields[$key]) ?></label><textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="3" maxlength="3000"><?= e($value($key)) ?></textarea></p>
<?php };
start_page('โปรไฟล์นักศึกษา', 'workspace-page student-editor');
?>
<?php error_box($error); ?>
<div class="editor-layout" data-error-section="<?= e($error ? $errorSection : '') ?>">
<nav class="editor-menu" aria-label="หัวข้อในโปรไฟล์">
<?php foreach ($sections as $anchor => $label): ?><a href="#<?= e($anchor) ?>"><?= e($label) ?></a><?php endforeach; ?>
</nav>
<div class="editor-content">
<form method="post">
<?php csrf_field(); ?>
<input type="hidden" name="active_section" value="">
<section class="editor-card" id="basic"><h2><?= e($sections['basic']) ?></h2>
<p><label for="verified-email">อีเมลมหาวิทยาลัย · ยืนยันแล้ว</label><input id="verified-email" type="email" value="<?= e($profile['email']) ?>" readonly></p>
<?php field('full_name','ชื่อและนามสกุล',$value('full_name'),'text',true,150); field('phone','เบอร์โทรศัพท์ (ไม่บังคับ)',$value('phone'),'tel',false,20); ?>
</section>
<section class="editor-card" id="education"><h2><?= e($sections['education']) ?></h2>
<?php field('major','สาขาวิชา',$value('major'),'text',false,150); ?>
<p><label for="study_year">ชั้นปี</label><select name="study_year" id="study_year"><option value="">ยังไม่ระบุ</option><?php for ($i=1;$i<=8;$i++): ?><option value="<?= $i ?>" <?= $value('study_year') === (string)$i ? 'selected' : '' ?>>ปี <?= $i ?></option><?php endfor; ?></select></p>
<?php $longField('activities'); ?></section>
<section class="editor-card" id="work"><h2><?= e($sections['work']) ?></h2>
<?php $longField('experience'); field('portfolio_url','ลิงก์ผลงาน',$value('portfolio_url'),'url',false,2048); field('resume_url','ลิงก์เรซูเม่',$value('resume_url'),'url',false,2048); ?></section>
<section class="editor-card" id="skills"><h2><?= e($sections['skills']) ?></h2>
<?php skill_picker('skills','ทักษะที่ใช้ทำงาน',$skills); $longField('languages'); $longField('certificates'); ?></section>
<section class="editor-card" id="preferences"><h2><?= e($sections['preferences']) ?></h2>
<?php field('preferred_area','พื้นที่ทำงานที่ต้องการ',$value('preferred_area'),'text',false,150); ?>
<p><label for="min_wage">ค่าจ้างขั้นต่ำที่ต้องการ (บาท/ชั่วโมง)</label><input type="number" name="min_wage" id="min_wage" min="0" max="99999999.99" step=".01" value="<?= e($value('min_wage')) ?>"></p></section>
<section class="editor-card" id="availability"><h2><?= e($sections['availability']) ?></h2>
<p>เลือกวันที่สะดวกทำงาน แล้วระบุช่วงเวลาในแต่ละวัน</p>
<input type="hidden" name="edit_availability" value="1">
<?php foreach ($days as $day => $label): $slot = $availability[$day] ?? []; ?>
<div class="editor-slot"><label><input type="checkbox" name="available_<?= $day ?>" value="1" <?= $slot ? 'checked' : '' ?>><?= e($label) ?></label>
<label>ตั้งแต่<input type="time" name="start_<?= $day ?>" aria-label="เวลาเริ่มวัน<?= e($label) ?>" value="<?= e(substr($slot['start_time'] ?? '',0,5)) ?>"></label>
<label>ถึง<input type="time" name="end_<?= $day ?>" aria-label="เวลาสิ้นสุดวัน<?= e($label) ?>" value="<?= e(substr($slot['end_time'] ?? '',0,5)) ?>"></label></div>
<?php endforeach; ?></section>
<div class="editor-actions"><button type="submit">บันทึกโปรไฟล์</button></div>
</form>
<section class="editor-card" id="accounts"><h2><?= e($sections['accounts']) ?></h2>
<?php foreach (['microsoft'=>'Microsoft','google'=>'Google'] as $provider=>$label): ?>
<div class="editor-provider"><strong><?= e($label) ?></strong>
<?php if (in_array($provider,$providers,true)): ?><span>เชื่อมต่อแล้ว</span>
<?php else: ?><form action="<?= e(url('student/oauth-start.php')) ?>" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="link"><input type="hidden" name="provider" value="<?= e($provider) ?>"><button type="submit">เชื่อมบัญชี <?= e($label) ?></button></form><?php endif; ?></div>
<?php endforeach; ?></section></div></div>
<script defer src="<?= e(url('assets/student-editor.js')) ?>"></script>
<?php end_page(); ?>
