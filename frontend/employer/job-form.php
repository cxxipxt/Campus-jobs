<?php
require dirname(__DIR__,2) . '/app/bootstrap.php';
$owner=employer(); $id=null; $error=null;
try { if (isset($_GET['job_id'])) $id=positive_id(input('job_id',$_GET)); }
catch (InvalidArgumentException $ex) { http_response_code(400); exit(e($ex->getMessage())); }
$data=$id?owned_job($id,$owner):['job_title'=>'','category_id'=>'','description'=>'','hourly_wage'=>'','location'=>'','work_schedule'=>''];
$skills=$id?implode("\n",query('SELECT skill_name FROM JOB_SKILL WHERE job_id=? ORDER BY skill_name',[$id])->fetchAll(PDO::FETCH_COLUMN)):'';
$categories=query('SELECT category_id,category_name FROM JOB_CATEGORY ORDER BY category_name')->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        foreach(['job_title'=>150,'description'=>20000,'location'=>255,'work_schedule'=>255] as $key=>$max) { $data[$key]=input($key); required_text($data[$key],$max,$key); }
        $data['hourly_wage']=money(input('hourly_wage'));
        $data['category_id']=positive_id(input('category_id'));
        if(!query('SELECT category_id FROM JOB_CATEGORY WHERE category_id=?',[$data['category_id']])->fetch()) throw new InvalidArgumentException('กรุณาเลือกประเภทงานที่มีอยู่จริง');
        $skills=input('skills');
        if(mb_strlen($skills,'UTF-8')>10000) throw new InvalidArgumentException('รายการทักษะยาวเกินไป');
        $skillList=[];
        foreach(preg_split('/\R/u',$skills) as $skill) { $skill=trim($skill); if($skill==='')continue; required_text($skill,100,'ทักษะ'); $skillList[]=$skill; }
        if(count($skillList)>50) throw new InvalidArgumentException('เพิ่มได้ไม่เกิน 50 ทักษะ');
        $pdo=db(); $pdo->beginTransaction();
        try {
            $params=[$data['category_id'],$data['job_title'],$data['description'],$data['hourly_wage'],$data['location'],$data['work_schedule']];
            if($id) {
                owned_job($id,$owner,true);
                query('UPDATE JOB SET category_id=?,job_title=?,description=?,hourly_wage=?,location=?,work_schedule=? WHERE job_id=? AND employer_id=?',[...$params,$id,$owner]);
                query('DELETE FROM JOB_SKILL WHERE job_id=?',[$id]);
            } else {
                query('INSERT INTO JOB (category_id,job_title,description,hourly_wage,location,work_schedule,employer_id) VALUES (?,?,?,?,?,?,?)',[...$params,$owner]);
                $id=(int)$pdo->lastInsertId();
            }
            // DB collation determines equivalent Thai/case/accent variants, not PHP.
            foreach($skillList as $skill) query('INSERT INTO JOB_SKILL (job_id,skill_name) VALUES (?,?) ON DUPLICATE KEY UPDATE skill_name=JOB_SKILL.skill_name',[$id,$skill]);
            $pdo->commit();
        } catch(Throwable $ex) { if($pdo->inTransaction())$pdo->rollBack(); throw $ex; }
        $_SESSION['flash']='บันทึกประกาศแล้ว'; go('employer/jobs.php');
    } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
}
start_page($id?'แก้ไขประกาศงาน':'สร้างประกาศงาน'); error_box($error);
?>
<form class="panel" method="post"><?php csrf_field(); field('job_title','ชื่อตำแหน่ง',(string)$data['job_title'],'text',true,150); ?>
<p><label for="category_id">ประเภทงาน</label><select id="category_id" name="category_id" required><option value="">เลือกประเภทงาน</option>
<?php foreach($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= (string)$data['category_id']===(string)$category['category_id']?'selected':'' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select></p>
<?php if(!$categories): ?><p class="error">ยังไม่มีประเภทงาน กรุณารัน seed.sql ก่อนสร้างประกาศ</p><?php endif; ?>
<p><label for="description">รายละเอียดงาน</label><textarea id="description" name="description" maxlength="20000" required><?= e($data['description']) ?></textarea></p>
<p><label for="hourly_wage">ค่าจ้างต่อชั่วโมง (บาท)</label><input id="hourly_wage" name="hourly_wage" type="number" min="0" max="99999999.99" step="0.01" value="<?= e($data['hourly_wage']) ?>" required></p>
<?php field('location','สถานที่',(string)$data['location']); field('work_schedule','วันและเวลางาน',(string)$data['work_schedule']); ?>
<p><label for="skills">ทักษะที่ต้องการ (หนึ่งทักษะต่อบรรทัด ไม่บังคับ)</label><textarea id="skills" name="skills" maxlength="10000"><?= e($skills) ?></textarea></p>
<button <?= !$categories?'disabled':'' ?>>บันทึกประกาศ</button> <a href="<?= e(url('employer/jobs.php')) ?>">กลับรายการประกาศ</a>
</form><?php end_page(); ?>
