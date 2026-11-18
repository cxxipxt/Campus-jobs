<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__).'/app/work-skills.php';
$openCount=(int)query("SELECT COUNT(*) FROM JOB WHERE status='Open'")->fetchColumn();
$latestJobs=query("SELECT j.*,e.company_name FROM JOB j JOIN EMPLOYER e USING(employer_id) WHERE j.status='Open' ORDER BY j.created_at DESC,j.job_id DESC LIMIT 6")->fetchAll();
$latestShifts=[];
if($latestJobs) {
    $ids=array_column($latestJobs,'job_id');
    foreach(query('SELECT job_id,start_time,end_time FROM JOB_SHIFT WHERE job_id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids)->fetchAll() as $slot) $latestShifts[$slot['job_id']][]=$slot;
}
$categories = query('SELECT category_id,category_name FROM JOB_CATEGORY ORDER BY category_id')->fetchAll();
start_page('หางานพาร์ทไทม์ที่เข้ากับเวลาเรียน', 'landing', false);
?>


    <section class="hero" aria-labelledby="hero-title">
      <div class="hero-copy">
        <h1 id="hero-title">หางานพาร์ทไทม์<br><span>ให้เข้ากับเวลาเรียน</span></h1>
        <p>ค้นหางานจากตำแหน่งหรือสถานที่ แล้วเปรียบเทียบค่าจ้างและเวลาทำงานก่อนสมัคร</p>
        <form action="<?= e(url('jobs.php')) ?>" method="get" role="search">
          <p><label for="q">ตำแหน่งงาน บริษัท หรือทักษะ</label><input type="search" id="q" name="q" placeholder="เช่น บาริสต้า หรือ Excel"></p>
          <p><label for="location">สถานที่ทำงาน</label><input type="text" id="location" name="location" placeholder="เช่น บางมด หรือกรุงเทพฯ"></p>
          <button type="submit">ค้นหางาน</button>
        </form>
        <nav class="hero-shortcuts" aria-label="ค้นหาตามหมวดงาน">
          <?php foreach (array_slice($categories, 0, 3) as $category): ?>
          <a href="<?= e(url('jobs.php?category_id='.$category['category_id'])) ?>"><?= e($category['category_name']) ?></a>
          <?php endforeach; ?>
        </nav>
        <p class="hero-browse"><a href="<?= e(url('jobs.php')) ?>">ดูประกาศทั้งหมด <span aria-hidden="true">→</span></a></p>
      </div>

      <div class="hero-visual job-carousel" aria-label="สำรวจประเภทงาน" aria-roledescription="carousel">
<?php $slides = [
 ['campus-cafe.jpg','งานคาเฟ่และร้านอาหาร','พบผู้คนใหม่ ๆ ในบรรยากาศที่คุณชอบ','บาริสต้า'],
 ['campus-tutoring.jpg','งานติวเตอร์และการสอน','แบ่งปันความรู้ ใช้ทักษะที่คุณถนัด','ติวเตอร์'],
 ['campus-retail.jpg','งานร้านค้าและบริการ','เก็บประสบการณ์การขายและดูแลลูกค้า','ร้านค้า'],
 ['campus-design.jpg','งานออกแบบกราฟิก','เปลี่ยนไอเดียสร้างสรรค์ให้เป็นผลงาน','ออกแบบ'],
 ['campus-office.jpg','งานธุรการและแอดมิน','ฝึกจัดการข้อมูลและประสานงาน','แอดมิน'],
 ['campus-event.jpg','งานอีเวนต์และทีมงาน','ร่วมสร้างประสบการณ์ในงานกิจกรรม','อีเวนต์'],
 ['campus-camera.jpg','งานถ่ายภาพและวิดีโอ','เล่าเรื่องผ่านภาพในมุมมองของคุณ','ถ่ายภาพ'],
 ['campus-code.jpg','งานเว็บไซต์และโปรแกรม','ต่อยอดทักษะดิจิทัลจากห้องเรียน','โปรแกรม']
]; foreach ($slides as $i => [$photo,$title,$description,$keyword]): ?>
<div class="job-slide<?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= $i+1 ?> จาก <?= count($slides) ?>"<?= $i ? ' inert aria-hidden="true"' : '' ?>>
<img src="<?= e(url('assets/'.$photo)) ?>" width="1000" height="900" alt="ภาพประกอบ<?= e($title) ?>" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
<aside class="hero-example"><h2><?= e($title) ?></h2><p><?= e($description) ?></p><a href="<?= e(url('jobs.php?q='.urlencode($keyword))) ?>">ค้นหางานประเภทนี้ →</a></aside>
</div>
<?php endforeach; ?>
<p class="carousel-hint">ปัดเพื่อดูงานประเภทอื่น</p><div class="carousel-controls" hidden><button type="button" data-carousel-prev aria-label="ภาพก่อนหน้า">←</button><span class="carousel-count">1 / <?= count($slides) ?></span><button type="button" data-carousel-next aria-label="ภาพถัดไป">→</button><button type="button" data-carousel-pause>หยุดสลับภาพ</button></div>
      </div>
    </section>
    <section class="latest-jobs" aria-labelledby="latest-title">
      <div class="latest-heading"><div><h2 id="latest-title">งานล่าสุด</h2><p>มี <?=e(number_format($openCount))?> ประกาศที่เปิดรับ</p></div><a href="<?=e(url('jobs.php'))?>">ดูงานทั้งหมด →</a></div>
      <div class="latest-grid">
      <?php foreach($latestJobs as $latest): ?>
      <article class="latest-card"><h3><a href="<?=e(url('job-detail.php?job_id='.$latest['job_id']))?>"><?=e($latest['job_title'])?></a></h3>
      <p><?=e($latest['company_name']??'')?></p><p class="latest-location"><?=e($latest['location'])?></p>
      <p class="latest-schedule"><?=e(isset($latestShifts[$latest['job_id']])?job_schedule_summary($latestShifts[$latest['job_id']],$latest['days_per_week']):$latest['work_schedule'])?></p>
      <strong>฿<?=e(number_format((float)$latest['hourly_wage'],2))?><small> / ชั่วโมง</small></strong></article>
      <?php endforeach; ?>
      </div><?php if(!$latestJobs): ?><p>ยังไม่มีประกาศที่เปิดรับในขณะนี้</p><?php endif; ?>
    </section>
    <section aria-labelledby="category-title">
      <h2 id="category-title">เลือกดูตามหมวดงาน</h2>
      <ul class="category-list">
<?php foreach ($categories as $category): ?>
<li><a href="<?= e(url('jobs.php?category_id='.$category['category_id'])) ?>"><?= e($category['category_name']) ?></a></li>
<?php endforeach; ?>
</ul>
    </section>

    <!-- ขั้นตอนการใช้งาน -->
    <section aria-labelledby="steps-title">
      <h2 id="steps-title">เริ่มสมัครงานอย่างไร</h2>
      <ol class="steps-list">
        <li>
          <h3>สมัครสมาชิก</h3>
          <p>เข้าสู่ระบบด้วยบัญชีอีเมลมหาวิทยาลัยและยืนยันข้อมูลโปรไฟล์</p>
        </li>
        <li>
          <h3>ค้นหางาน</h3>
          <p>กรองตามประเภทงาน สถานที่ และค่าจ้างต่อชั่วโมง</p>
        </li>
        <li>
          <h3>สมัครงาน</h3>
          <p>กดสมัครผ่านระบบ หรือบันทึกงานที่สนใจไว้ดูภายหลัง</p>
        </li>
        <li>
          <h3>ติดตามผล</h3>
          <p>ดูสถานะใบสมัครได้ที่หน้าการสมัครของฉัน</p>
        </li>
      </ol>
    </section>

    <!-- ชวนนายจ้าง -->
    <section class="employer-cta" aria-labelledby="employer-title">
      <h2 id="employer-title">เป็นเจ้าของร้านหรือบริษัท?</h2>
      <p>ประกาศงานพาร์ทไทม์และรับสมัครนักศึกษาได้ในที่เดียว</p>
      <a href="<?= e(url('employer/register.php')) ?>">สมัครสมาชิกนายจ้าง</a>
    </section>


<?php end_page(); ?>
