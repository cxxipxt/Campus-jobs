# นักศึกษา: บัญชีมหาวิทยาลัยเดียวผ่านสองผู้ให้บริการ

ใช้ 8 ตารางเดิม ไม่มีตารางบัญชี provider หรือโดเมนเพิ่มเติม
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
6. ถ้าไม่พบ ให้เก็บผลยืนยันบัญชีชั่วคราวใน session ฝั่ง Backend แล้วไป student/register.html โดยยังไม่ INSERT ถ้า provider ส่งชื่อมาให้เติมในฟอร์มเพื่อให้นักศึกษาตรวจและยืนยัน
7. กรณีคำขอครั้งแรกมาพร้อมกัน ให้ UNIQUE ป้องกันซ้ำ ถ้า INSERT ชน duplicate key ให้ rollback แล้วค้นด้วย username เดิมอีกครั้ง ถ้าไม่พบต้องรายงาน conflict ไม่ใช้บัญชีจากอีเมลอื่นอัตโนมัติ
8. เมื่อนักศึกษายืนยันชื่อและโปรไฟล์แล้ว ตรวจ full_name ว่าไม่ว่าง จากนั้น INSERT STUDENT ใน transaction ด้วย username/อีเมลจาก session ที่ยืนยันแล้ว สร้าง session ของนักศึกษาและไป dashboard.html ครั้งถัดไปค้นบัญชีเดิมด้วย university_username
9. ระหว่างกรอกครั้งแรกใช้สถานะยืนยันตัวตนชั่วคราวฝั่ง Backend ไม่รับอีเมล/username จากฟอร์มเป็นหลักฐาน หลังสร้างบัญชีแล้วใช้ student_id จาก session สำหรับแก้โปรไฟล์ ใช้ parameterized queries ทุกครั้ง

นี่เป็นข้อกำหนดสำหรับ Backend ซึ่งยังไม่มีใน repository ไม่ใช่ระบบล็อกอินที่พร้อมใช้งาน
การพบชื่อก่อน @ ตรงกันจะถือเป็นคนเดียวกันตามนโยบายโปรเจกต์ แต่ต้องยืนยันกับ KMUTT ว่ารหัสนี้ไม่ซ้ำข้ามบุคคล/ไม่ถูกนำกลับมาใช้ก่อนเปิดระบบจริง
บัญชีบุคลากรอาจใช้โดเมนเดียวกัน ต้องกำหนดการตรวจสถานะนักศึกษาหากต้องการจำกัดเฉพาะนักศึกษาปัจจุบัน

## ติดตั้งและอัปเกรด

- ฐานข้อมูลใหม่: schema.sql v2 → seed.sql → verify.sql ไม่รัน migration 001 ซ้ำ
- ฐานข้อมูล v1 ที่มีอยู่: สำรองทั้งโครงและข้อมูล → หยุดการเขียน → migrations/preflight.sql → แก้รายการที่พบ → migrations/001_student_university_login.sql → verify.sql
- preflight ทุก query ควรคืนศูนย์แถว ถ้ามีสอง student_id ที่ใช้ username เดียวกัน ให้ตรวจข้อมูลและความสัมพันธ์ก่อนรวมด้วย migration เฉพาะกรณี ห้ามลบใบสมัครหรือแก้ IDs เงียบ ๆ
- migration เป็นแบบรันครั้งเดียว MySQL DDL rollback ทั้งไฟล์ไม่ได้ ถ้าเกิดข้อผิดพลาดให้หยุด ตรวจขั้นที่สำเร็จ แล้วดำเนินต่อเฉพาะคำสั่งที่จำเป็น ไม่รันตั้งแต่ต้นซ้ำ
- ก่อนลบคอลัมน์ legacy ให้เก็บ backup ไว้ส่วนตัว ไม่ commit dump ที่มีรหัสผ่าน/ข้อมูลผู้ใช้ลง GitHub Public
- ยังไม่ได้รัน migration กับ MySQL จริง และ ER ภาพเดิมยังเป็นแบบก่อน v2 ให้ใช้ schema และ Data Dictionary เป็นข้อกำหนดล่าสุด

อ้างอิง [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) และ [Microsoft token validation](https://learn.microsoft.com/en-us/entra/identity-platform/access-tokens)
