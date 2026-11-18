<?php
require dirname(__DIR__,2) . '/app/bootstrap.php';
require_once dirname(__DIR__,2) . '/app/work-skills.php';
$owner=employer(); $id=null; $error=null;
if (query('SELECT 1 FROM EMPLOYER WHERE employer_id=? AND company_name IS NULL', [$owner])->fetchColumn()) {
    $_SESSION['flash'] = 'กรอกชื่อบริษัทหรือร้านค้าก่อนลงประกาศงาน';
    go('employer/profile.php');
}
try { if (isset($_GET['job_id'])) $id=positive_id(input('job_id',$_GET)); }
catch (InvalidArgumentException $ex) { http_response_code(400); exit(e($ex->getMessage())); }
$data=$id?owned_job($id,$owner):['job_title'=>'','category_id'=>'','description'=>'','hourly_wage'=>'','location'=>'','work_schedule'=>''];
$groups=['required'=>'','preferred'=>''];
if ($id) foreach(query('SELECT skill_type,skill_name FROM JOB_SKILL WHERE job_id=? ORDER BY skill_name',[$id])->fetchAll() as $row) $groups[$row['skill_type']].=$row['skill_name']."\n";
$shifts=$id?query('SELECT weekday,start_time,end_time FROM JOB_SHIFT WHERE job_id=?',[$id])->fetchAll(PDO::FETCH_UNIQUE):[];
$daysNeeded=(string)($data['days_per_week']??'');
$categories=query('SELECT category_id,category_name FROM JOB_CATEGORY ORDER BY category_name')->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        foreach(['job_title'=>150,'description'=>20000,'location'=>255] as $key=>$max) { $data[$key]=input($key); required_text($data[$key],$max,$key); }
        $data['hourly_wage']=money(input('hourly_wage'));
        $data['hours_per_day']=input('hours_per_day') === '' ? null : money(input('hours_per_day'));
        if ($data['hours_per_day'] !== null && ((float)$data['hours_per_day'] <= 0 || (float)$data['hours_per_day'] > 24)) throw new InvalidArgumentException('ชั่วโมงทำงานต่อวันต้องมากกว่า 0 และไม่เกิน 24');
        $data['category_id']=positive_id(input('category_id'));
        if(!query('SELECT category_id FROM JOB_CATEGORY WHERE category_id=?',[$data['category_id']])->fetch()) throw new InvalidArgumentException('กรุณาเลือกประเภทงานที่มีอยู่จริง');
        $skillList=[];
        foreach (['required'=>'skills','preferred'=>'preferred_skills'] as $type=>$fieldName) {
            $groups[$type]=input($fieldName);
            if(mb_strlen($groups[$type])>10000) throw new InvalidArgumentException('รายการทักษะยาวเกินไป');
            foreach(preg_split('/\R/u',$groups[$type]) as $line) {
                $skill=canonical_skill($line);
                if($skill==='') continue;
                required_text($skill,100,'ทักษะ');
                $skillList[skill_key($skill)]??=[$skill,$type];
            }
        }
        if(count($skillList)>50) throw new InvalidArgumentException('เพิ่มได้ไม่เกิน 50 ทักษะ');
        $daysNeeded=input('days_per_week');
        $shifts=[];
        foreach(work_weekdays() as $day=>$dayName) {
            if (input('shift_day_'.$day)==='') continue;
            $start=input('shift_start_'.$day);
            $end=input('shift_end_'.$day);
            $shifts[$day]=['start_time'=>$start,'end_time'=>$end];
            foreach([$start,$end] as $time) if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)) throw new InvalidArgumentException('กรุณาระบุเวลาเริ่มและสิ้นสุดของวัน'.$dayName);
            if ($start >= $end) throw new InvalidArgumentException('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่มของวัน'.$dayName);
        }
        if ($daysNeeded!=='' && (!preg_match('/^[1-7]$/D',$daysNeeded) || (int)$daysNeeded>count($shifts))) throw new InvalidArgumentException('จำนวนวันต่อสัปดาห์ต้องไม่เกินจำนวนวันที่เปิดกะ');
        $data['work_schedule']=input('work_schedule');
        if ($data['work_schedule']==='' && $shifts) $data['work_schedule']=$daysNeeded!==''?'เลือก '.$daysNeeded.' วัน/สัปดาห์':'ทำงานตามกะที่ระบุ';
        required_text($data['work_schedule'],255,'วันและเวลางาน');
        $pdo=db(); $pdo->beginTransaction();
        try {
            $params=[$data['category_id'],$data['job_title'],$data['description'],$data['hourly_wage'],$data['location'],$data['work_schedule'],$data['hours_per_day']];
            if($id) {
                owned_job($id,$owner,true);
                query('UPDATE JOB SET category_id=?,job_title=?,description=?,hourly_wage=?,location=?,work_schedule=?,hours_per_day=? WHERE job_id=? AND employer_id=?',[...$params,$id,$owner]);
                query('DELETE FROM JOB_SKILL WHERE job_id=?',[$id]);
            } else {
                query('INSERT INTO JOB (category_id,job_title,description,hourly_wage,location,work_schedule,hours_per_day,employer_id) VALUES (?,?,?,?,?,?,?,?)',[...$params,$owner]);
                $id=(int)$pdo->lastInsertId();
            }
            query('UPDATE JOB SET days_per_week=? WHERE job_id=?',[$daysNeeded===''?null:(int)$daysNeeded,$id]);
            query('DELETE FROM JOB_SHIFT WHERE job_id=?',[$id]);
            foreach($shifts as $day=>$shift) query('INSERT INTO JOB_SHIFT(job_id,weekday,start_time,end_time) VALUES(?,?,?,?)',[$id,$day,$shift['start_time'],$shift['end_time']]);
            foreach($skillList as [$skill,$type]) query('INSERT INTO JOB_SKILL (job_id,skill_name,skill_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE skill_name=JOB_SKILL.skill_name',[$id,$skill,$type]);
            $pdo->commit();
        } catch(Throwable $ex) { if($pdo->inTransaction())$pdo->rollBack(); throw $ex; }
        $_SESSION['flash']='บันทึกประกาศแล้ว'; go('employer/jobs.php');
    } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
}
start_page($id?'แก้ไขประกาศงาน':'สร้างประกาศงาน', 'workspace-page posting-page'); error_box($error);
?>
<div class="posting-layout"><form class="panel" method="post"><?php csrf_field(); field('job_title','ชื่อตำแหน่ง',(string)$data['job_title'],'text',true,150); ?>
<p><label for="category_id">ประเภทงาน</label><select id="category_id" name="category_id" required><option value="">เลือกประเภทงาน</option>
<?php foreach($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= (string)$data['category_id']===(string)$category['category_id']?'selected':'' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select></p>
<?php if(!$categories): ?><p class="error">ยังไม่มีหมวดงานให้เลือก กรุณาติดต่อผู้ดูแลระบบ</p><?php endif; ?>
<p><label for="description">รายละเอียดงาน</label><textarea id="description" name="description" maxlength="20000" required><?= e($data['description']) ?></textarea></p>
<p><label for="hourly_wage">ค่าจ้างต่อชั่วโมง (บาท)</label><input id="hourly_wage" name="hourly_wage" type="number" min="0" max="99999999.99" step="0.01" value="<?= e($data['hourly_wage']) ?>" required></p>
<?php field('location','สถานที่',(string)$data['location']); field('work_schedule','คำอธิบายวันและเวลางาน',(string)$data['work_schedule'],'text',false); ?>
<p><label for="hours_per_day">ชั่วโมงทำงานต่อวัน (ไม่บังคับ)</label><input id="hours_per_day" name="hours_per_day" type="number" min="0.01" max="24" step="0.01" placeholder="เช่น 4 หรือ 7.5" value="<?= e($data['hours_per_day'] ?? '') ?>"><small>หากเวลาไม่แน่นอน ให้เว้นว่างและระบุรายละเอียดในวันและเวลางาน</small></p>
<fieldset><legend>กะงานรายสัปดาห์</legend>
<?php foreach(work_weekdays() as $day=>$dayName): $shift=$shifts[$day]??[]; ?>
<div><label><input type="checkbox" name="shift_day_<?=$day?>" value="1" <?=$shift?'checked':''?>> <?=e($dayName)?></label>
<label>เริ่ม <input type="time" name="shift_start_<?=$day?>" value="<?=e(substr($shift['start_time']??'',0,5))?>"></label>
<label>สิ้นสุด <input type="time" name="shift_end_<?=$day?>" value="<?=e(substr($shift['end_time']??'',0,5))?>"></label></div>
<?php endforeach; ?>
<label>จำนวนวันที่นักศึกษาเลือกทำได้ต่อสัปดาห์ <select name="days_per_week"><option value="">ทำทุกวันที่ระบุ</option><?php foreach(range(1,7) as $n): ?><option value="<?=$n?>" <?=$daysNeeded===(string)$n?'selected':''?>>เลือก <?=$n?> วัน</option><?php endforeach; ?></select></label></fieldset>
<div class="posting-skills"><h2>ทักษะสำหรับตำแหน่งนี้</h2><p>ติ๊กทุกทักษะที่ต้องการ แล้วบันทึกประกาศครั้งเดียว</p><?php skill_picker('skills','ทักษะจำเป็น',$groups['required']); skill_picker('preferred_skills','ทักษะเสริม (พิจารณาเป็นพิเศษ)',$groups['preferred']); ?></div>
<div class="post-actions"><button <?= !$categories?'disabled':'' ?>>บันทึกประกาศ</button> <a href="<?= e(url('employer/jobs.php')) ?>">กลับรายการประกาศ</a></div>
</form><aside class="panel"><h2>เขียนประกาศให้ชัดเจน</h2><ul><li>ระบุชื่อตำแหน่งและสถานที่</li><li>แจ้งค่าจ้างต่อชั่วโมง</li><li>บอกวันและเวลาทำงาน</li></ul></aside></div><?php end_page(); ?>
