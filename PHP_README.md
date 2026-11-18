# PHP ของมิก

ส่วนนี้ใช้ PHP 8.1 ขึ้นไปพร้อม extensions `pdo_mysql` และ `mbstring` กับ MySQL 8.0.16 ขึ้นไป ใช้ schema v3 ซึ่งเพิ่มสถานะตรวจสอบนายจ้างใน EMPLOYER โดยไม่เพิ่มตาราง

## เริ่มใช้งาน

1. เตรียมฐานข้อมูลใหม่ด้วย `database/schema.sql` → `database/seed.sql` → `database/verify.sql` ถ้ามีตารางครบแล้ว ไม่ต้องสร้างซ้ำ
2. คัดลอก `.env.example` เป็น `.env` ใส่ชื่อผู้ใช้ MySQL และรหัสผ่านของเครื่องตัวเอง ไม่ commit `.env` ไม่ใช้บัญชี root สำหรับแอป
3. เปิด terminal ที่โฟลเดอร์ repository แล้วรัน:

```sh
php -S 127.0.0.1:8000 -t frontend frontend/router.php
```

4. ตั้งค่า Admin ตามหัวข้อถัดไป แล้วเปิด `http://127.0.0.1:8000/employer/login.php`

### ตั้งค่าและตรวจสอบนายจ้าง

- ฐานข้อมูลเดิมให้รัน `database/migrations/007_employer_verification.sql` หนึ่งครั้ง; ฐานข้อมูลใหม่ที่สร้างจาก `schema.sql` มีคอลัมน์พร้อมแล้ว
- กำหนด `ADMIN_USERNAME` และ `ADMIN_PASSWORD_HASH` ใน `.env`; สร้าง hash ด้วย `php -r "echo password_hash('your-secret-password', PASSWORD_DEFAULT), PHP_EOL;"` แล้วใส่ hash แทนรหัสผ่านจริง
- เข้าหน้า `http://127.0.0.1:8000/admin/login.php` เพื่ออนุมัติหรือปฏิเสธบัญชีที่รอตรวจสอบ พร้อมแจ้งเหตุผลเมื่อต้องปฏิเสธ
- นายจ้างต้องกรอกชื่อกิจการ เบอร์โทร อีเมลติดต่อ และที่อยู่; บัญชีที่ยังไม่ Verified ลงประกาศงานไม่ได้ การแก้ข้อมูลหลังอนุมัติจะส่งบัญชีกลับเข้าคิว Pending

### ตั้งค่า Google Login สำหรับนายจ้าง

- ฐานข้อมูลที่สร้างไว้ก่อนหน้านี้ให้รัน `database/migrations/002_employer_google_login.sql` หนึ่งครั้ง ฐานข้อมูลที่สร้างด้วย `schema.sql` ปัจจุบันมีคอลัมน์ครบแล้ว
- ฐานข้อมูลเดิมให้รัน `database/migrations/003_employer_registration_details.sql` ต่อจาก 002 เพื่อรองรับชื่อผู้ใช้ ลักษณะธุรกิจ และอีเมลติดต่อ
- เปิด PHP extension `curl` และตั้งค่า `EMPLOYER_GOOGLE_CLIENT_ID`, `EMPLOYER_GOOGLE_CLIENT_SECRET` และ `EMPLOYER_GOOGLE_REDIRECT_URI` ใน `.env` จาก OAuth client ประเภท Web application ใน Google Cloud
- เพิ่ม URL เดียวกับ `EMPLOYER_GOOGLE_REDIRECT_URI` ใน Authorized redirect URIs และเปิดเว็บด้วย host เดียวกันเพื่อให้ session ใช้ได้เมื่อ Google ส่งกลับมา

