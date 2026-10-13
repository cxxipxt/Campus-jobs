# ฐานข้อมูล Campus-jobs
[Data Dictionary](data-dictionary.md) เป็นข้อกำหนดร่วมก่อนเขียน SQL

ทีมยืนยันใช้ MySQL แล้ว โครงล่าสุด v2 ใช้ MySQL 8.0.16 ขึ้นไป เพราะต้องบังคับ CHECK constraints
ทุกคนสร้างฐานข้อมูลพัฒนาบนเครื่องตัวเองด้วยไฟล์กลางชุดเดียวกัน ไม่แยก schema ตามคน
ยังไม่ได้ติดตั้งลงเซิร์ฟเวอร์ของทีม และยังไม่ได้ทดสอบกับ MySQL จริงในเครื่องที่จัดทำไฟล์

## ไฟล์ที่ใช้ร่วมกัน
- [schema.sql](schema.sql): สร้าง 8 ตาราง พร้อม PK, FK, UNIQUE, CHECK และ indexes
- [seed.sql](seed.sql): ประเภทงานเริ่มต้น 7 รายการ รันซ้ำได้ ไม่สร้างบัญชีผู้ใช้
- [verify.sql](verify.sql): อ่าน metadata เพื่อตรวจโครงสร้างหลังนำเข้า ไม่แก้ข้อมูล
- [แนวทางแบ่งงานและเชื่อมข้อมูล](team-guide.md): ผู้รับผิดชอบ ลำดับเตรียมข้อมูล และกฎ backend

## ติดตั้งบนเครื่องแต่ละคน
1. ตรวจเวอร์ชันด้วย `SELECT VERSION();` ต้องเป็น MySQL 8.0.16 ขึ้นไป ไม่ใช่ MariaDB ของ XAMPP
2. สร้างฐานข้อมูลใหม่ที่ยังว่าง โดยรันคำสั่งด้านล่างใน MySQL Workbench หรือ mysql client
3. เลือกฐานข้อมูลนั้นแล้วรัน schema.sql เพียงครั้งเดียว ตามด้วย seed.sql และ verify.sql
4. ให้ backend เชื่อมฐานข้อมูลนี้ และตั้ง session timezone เป็น UTC ทุก connection

```sql
CREATE DATABASE campus_jobs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_jobs;
```

ใน Workbench เปิดไฟล์ SQL แล้วสั่งรันทั้งไฟล์ โดยใช้ connection เดียวกับที่เลือกฐานข้อมูลไว้
หรือเปิด mysql client ด้วย `mysql -u root -p` แล้วรัน (แก้ path ถ้าเก็บโปรเจกต์ไว้ที่อื่น):

```sql
USE campus_jobs;
SOURCE C:/Users/chaip/OneDrive/Documents/ChatGPT/Webpro/Campus-jobs-github/database/schema.sql;
SOURCE C:/Users/chaip/OneDrive/Documents/ChatGPT/Webpro/Campus-jobs-github/database/seed.sql;
SOURCE C:/Users/chaip/OneDrive/Documents/ChatGPT/Webpro/Campus-jobs-github/database/verify.sql;
```

schema.sql ไม่ลบฐานข้อมูลและไม่ข้ามตารางเดิมเงียบ ๆ ถ้าชื่อซ้ำให้หยุดและตรวจ ห้ามรันต่อโดยใช้ `--force`
DDL ของ MySQL commit แยกแต่ละคำสั่ง หากเกิดข้อผิดพลาดบางตารางอาจถูกสร้างแล้ว อย่ารันซ้ำหรือ DROP ฐานข้อมูลที่มีข้อมูล ให้ตรวจและแก้เฉพาะจุด

