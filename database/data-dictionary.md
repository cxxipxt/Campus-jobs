# Data Dictionary v2 — 8 ตาราง

ใช้ MySQL 8.0.16 ขึ้นไป ตาม [schema.sql](schema.sql) สำหรับฐานข้อมูลใหม่
อัปเกรดฐานข้อมูลเดิมตาม [student-login.md](student-login.md) ด้วย migration 001 ไม่รัน schema ใหม่ทับฐานข้อมูลที่มีข้อมูล

STUDENT ใช้แบบภาพล่าสุด: student_id, university_username, full_name, email, phone, major, resume_url ไม่มี password_hash หรือ university
ตารางอื่นใช้ constraints ที่มิกกำหนด รวมถึง EMPLOYER.company_name

## STUDENT

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `student_id` | INT UNSIGNED | PK, AUTO_INCREMENT, NOT NULL; ระบบสร้าง ID บวก |
| `university_username` | VARCHAR(64), ASCII binary | NOT NULL, UNIQUE; ส่วนก่อน @, lowercase, trim, ห้ามว่างหรือมี @; Backend ได้จากอีเมลที่ยืนยันแล้ว |
| `full_name` | VARCHAR(150) | NOT NULL, CHECK ห้ามว่าง |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE ภายใน STUDENT, CHECK ห้ามว่าง; Backend ตรวจรูปแบบ/ผู้ให้บริการ/โดเมน |
| `phone` | VARCHAR(20) | NULL; CHECK ถ้ามีห้ามว่าง; Backend ตรวจรูปแบบเบอร์โทร |
| `major` | VARCHAR(150) | NULL; CHECK ถ้ามีห้ามว่าง |
| `resume_url` | VARCHAR(2048) | NULL; CHECK เริ่มด้วย http:// หรือ https://; Backend ตรวจ URL เต็ม |

## EMPLOYER

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `employer_id` | INT UNSIGNED | PK, AUTO_INCREMENT, NOT NULL; ระบบสร้าง ID บวก |
| `company_name` | VARCHAR(150) | NOT NULL, CHECK ห้ามว่าง; ไม่ UNIQUE |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE ภายใน EMPLOYER, CHECK ห้ามว่าง; Backend ตรวจรูปแบบ |
| `password_hash` | VARCHAR(255) | NOT NULL, CHECK ห้ามว่าง; Backend สร้างด้วยไลบรารีแฮชรหัสผ่านจริง |
| `phone` | VARCHAR(20) | NULL; CHECK ถ้ามีห้ามว่าง; Backend ตรวจรูปแบบ |
| `address` | TEXT | NULL; CHECK ถ้ามีห้ามว่าง |

## JOB_CATEGORY

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `category_id` | INT UNSIGNED | PK, AUTO_INCREMENT, NOT NULL; ระบบสร้าง ID บวก |
| `category_name` | VARCHAR(100) | NOT NULL, UNIQUE, CHECK ห้ามว่าง |

## JOB

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `job_id` | INT UNSIGNED | PK, AUTO_INCREMENT, NOT NULL; ระบบสร้าง ID บวก |
| `employer_id` | INT UNSIGNED | NOT NULL, FK → EMPLOYER.employer_id |
| `category_id` | INT UNSIGNED | NOT NULL, FK → JOB_CATEGORY.category_id |
| `job_title` | VARCHAR(150) | NOT NULL, CHECK ห้ามว่าง |
| `description` | TEXT | NOT NULL, CHECK ห้ามว่าง |
| `hourly_wage` | DECIMAL(10,2) | NOT NULL, CHECK >= 0; บาท/ชั่วโมง |
| `location` | VARCHAR(255) | NOT NULL, CHECK ห้ามว่าง |
| `work_schedule` | VARCHAR(255) | NOT NULL, CHECK ห้ามว่าง |
| `status` | VARCHAR(10), ASCII binary | NOT NULL, DEFAULT 'Open', CHECK เฉพาะ Open / Closed |
| `created_at` | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP; Backend ไม่รับเวลาที่ผู้ใช้กำหนด |

## APPLICATION

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `application_id` | INT UNSIGNED | PK, AUTO_INCREMENT, NOT NULL; ระบบสร้าง ID บวก |
| `student_id` | INT UNSIGNED | NOT NULL, FK → STUDENT.student_id |
| `job_id` | INT UNSIGNED | NOT NULL, FK → JOB.job_id |
| `applied_at` | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP; ระบบกำหนด |
| `status` | VARCHAR(20), ASCII binary | NOT NULL, DEFAULT 'Pending', CHECK เฉพาะ Pending / Reviewing / Accepted / Rejected |

