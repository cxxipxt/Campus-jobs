# Campus-jobs — โครง HTML และแบ่งงานหน้าเว็บ

เปิด `index.html` ด้วย Chrome, Edge หรือ Firefox เพื่อดูโครงหน้า ไม่ต้องติดตั้งอะไรเพิ่ม
ยังไม่มี CSS, JavaScript, Backend หรือการเชื่อม MySQL ปุ่มที่ต้องประมวลผลปิดไว้ ข้อมูลทั้งหมดเป็นตัวอย่าง
ชุดนี้เป็นโครง HTML สำหรับตรวจและพัฒนาร่วมกันบน GitHub ยังไม่ใช่เว็บไซต์ที่เผยแพร่ใช้งานจริง

## หน้าหลักและผู้รับผิดชอบตามแผนล่าสุด

| หน้า | ไฟล์ | ผู้รับผิดชอบ |
|---|---|---|
| 1. Landing page | `index.html` | โอปอ |
| 2. เข้าสู่ระบบนักศึกษา | `student/login.html` | เต้อ |
| 2. เข้าสู่ระบบนายจ้าง | `employer/login.html` | เต้อ |
| 3. สมัครสมาชิก / เลือกบทบาท | `register.html` | โอปอ |
| 4. ค้นหางาน | `jobs.html` | มิก |
| 5. รายละเอียดงาน | `job-detail.html` | มิก |
| 6. การสมัครของฉัน | `my-applications.html` | มิก |
| 7. ภาพรวม | `dashboard.html` | โอปอ |
| CSS กลาง (แผนขั้นถัดไป ยังไม่สร้างไฟล์) | `css/style.css` | เต้อ |

`login.html` แยกเป็นสองไฟล์ในโฟลเดอร์ `student/` และ `employer/` จึงไม่มีไฟล์ login รวม
เปลี่ยนชื่อ `applications.html` เป็น `my-applications.html` และปรับลิงก์ทุกหน้าแล้ว
แผนนี้ระบุผู้รับผิดชอบหน้าเว็บ ส่วนการแบ่งตารางในเอกสารฐานข้อมูลยังเป็นข้อตกลงเดิม

## การเข้าสู่ระบบนักศึกษา

