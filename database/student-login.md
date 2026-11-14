# นักศึกษา: บัญชีมหาวิทยาลัยเดียวผ่านสองผู้ให้บริการ

ใช้ฐานข้อมูลพื้นฐาน v2 จำนวน 8 ตาราง และเพิ่ม STUDENT_OAUTH เป็นตารางที่ 9 ผ่าน Migration 005 เพื่อเชื่อมบัญชี Microsoft และ Google ของนักศึกษา
STUDENT.university_username เป็นส่วนก่อน @ ที่แปลงเป็นตัวพิมพ์เล็กและ UNIQUE
STUDENT.email เก็บอีเมลมหาวิทยาลัยเต็มของบัญชี ไม่มี password_hash หรือ university ใน STUDENT ตามแบบ v2
EMPLOYER ใช้ company_name และยังมี password_hash

## กฎที่ Backend ต้องบังคับ

| ผู้ให้บริการ | โดเมนอีเมลที่อนุญาต (ตรงตัว) |
|---|---|
| Microsoft | kmutt.ac.th |
| Google | mail.kmutt.ac.th |

1. ตรวจ token และบัญชีจาก provider ฝั่งเซิร์ฟเวอร์ก่อนอ่านอีเมล ไม่รับอีเมลที่กรอกเองเป็นหลักฐาน
2. ตรวจ issuer/audience/expiry และข้อมูลองค์กรตามผู้ให้บริการ รวมถึง tenant ของ KMUTT หรือ Google hosted domain ที่อนุญาต ต้องยืนยันค่าจริงก่อนใช้งาน
3. ตรวจโดเมนให้ตรง provider ตามตาราง ไม่ใช้แค่ endsWith และไม่รับ subdomain อื่นอัตโนมัติ
4. แยก university_username จากอีเมลที่ตรวจแล้ว ใช้ lowercase แบบ ASCII ตามข้อกำหนด v2
5. ค้น STUDENT ด้วย university_username ถ้าพบให้ใช้ student_id เดิม ไม่สร้างบัญชีอีกแถว แม้อีเมลจากอีก provider ต่างกัน
6. ถ้าไม่พบ ให้เก็บผลยืนยันบัญชีชั่วคราวใน session ฝั่ง Backend แล้วไป student/register.html; หลังยืนยันบัญชีครั้งแรกจึงสร้างบัญชีและไป Dashboard เพื่อกรอกโปรไฟล์
7. กรณีคำขอครั้งแรกมาพร้อมกัน ให้ UNIQUE ป้องกันซ้ำ ถ้า INSERT ชน duplicate key ให้ rollback แล้วค้นด้วย username เดิมอีกครั้ง ถ้าไม่พบต้องรายงาน conflict ไม่ใช้บัญชีจากอีเมลอื่นอัตโนมัติ
8. เมื่อนักศึกษายืนยันบัญชีที่ผ่าน SSO แล้ว ให้ INSERT STUDENT ใน transaction ด้วย username/อีเมลจาก session ที่ยืนยันแล้ว ตั้ง profile_completed เป็น 0 และสร้าง session ของนักศึกษาเพื่อไป dashboard.html; ต้องบันทึกโปรไฟล์ผ่านฟอร์มบน Dashboard ก่อนใช้หน้าอื่น ครั้งถัดไปค้นบัญชีเดิมด้วย university_username
9. ระหว่างกรอกครั้งแรกใช้สถานะยืนยันตัวตนชั่วคราวฝั่ง Backend ไม่รับอีเมล/username จากฟอร์มเป็นหลักฐาน หลังสร้างบัญชีแล้วใช้ student_id จาก session สำหรับแก้โปรไฟล์ ใช้ parameterized queries ทุกครั้ง