UNIQUE(student_id, job_id): สมัครงานเดิมซ้ำไม่ได้ แม้ใบสมัครเดิมถูกปฏิเสธ

## STUDENT_SKILL

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `student_id` | INT UNSIGNED | NOT NULL, ส่วนหนึ่งของ PK, FK → STUDENT.student_id |
| `skill_name` | VARCHAR(100) | NOT NULL, ส่วนหนึ่งของ PK, CHECK ห้ามว่าง; หนึ่งทักษะต่อแถว |

PRIMARY KEY(student_id, skill_name); ไม่ UNIQUE แยกแต่ละคอลัมน์

## JOB_SKILL

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `job_id` | INT UNSIGNED | NOT NULL, ส่วนหนึ่งของ PK, FK → JOB.job_id |
| `skill_name` | VARCHAR(100) | NOT NULL, ส่วนหนึ่งของ PK, CHECK ห้ามว่าง; หนึ่งทักษะต่อแถว |

PRIMARY KEY(job_id, skill_name); ไม่ UNIQUE แยกแต่ละคอลัมน์

## SAVED_JOB

| Attribute | Type | Constraints / การตรวจสอบ |
|---|---|---|
| `student_id` | INT UNSIGNED | NOT NULL, ส่วนหนึ่งของ PK, FK → STUDENT.student_id |
| `job_id` | INT UNSIGNED | NOT NULL, ส่วนหนึ่งของ PK, FK → JOB.job_id |
| `saved_at` | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP; ระบบกำหนด |

PRIMARY KEY(student_id, job_id): บันทึกงานเดิมซ้ำไม่ได้

## ข้อกำหนดร่วม

- FK ทั้ง 8 ใช้ ON DELETE RESTRICT / ON UPDATE RESTRICT; ชนิดคอลัมน์อ้างอิงต้องตรงกัน
- PK, UNIQUE, NOT NULL, CHECK และ FK บังคับใน MySQL; ใช้ strict mode เพื่อไม่แปลงค่าผิดเงียบ ๆ
- ID ใช้ AUTO_INCREMENT และ UNSIGNED; Backend ไม่รับ ID ใหม่จากผู้ใช้ และต้องตรวจ ID ที่อ้างอิงเป็นจำนวนเต็ม > 0 ห้ามเปิด NO_AUTO_VALUE_ON_ZERO ใน connection ที่สร้างข้อมูล
- MySQL ไม่รองรับ CHECK บนคอลัมน์ AUTO_INCREMENT จึงไม่ใส่ CHECK(id > 0) ที่ทำให้ schema รันไม่ได้ ดู [เอกสาร MySQL](https://dev.mysql.com/doc/refman/8.0/en/numeric-type-attributes.html)
- อีเมล UNIQUE แยก STUDENT/EMPLOYER จึงซ้ำข้ามบทบาทได้
- Backend ตรวจรูปแบบอีเมล เบอร์โทร URL และตรวจว่า password_hash มาจากไลบรารีจริง SQL ไม่ยืนยัน token หรือความถูกต้องของ hash
- Backend trim ข้อความ optional แล้วเก็บ NULL ถ้าเว้นว่าง; ไม่ใช้ข้อความว่างแทน NULL
- ข้อความทั่วไปใช้ utf8mb4_unicode_ci; ชื่อทักษะไม่แยกตัวพิมพ์เล็ก/ใหญ่ ส่วน username และ status ใช้ binary collation
- ไม่ให้ผู้ใช้กำหนด created_at/applied_at/saved_at ใน Backend เก็บ UTC โดยตั้ง timezone ทุก connection และแสดง Asia/Bangkok
- skills_required ไม่อยู่ใน JOB เพราะแยก JOB_SKILL แล้ว
- ใช้ university_username เป็นคนเดียวกันข้ามผู้ให้บริการตามนโยบายที่ต้องยืนยันกับ KMUTT ไม่ใช้ชื่อจากฟอร์มเป็นหลักฐาน
- full_name บังคับ NOT NULL: ถ้า provider ไม่ส่งชื่อ ต้องกรอกชื่อก่อน INSERT; ข้อมูลที่ยืนยันชั่วคราวเก็บใน session ของ Backend ไม่ต้องเพิ่มตาราง

## สถานะตรวจสอบ

ไฟล์ผ่านการตรวจโครงสร้างเบื้องต้น ยังไม่ได้รัน MySQL หรือ migration จริง และภาพ ER เก่ายังไม่ได้ปรับตาม v2
