<?php
function work_skill_catalog(): array {
    return [
        'บริการลูกค้า'=>['customer service','งานบริการ','ดูแลลูกค้า'],
        'การสื่อสาร'=>['communication','สื่อสาร'],
        'การขาย'=>['sales','ขายสินค้า','ขายของ'],
        'แคชเชียร์'=>['cashier','คิดเงิน','รับชำระเงิน'],
        'ชงกาแฟ'=>['barista','บาริสต้า','บาริสตา','ทำกาแฟ'],
        'ทำอาหาร'=>['cooking','ปรุงอาหาร','ประกอบอาหาร'],
        'ทำขนม'=>['baking','เบเกอรี่'],
        'ภาษาอังกฤษ'=>['english','อังกฤษ'],
        'ภาษาจีน'=>['chinese','mandarin'],
        'สอนพิเศษ'=>['tutoring','ติวเตอร์','ติว'],
        'Microsoft Excel'=>['excel','ms excel','เอ็กเซล','เอ็กเซลล์'],
        'Microsoft Word'=>['word','เวิร์ด'],
        'ออกแบบกราฟิก'=>['graphic design','กราฟิกดีไซน์'],
        'ถ่ายภาพ'=>['photography','ถ่ายรูป'],
        'ตัดต่อวิดีโอ'=>['video editing','ตัดต่อวีดีโอ'],
        'จัดการสต็อก'=>['inventory','นับสต็อก','จัดสินค้า'],
        'ทำงานเป็นทีม'=>['teamwork','ร่วมงานเป็นทีม'],
        'ดูแลโซเชียลมีเดีย'=>['social media','ดูแลเพจ'],
    ];
}
function skill_key(string $name): string {
    return mb_strtolower(trim(preg_replace('/\s+/u',' ',$name)), 'UTF-8');
}
function canonical_skill(string $name): string {
    static $lookup = null;
    if ($lookup === null) {
        $lookup = [];
        foreach (work_skill_catalog() as $label=>$aliases)
            foreach ([$label,...$aliases] as $alias) $lookup[skill_key($alias)]=$label;
    }
    return $lookup[skill_key($name)] ?? trim(preg_replace('/\s+/u',' ',$name));
}
function skill_picker(string $name,string $label,string $newlineString): void {
    static $loaded=false;
    $catalog = work_skill_catalog();
    $selected = [];
    foreach (preg_split('/\R/u',$newlineString) as $line) {
        if (trim($line) === '') continue;
        $skill = canonical_skill($line);
        $selected[skill_key($skill)] = true;
        $catalog[$skill] ??= [];
    }
    ?>
    <fieldset class="skill-menu" data-skill-menu>
      <legend><?= e($label) ?></legend>
      <label data-skill-fallback>หนึ่งทักษะต่อบรรทัด<textarea name="<?= e($name) ?>" maxlength="10000"><?= e($newlineString) ?></textarea></label>
      <div data-skill-ui hidden>
        <div class="skill-toolbar"><input type="search" aria-label="ค้นหาใน<?= e($label) ?>" placeholder="ค้นหาทักษะ" maxlength="100"><output aria-live="polite"></output></div>
        <div class="skill-choices"><?php foreach ($catalog as $skill=>$aliases): ?>
          <label data-aliases="<?= e(implode(' ', $aliases)) ?>"><input type="checkbox" value="<?= e($skill) ?>" <?= isset($selected[skill_key($skill)])?'checked':'' ?>><span><?= e($skill) ?></span></label>
        <?php endforeach; ?></div>
        <label class="skill-custom" hidden><input type="checkbox"><span></span></label>
      </div>
    </fieldset>
    <?php if (!$loaded) { $loaded=true; ?><script src="<?=e(url('assets/skill-picker.js?v=2'))?>" defer></script><?php }

}
function work_weekdays(): array {
    return [1=>'จันทร์',2=>'อังคาร',3=>'พุธ',4=>'พฤหัสบดี',5=>'ศุกร์',6=>'เสาร์',7=>'อาทิตย์'];
}
function dashboard_recommendations(int $studentId): array {
    $student=query('SELECT preferred_area,min_wage FROM STUDENT WHERE student_id=?',[$studentId])->fetch();
    if (!$student) return [];
    $known=[];
    foreach(query('SELECT skill_name FROM STUDENT_SKILL WHERE student_id=?',[$studentId])->fetchAll(PDO::FETCH_COLUMN) as $skill)
        $known[skill_key(canonical_skill($skill))]=true;
    $skills=[];
    foreach(query("SELECT s.* FROM JOB_SKILL s JOIN JOB j USING(job_id) WHERE j.status='Open'")->fetchAll() as $row) {
        $name=canonical_skill($row['skill_name']);
        $key=skill_key($name);
        $old=$skills[$row['job_id']][$key]??null;
        if (!$old || $row['skill_type']==='required') $skills[$row['job_id']][$key]=[$name,$row['skill_type']];
    }
    $shiftCounts=[];
    foreach(query("SELECT s.job_id,COUNT(*) shift_count,SUM(EXISTS(SELECT 1 FROM STUDENT_AVAILABILITY a WHERE a.student_id=? AND a.weekday=s.weekday AND a.start_time<=s.start_time AND ((s.end_time<'24:00:00' AND a.end_time>=s.end_time) OR (s.end_time>='24:00:00' AND a.end_time>='23:59:00' AND EXISTS(SELECT 1 FROM STUDENT_AVAILABILITY b WHERE b.student_id=a.student_id AND b.weekday=MOD(s.weekday,7)+1 AND b.start_time='00:00:00' AND b.end_time>=SUBTIME(s.end_time,'24:00:00')))))) covered FROM JOB_SHIFT s JOIN JOB j USING(job_id) WHERE j.status='Open' GROUP BY s.job_id",[$studentId])->fetchAll() as $row) $shiftCounts[$row['job_id']]=$row;
    $jobs=query("SELECT job_id,job_title,hourly_wage,location,days_per_week FROM JOB WHERE status='Open' ORDER BY created_at DESC,job_id DESC")->fetchAll();
    foreach($jobs as &$job) {
        $names=[]; $missing=0;
        $wanted=$skills[$job['job_id']]??[];
        foreach($wanted as $key=>[$name,$type]) {
            if(isset($known[$key])) $names[]=$name;
            elseif ($type==='required') $missing++;
        }
        $job['matched_count']=count($names);
        $job['total_skills']=count($wanted);
        $job['matched_names']=implode(', ',$names);
        $shift=$shiftCounts[$job['job_id']]??null;
        $job['shift_match']=$shift && (int)$shift['covered'] >= (int)($job['days_per_week']??$shift['shift_count']);
        $area=trim($student['preferred_area']??'');
        $areaMatch=$area!=='' && mb_stripos($job['location'],$area)!==false;
        $wageMatch=$student['min_wage']!==null && (float)$job['hourly_wage']>=(float)$student['min_wage'];
        $job['area_match']=$areaMatch;
        $job['wage_match']=$wageMatch;
        $job['score']=($wanted ? count($names)/count($wanted)*4 : 0) - min(2,$missing) + 2*(int)$job['shift_match'] + (int)$areaMatch + (int)$wageMatch;
    }
    unset($job);
    $jobs=array_filter($jobs,static fn($j)=>$j['area_match'] || $j['wage_match'] || $j['shift_match'] || $j['matched_count']);
    usort($jobs,static fn($a,$b)=>$b['score']<=>$a['score']);
    return array_slice($jobs,0,5);
}