Repository มีการพัฒนา Backend สำหรับ Microsoft และ Google OAuth แล้ว แต่ยังต้องทดสอบการติดตั้งจาก Fresh checkout, Composer dependencies, Migration 005 และการเข้าสู่ระบบจากสภาพแวดล้อมใหม่ก่อนยืนยันว่าพร้อมใช้งาน
การพบชื่อก่อน @ ตรงกันจะถือเป็นคนเดียวกันตามนโยบายโปรเจกต์ แต่ต้องยืนยันกับ KMUTT ว่ารหัสนี้ไม่ซ้ำข้ามบุคคล/ไม่ถูกนำกลับมาใช้ก่อนเปิดระบบจริง
บัญชีบุคลากรอาจใช้โดเมนเดียวกัน ต้องกำหนดการตรวจสถานะนักศึกษาหากต้องการจำกัดเฉพาะนักศึกษาปัจจุบัน

## ติดตั้งและอัปเกรด

# ฐานข้อมูลใหม่ (Fresh Database)
ดำเนินการตามลำดับดังนี้

1. สร้างฐานข้อมูลและเลือกฐานข้อมูลที่จะใช้งาน

2. รัน database/schema.sql เพื่อสร้างตารางพื้นฐาน v2 จำนวน 8 ตาราง

3. รัน database/seed.sql เพื่อเพิ่มข้อมูลเริ่มต้น

4. รัน database/verify.sql เพื่อตรวจสอบโครงสร้างฐานข้อมูลพื้นฐานทั้ง 8 ตาราง

5. รัน database/migrations/005_student_oauth.sql เพื่อสร้างตาราง STUDENT_OAUTH เป็นตารางที่ 9 สำหรับเชื่อมบัญชี Microsoft และ Google

ไม่ต้องรัน Migration 001 กับฐานข้อมูลใหม่ เพราะ Migration ดังกล่าวใช้สำหรับอัปเกรดฐานข้อมูล v1 เท่านั้น
ฐานข้อมูลใหม่มีคอลัมน์ `STUDENT.profile_completed` จาก schema.sql อยู่แล้ว จึงไม่ต้องรัน Migration 006

# ฐานข้อมูล v1 ที่มีอยู่

สำรองทั้งโครงสร้างและข้อมูล → หยุดการเขียนข้อมูล → รัน database/migrations/preflight.sql → แก้ไขรายการที่พบ → รัน database/migrations/001_student_university_login.sql → database/migrations/005_student_oauth.sql → database/migrations/006_student_profile_completion.sql → ตรวจสอบด้วย database/verify.sql

# ข้อควรระวังในการ Migration

- Preflight ทุก query ควรคืนศูนย์แถว หากพบ student_id มากกว่าหนึ่งรายการที่ใช้ username เดียวกัน ให้ตรวจสอบข้อมูลและความสัมพันธ์ก่อนรวมด้วย migration เฉพาะกรณี ห้ามลบใบสมัครหรือแก้ IDs เงียบ ๆ

- Migration เป็นการดำเนินการที่ต้องทำตามลำดับและรันเพียงครั้งเดียว MySQL DDL ไม่สามารถ rollback ทั้งไฟล์ได้ หากเกิดข้อผิดพลาดให้หยุด ตรวจสอบคำสั่งที่สำเร็จแล้ว และดำเนินต่อเฉพาะคำสั่งที่จำเป็น ห้ามรันตั้งแต่ต้นซ้ำ

- ก่อนลบคอลัมน์ legacy ให้เก็บ backup ไว้ส่วนตัว ห้าม commit dump ที่มีรหัสผ่านหรือข้อมูลผู้ใช้ลง GitHub Public

- verify.sql ตรวจสอบตารางพื้นฐาน v2 จำนวน 8 ตาราง ไม่ได้ตรวจสอบ STUDENT_OAUTH ซึ่งต้องตรวจแยกหลังรัน Migration 005

- การติดตั้งและทดสอบจาก Fresh checkout รวมถึง Composer dependencies, Migration 005 และการเข้าสู่ระบบ Microsoft/Google ยังต้องผ่านการทดสอบก่อนยืนยันว่าพร้อมใช้งานจริง

- ภาพ ER เดิมยังเป็นแบบก่อน v2 ให้ใช้ schema.sql และ Data Dictionary เป็นข้อกำหนดล่าสุด

อ้างอิง [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) และ [Microsoft token validation](https://learn.microsoft.com/en-us/entra/identity-platform/access-tokens)