- หน้า `student/login.html` มีเพียงปุ่มเลือก Microsoft หรือ Google ใช้บัญชีอีเมลนักศึกษาของมหาวิทยาลัยเท่านั้น ไม่มีช่องอีเมลและรหัสผ่าน
- โดเมนที่มิกกำหนด: Microsoft ใช้ `@kmutt.ac.th` และ Google ใช้ `@mail.kmutt.ac.th` เท่านั้น ไม่รับอีเมลส่วนตัว
- ตรวจชื่อโดเมนหลัง @ แบบตรงตัวและไม่แยกตัวพิมพ์เล็ก/ใหญ่ ไม่รับโดเมนที่เพียงลงท้ายคล้ายกันหรือ subdomain อื่นโดยอัตโนมัติ
- ต้องยืนยัน tenant ID ของ KMUTT สำหรับ Microsoft และ hosted domain ที่ Google ส่งจริงก่อนตั้งค่า Backend; ไม่สมมติว่า hosted domain ตรงกับโดเมนอีเมลเสมอ
- ระบบจริงต้องตรวจบัญชีและข้อมูลยืนยันตัวตนฝั่ง Backend ก่อนสร้าง session ไม่ตัดสินจากข้อความที่ผู้ใช้กรอกหรือส่วนท้ายอีเมลเพียงอย่างเดียว
- Google: ตรวจ ID token และ hosted domain (hd) ขององค์กรที่อนุญาต; Microsoft: ตรวจ ID token และ tenant/issuer ของมหาวิทยาลัย รวมถึงเงื่อนไขบัญชีนักศึกษา
- อีเมลมหาวิทยาลัยอาจเป็นบัญชีบุคลากรด้วย ต้องกำหนดวิธีแยกนักศึกษาตามข้อมูลหรือโดเมนที่มหาวิทยาลัยใช้
- อ้างอิง [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) และ [Microsoft token validation](https://learn.microsoft.com/en-us/entra/identity-platform/access-tokens)
- ปุ่มยังไม่เชื่อมบริการยืนยันตัวตน และไม่ใช่การเข้าสู่ระบบจริง
- เส้นทางบัญชีใหม่ที่วางไว้: `register.html` → `student/login.html` → `student/register.html` → `dashboard.html`
- `student/register.html` เป็นฟอร์มยืนยันชื่อและข้อมูลโปรไฟล์ครั้งแรก ไม่มีฟิลด์ตั้งรหัสผ่าน ยืนยันแล้วจึง INSERT STUDENT และไป Dashboard
- เมื่อทำระบบจริง อีเมลและข้อมูลบัญชีต้องมาจากผู้ให้บริการที่ยืนยันแล้ว ไม่ใช้ค่าจากฟอร์มเป็นหลักฐานยืนยันตัวตน
- Schema v2 ใช้ STUDENT.university_username แบบ UNIQUE แทนรหัสผ่านนักศึกษา ใช้ 8 ตารางเดิม และมี migration จาก v1 ดู [ขั้นตอนล็อกอินนักศึกษา](../database/student-login.md)
- นายจ้างยังใช้โครงฟอร์มอีเมล/รหัสผ่านเดิม

## โครงสร้างปัจจุบัน

```text
frontend/
├── README.md
├── index.html                  โอปอ
├── register.html               โอปอ
├── jobs.html                   มิก
├── job-detail.html             มิก
├── my-applications.html        มิก
├── dashboard.html              โอปอ
├── saved.html                  หน้าเสริมเดิม
├── student/
│   ├── login.html              เต้อ (Microsoft / Google)
│   ├── register.html           ฟอร์มข้อมูลครั้งแรก
│   └── profile.html            หน้าเสริมเดิม
└── employer/
    ├── login.html              เต้อ
    ├── register.html           ฟอร์มสมัครนายจ้าง
    ├── profile.html            หน้าเสริมเดิม
    ├── jobs.html               หน้าเสริมเดิม
    ├── job-form.html           หน้าเสริมเดิม
    └── applicants.html         หน้าเสริมเดิม
```

แผน CSS ขั้นถัดไป: เต้อสร้าง `css/style.css` ให้ทุกหน้าใช้ร่วมกัน รอบนี้ยังไม่มีโฟลเดอร์หรือไฟล์ CSS และยังไม่มีการอ้างอิง stylesheet
หน้าเสริมเดิมคงไว้สำหรับตรวจฟีเจอร์ โปรดตกลงผู้รับผิดชอบเพิ่มเติมก่อนแบ่งพัฒนาหน้าเหล่านั้น

## ตรวจโครงหน้า

1. เปิดหน้าหลักแล้วลองเมนูเข้าสู่ระบบทั้งสองบทบาท
2. ตรวจหน้าเลือกสมัครสมาชิกและฟอร์มข้อมูลครั้งแรก
3. ตรวจเส้นทางค้นหางาน → รายละเอียดงาน → การสมัครของฉัน → ภาพรวม
4. ตรวจชื่อฟิลด์และข้อมูลที่แต่ละหน้าต้องแสดง

ทุกหน้าใช้ UTF-8, `lang="th"` และ HTML เชิงความหมาย พร้อม label ของฟอร์ม ลิงก์เปิดได้โดยไม่ใช้ JavaScript
Backend ต้องตรวจเจ้าของข้อมูลและกฎการสมัครงานเมื่อพัฒนาระบบจริง
อ่าน [ขอบเขตระบบ](../docs/scope.md), [Data Dictionary](../database/data-dictionary.md) และ [แนวทางฐานข้อมูล](../database/team-guide.md)