## ข้อตกลงโครงล่าสุด v2
- ชื่อตารางใช้ตัวพิมพ์ใหญ่ตาม ER ทุกเครื่องและทุก query ต้องใช้ให้ตรงกัน โดยเฉพาะเมื่อย้ายไป Linux
- ID ทุกตัวเป็น INT UNSIGNED; PK ที่เป็น ID เดี่ยวเพิ่มอัตโนมัติ ห้ามกำหนด ID ใหม่เอง
- STUDENT ใช้ university_username แบบ UNIQUE ไม่มี password_hash หรือ university; full_name เป็น NOT NULL และห้ามว่าง ต้องมีชื่อก่อนสร้างนักศึกษา ส่วน EMPLOYER ใช้ company_name และบังคับ password_hash
- JOB บังคับรายละเอียด ค่าจ้าง สถานที่ และเวลางาน; ค่าจ้างเป็นบาทต่อชั่วโมงและห้ามติดลบ
- status บังคับตัวสะกดและตัวพิมพ์ตรงตาม scope; ไม่อนุญาตค่าอื่น
- ข้อความทั่วไปใช้ utf8mb4_unicode_ci: รองรับภาษาไทย เปรียบเทียบโดยไม่แยกตัวพิมพ์เล็ก/ใหญ่และอาจไม่แยกเครื่องหมายกำกับเสียง
- ตัดช่องว่างหัวท้ายอีเมลและชื่อทักษะใน backend ก่อนบันทึก; อีเมลซ้ำข้ามบทบาทได้ แต่ซ้ำในบทบาทเดียวกันไม่ได้
- ทุก connection ใช้ `SET SESSION time_zone = '+00:00';` ก่อนอ่าน/เขียนเวลา เก็บ UTC และแสดง Asia/Bangkok
- ใช้ strict SQL mode เช่น STRICT_TRANS_TABLES; ตรวจด้วย verify.sql เพื่อไม่ให้ข้อมูลผิดถูกแปลงเงียบ ๆ
- FK ใช้ RESTRICT ทั้งลบและแก้ ID เพื่อป้องกันข้อมูลอ้างอิงหาย ปิดงานด้วย status แทนลบงาน
- การตรวจเจ้าของข้อมูล รูปแบบอีเมล/URL การ hash รหัสผ่าน และงานเปิดรับ ต้องทำใน backend ด้วย SQL schema เพียงอย่างเดียวไม่บังคับกฎทั้งหมด

ตรวจ CHECK ของ MySQL ได้จาก [เอกสารทางการ](https://dev.mysql.com/doc/refman/8.0/en/information-schema-check-constraints-table.html)

## ลำดับสร้างตาม FK
1. STUDENT, EMPLOYER, JOB_CATEGORY
2. JOB
3. APPLICATION, STUDENT_SKILL, JOB_SKILL, SAVED_JOB

## การเปลี่ยนโครงสร้าง
หลังเริ่มใช้งาน ให้เพิ่มไฟล์ migration มีเลขลำดับและบอกวิธีรัน ไม่แก้ข้อมูลจริงโดยลบสร้างใหม่
ข้อมูลตัวอย่างต้องเป็นข้อมูลสมมติและใส่ในไฟล์แยกจาก schema

## การลบ
ขอบเขตปัจจุบันปิดประกาศแทนลบประกาศที่มีใบสมัคร
ยังไม่มีฟังก์ชันลบบัญชี ต้องตกลงนโยบาย FK ก่อนเพิ่ม
การยกเลิกบันทึกงานลบเฉพาะ SAVED_JOB ไม่ลบ JOB

## การแปลง ER
skill วงรีสองชั้น -> STUDENT_SKILL
skills_required วงรีสองชั้น -> JOB_SKILL
ความสัมพันธ์บันทึกงาน M:N -> SAVED_JOB
saved_at เป็นเวลาในความสัมพันธ์บันทึกงาน (เพิ่มลงในภาพฉบับ repository นี้)


## อัปเกรดบัญชีนักศึกษาเป็น v2

ดู [student-login.md](student-login.md) สำหรับกฎ Microsoft/Google ขั้นตอนสร้างบัญชีและ migration จาก v1 ฐานข้อมูลใหม่รัน schema.sql v2 ได้เลย แต่ฐานข้อมูลเดิมต้องสำรองและรัน preflight ก่อน migration 001 ไม่ลบสร้างใหม่