function job_schedule_summary(array $shifts, $daysNeeded): string {
    if (!$shifts) return '';
    $durations=[];
    foreach ($shifts as $shift) {
        $minutes=static fn($time)=>(int)substr($time,0,2)*60+(int)substr($time,3,2);
        $durations[]=($minutes($shift['end_time'])-$minutes($shift['start_time']))/60;
    }
    $minimum=min($durations); $maximum=max($durations);
    $hours=$minimum===$maximum?(string)$minimum:$minimum.'–'.$maximum;
    return $hours.' ชม./วัน · '.($daysNeeded ?: count($shifts)).' วัน/สัปดาห์';
}

function shift_fits_availability(array $shift, array $availability): bool {
    $seconds=static function(string $time): int {
        $parts=explode(':',$time);
        return (int)$parts[0]*3600+(int)$parts[1]*60+(int)($parts[2]??0);
    };
    $day=(int)$shift['weekday'];
    $start=$seconds($shift['start_time']); $end=$seconds($shift['end_time']);
    $slot=$availability[$day]??null;
    if (!$slot || $seconds($slot['start_time'])>$start) return false;
    if ($end<86400) return $seconds($slot['end_time'])>=$end;
    $next=$availability[$day%7+1]??null;
    return $seconds($slot['end_time'])>=86340 && ($end===86400 || ($next && $seconds($next['start_time'])===0 && $seconds($next['end_time'])>=$end-86400));
}