ตั้งค่า PHP `extension=pdo_mysql` และ `extension=mbstring` ใน php.ini หากยังไม่เปิด สำหรับ Apache ให้ document root ชี้ `frontend` เท่านั้น เปิด mod_rewrite/AllowOverride เพื่อให้ลิงก์ .html เดิมไปหน้า PHP และตั้ง APP_BASE_PATH ตาม prefix ของเว็บ ห้ามเปิดทั้ง repository เป็น web root เพราะมี `.env` และโค้ดภายใน

## ทำงานได้ในส่วนนี้

- นายจ้างสมัคร/ล็อกอิน/ออกจากระบบ และแก้ข้อมูลบริษัท เบอร์โทร อีเมลติดต่อ ที่อยู่ (อีเมลบัญชีไม่เปลี่ยนในรอบนี้)
- Admin ตรวจสอบและอนุมัติ/ปฏิเสธบัญชีนายจ้างได้ โดยเหตุผลปฏิเสธจะแสดงให้นายจ้างและแก้ไขส่งตรวจใหม่ได้
- สร้าง/แก้ไขงานและทักษะใน transaction; ใช้หมวดหมู่จาก DB; ปิดรับแทนลบงาน; แก้ได้เฉพาะงานของนายจ้างใน session
- ค้นหางาน Open ตามชื่อ สถานที่ ประเภท ค่าจ้าง พร้อมแบ่งหน้า และรายละเอียดตาม job_id จริง
- อ่านใบสมัครของนักศึกษาที่ล็อกอินแล้ว แสดงเวลาไทยและคงประวัติงานปิด
- ฟอร์มแก้ข้อมูลทุกตัวใช้ POST และ CSRF; query รับค่าผ่าน PDO placeholders; HTML escape ก่อนแสดงข้อมูล

## จุดเชื่อมกับเพื่อน

โอปอและเตอร์ยังต้องทำระบบยืนยันตัวตนนักศึกษา/สมัคร/บันทึกงานของตนเอง ไม่มีบัญชีนักศึกษาจำลองหรือการล็อกอินผ่านพารามิเตอร์ URL

หลังตรวจ Google/Microsoft ฝั่งเซิร์ฟเวอร์และสร้าง/ค้นหา STUDENT สำเร็จ ให้ใช้ bootstrap เดียวกัน และตั้ง session:

```php
session_regenerate_id(true);
$_SESSION = ['auth' => ['role' => 'student', 'id' => (int)$studentId]];
```

`$studentId` ต้องได้จากฐานข้อมูลที่ผูกกับบัญชีที่ยืนยันแล้ว ห้ามรับจากฟอร์มหรือ query string แล้วเชื่อถือโดยตรง การล็อกอินนายจ้างแทนที่ session เดิม จึงไม่มีสองบทบาทค้างพร้อมกัน

เตอร์สามารถใช้ `identity('student')`, `check_csrf()`, `db()` และ transaction ตาม database/team-guide.md เมื่อเชื่อมปุ่มใน job-detail.php; ปุ่มสมัคร/บันทึกยัง disabled จนกว่าจะมี handler จริง หน้าพิจารณาผู้สมัครยังเป็นงานของเตอร์

## ตรวจสอบ

```sh
php tests/php-validation.php
```

ทดสอบรวมบนฐานข้อมูลพัฒนา: สร้างนายจ้าง A/B, A สร้างงานพร้อมทักษะ, B เปลี่ยน job_id ในหน้าแก้ไขต้องไม่ได้ข้อมูลของ A, POST โดยไม่มี CSRF ต้องถูกปฏิเสธ, ค้นด้วยค่าจ้างผิดต้องไม่ query ข้อมูล, ปิดงานแล้วหายจากหน้าค้นหาแต่ใบสมัครเดิมยังอยู่ การสร้างบัญชีอีเมลซ้ำต้องได้ข้อความผิดพลาดโดยไม่เผย SQL

ก่อนเผยแพร่จริงให้ใช้ HTTPS และตั้ง rate limit การล็อกอินที่เว็บเซิร์ฟเวอร์ บริการ PHP development server ใช้พัฒนาในเครื่องเท่านั้น
