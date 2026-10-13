# การทำงานร่วมกันบน GitHub

มิกคือเจ้าของ repository `cxxipxt/Campus-jobs` ใช้บัญชี `@cxxipxt`
ทุกคนทำงานบน branch ของตัวเอง ส่ง Pull Request ให้มิกตรวจ ก่อนนำเข้า main

## สถานะการป้องกัน

- repository เป็น Public ตามที่มิกอนุญาต ทุกคนบนอินเทอร์เน็ตอ่านโค้ดได้
- มี `.github/CODEOWNERS` ระบุ `@cxxipxt` เป็นผู้ตรวจทุกไฟล์ รวมถึงไฟล์ CODEOWNERS
- main มี branch protection: ต้องส่ง PR, อนุมัติอย่างน้อย 1 คน, ผ่าน Code Owner และปิดบทสนทนาที่ค้างก่อนรวมงาน
- เพื่อนที่เป็น Collaborator สร้างและ push branch ได้ แต่ push เข้า main โดยตรงไม่ได้; หลังมิกอนุมัติและเงื่อนไขครบ ผู้มีสิทธิ์เขียนยังกด merge ได้
- ไม่อนุญาต force push หรือลบ main; การอนุมัติจะถูกยกเลิกเมื่อ PR มี commit ใหม่
- เจ้าของมีสิทธิ์ข้ามกฎ (enforce_admins = false) เพื่อจัดการ PR ของตนเอง ซึ่งไม่สามารถอนุมัติเองได้
- CODEOWNERS ระบุผู้ตรวจ ไม่ได้จำกัดว่าใครแก้ไฟล์ใดได้

## ครั้งแรก

มิกเชิญเพื่อนผ่าน Settings → Collaborators แล้วเพื่อนรับคำเชิญ
จากนั้นแต่ละคนรัน:

```bash
git clone https://github.com/cxxipxt/Campus-jobs.git
cd Campus-jobs
```

หากมี clone อยู่แล้ว ไม่ต้อง clone ซ้ำ

## เริ่มงานใหม่

บันทึกงานเดิมให้เรียบร้อยก่อนสลับ branch แล้วเริ่มจาก main ล่าสุด:

```bash
git switch main
git pull --ff-only origin main
git switch -c feature/mik-job-search
```

ชื่อ branch ตัวอย่าง:

| คน | งาน | branch |
|---|---|---|
| โอปอ | หน้าภาพรวม | `feature/opor-dashboard` |
| มิก | ค้นหางาน | `feature/mik-job-search` |
| เต้อ | Login นักศึกษา | `feature/ter-student-login` |

สร้าง branch ใหม่ต่อหนึ่งงานหรือฟีเจอร์ ไม่ใช้ branch เดียวร่วมกัน
แผนแบ่งหน้าเว็บล่าสุดอยู่ใน [frontend/README.md](../frontend/README.md)

## ส่งงานและเปิด PR

ตัวอย่างสำหรับมิก ปรับชื่อไฟล์และ branch ให้ตรงงานของแต่ละคน:

```bash
git add frontend/jobs.html frontend/job-detail.html
git commit -m "Update job search and detail pages"
git push -u origin feature/mik-job-search
```

บน GitHub กด Compare & pull request เลือก base = main และ compare = branch ของตัวเอง
ระบุสิ่งที่แก้และวิธีทดสอบ แล้วขอ review จากมิก
ถ้าต้องแก้ ให้ commit และ push ไป branch เดิม PR จะอัปเดตเอง

## ตรวจและรวมงาน

มิกตรวจ Files changed และลองเปิด HTML ก่อน Approve
ทีมตกลงให้มิกกด Squash and merge แล้วลบ branch ที่เสร็จแล้ว
กฎ Require review from Code Owners เมื่อเปิดแล้วจะบังคับให้มิกอนุมัติงานของคนอื่น แต่ผู้มีสิทธิ์เขียนยังสามารถกด merge หลังเงื่อนไขครบได้
PR ที่มิกเขียนเองให้เพื่อนตรวจ มิกอนุมัติ PR ตัวเองไม่ได้ หากกำหนดมิกเป็น Code Owner คนเดียวและบังคับกฎกับเจ้าของด้วย PR ของมิกจะติดเงื่อนไข ต้องวางสิทธิ์ bypass สำหรับเจ้าของหรือเปลี่ยนนโยบายผู้ตรวจให้เหมาะสม

หลังรวมงาน ทุกคนอัปเดต main ก่อนเริ่มงานใหม่:

```bash
git switch main
git pull --ff-only origin main
```

หาก branch ที่กำลังทำตามหลัง main ให้ commit งานให้เรียบร้อยแล้วรัน `git fetch origin` และ `git merge origin/main` บน branch นั้น
ถ้ามี conflict ให้ตรวจและแก้ไฟล์ที่เกี่ยวข้องก่อน commit อย่า force push เข้า main

## กฎที่เปิดไว้แล้ว

Settings → Branches → Add classic branch protection rule:

| ตัวเลือก | ค่า |
|---|---|
| Branch name pattern | `main` |
| Require a pull request before merging | เปิด |
| Require approvals | 1 |
| Require review from Code Owners | เปิด |
| Dismiss stale pull request approvals when new commits are pushed | เปิด |
| Require conversation resolution before merging | เปิด |
| Allow force pushes / Allow deletions | ปิด |

ไม่เพิ่มเพื่อนเป็นผู้ข้ามกฎ และยังไม่บังคับ status checks จนกว่าจะมีระบบทดสอบอัตโนมัติ
การเชิญคนเพิ่ม เปลี่ยน visibility หรือแพ็กเกจบัญชีต้องให้มิกตัดสินใจ
