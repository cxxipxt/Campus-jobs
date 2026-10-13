# Data Dictionary — 8 ตาราง

โครงเริ่มต้น v1 อยู่ใน [schema.sql](schema.sql) สำหรับ MySQL 8.0.16 ขึ้นไป ชนิด INT ของ ID ทุกตัวเป็น INT UNSIGNED
PK และ FK ทุกคอลัมน์ต้องไม่เป็น NULL; email/password_hash และชื่อหลักต้องมีค่าและไม่เป็นข้อความว่าง
ฟิลด์เพิ่มเติมของ STUDENT (phone, university, major, resume_url) และ EMPLOYER (phone, address) อนุญาต NULL ใน v1
คอลัมน์อื่นทั้งหมดบังคับ NOT NULL; ฟิลด์ข้อความของประกาศและชื่อทักษะห้ามเป็นข้อความว่าง
status ใช้ VARCHAR กับ CHECK และเปรียบเทียบแบบแยกตัวพิมพ์เล็ก/ใหญ่
ข้อความทั่วไปใช้ utf8mb4_unicode_ci; backend ต้อง trim อีเมล/ทักษะก่อนบันทึก
FK ทุกตัวใช้ ON DELETE RESTRICT และ ON UPDATE RESTRICT ดูข้อตกลงเพิ่มเติมใน [README](README.md)

## STUDENT

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| student_id | INT | PK; เพิ่มอัตโนมัติ |
| full_name | VARCHAR(150) | ชื่อ–นามสกุล |
| email | VARCHAR(255) | UNIQUE ภายใน STUDENT |
| password_hash | VARCHAR(255) | รหัสผ่านที่แฮชแล้ว |
| phone | VARCHAR(20) | เบอร์โทร |
| university | VARCHAR(150) | มหาวิทยาลัย |
| major | VARCHAR(150) | สาขา |
| resume_url | VARCHAR(2048) | ลิงก์ Resume |

## EMPLOYER

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| employer_id | INT | PK; เพิ่มอัตโนมัติ |
| company_name | VARCHAR(150) | ชื่อบริษัท/ร้านค้า |
| email | VARCHAR(255) | UNIQUE ภายใน EMPLOYER |
| password_hash | VARCHAR(255) | รหัสผ่านที่แฮชแล้ว |
| phone | VARCHAR(20) | เบอร์โทร |
| address | TEXT | ที่อยู่ |

## JOB_CATEGORY

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| category_id | INT | PK; เพิ่มอัตโนมัติ |
| category_name | VARCHAR(100) | UNIQUE |

## JOB

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| job_id | INT | PK; เพิ่มอัตโนมัติ |
| employer_id | INT | FK -> EMPLOYER.employer_id; NOT NULL |
| category_id | INT | FK -> JOB_CATEGORY.category_id; NOT NULL |
| job_title | VARCHAR(150) | ชื่อตำแหน่ง |
| description | TEXT | รายละเอียด |
| hourly_wage | DECIMAL(10,2) | บาท/ชั่วโมง; >= 0 |
| location | VARCHAR(255) | สถานที่ |
| work_schedule | VARCHAR(255) | วัน/เวลางาน |
| status | VARCHAR(10) | Open / Closed; default Open |
| created_at | DATETIME | เวลาสร้าง; default เวลาปัจจุบัน |

## APPLICATION

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| application_id | INT | PK; เพิ่มอัตโนมัติ |
| student_id | INT | FK -> STUDENT.student_id; NOT NULL |
| job_id | INT | FK -> JOB.job_id; NOT NULL |
| applied_at | DATETIME | เวลาสมัคร; default เวลาปัจจุบัน |
| status | VARCHAR(20) | Pending / Reviewing / Accepted / Rejected; default Pending |

## STUDENT_SKILL

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| student_id | INT | FK -> STUDENT.student_id; ส่วนหนึ่งของ PK |
| skill_name | VARCHAR(100) | ส่วนหนึ่งของ PK; หนึ่งทักษะต่อแถว |

## JOB_SKILL

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| job_id | INT | FK -> JOB.job_id; ส่วนหนึ่งของ PK |
| skill_name | VARCHAR(100) | ส่วนหนึ่งของ PK; หนึ่งทักษะต่อแถว |

## SAVED_JOB

| Column | Type (เสนอ) | เงื่อนไข / ความหมาย |
|---|---|---|
| student_id | INT | FK -> STUDENT.student_id; ส่วนหนึ่งของ PK |
| job_id | INT | FK -> JOB.job_id; ส่วนหนึ่งของ PK |
| saved_at | DATETIME | เวลาบันทึก; default เวลาปัจจุบัน |

## คีย์และข้อกำหนดเพิ่มเติม
- APPLICATION: UNIQUE(student_id, job_id)
- STUDENT_SKILL: PRIMARY KEY(student_id, skill_name)
- JOB_SKILL: PRIMARY KEY(job_id, skill_name)
- SAVED_JOB: PRIMARY KEY(student_id, job_id)
- FK และ PK ที่อ้างกันต้องใช้ชนิดตรงกัน
- อีเมลซ้ำข้ามประเภทบัญชีได้ในแบบนี้ จึงต้องแยกบทบาทตอนล็อกอิน
- ตารางทักษะไม่มีรายการ SKILL กลาง; v1 ใช้ collation ที่ไม่แยกตัวพิมพ์เล็ก/ใหญ่ ต้องตกลงชื่อทักษะร่วมกัน
- ค่าเวลาเก็บ UTC (ตั้ง session timezone ทุก connection) และแสดง Asia/Bangkok
