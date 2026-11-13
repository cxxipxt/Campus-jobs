-- ==========================================
-- Campus-jobs: APPLICATION & SAVED_JOB Tables
-- Created / Maintained by: Ter
-- ==========================================
SET NAMES utf8mb4;
SET SESSION time_zone = '+00:00';


CREATE TABLE APPLICATION (
    application_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (application_id),
    CONSTRAINT uq_application_student_job UNIQUE (student_id, job_id),
    KEY idx_application_job_status (job_id, status),
    CONSTRAINT fk_application_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_application_job FOREIGN KEY (job_id) REFERENCES JOB (job_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_application_status CHECK (status IN ('Pending', 'Reviewing', 'Accepted', 'Rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE SAVED_JOB (
    student_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, job_id),
    KEY idx_saved_job_job (job_id),
    CONSTRAINT fk_saved_job_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_saved_job_job FOREIGN KEY (job_id) REFERENCES JOB (job_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- คำสั่งสำหรับ API: บันทึกงาน (Saved Jobs)
INSERT IGNORE INTO SAVED_JOB (student_id, job_id) 
VALUES (?, ?);

-- คำสั่งสำหรับ API: สมัครงาน (Applications)
START TRANSACTION;
INSERT IGNORE INTO APPLICATION (student_id, job_id, status)
SELECT ?, ?, 'Pending'
FROM JOB
WHERE job_id = ? AND status = 'Open'
FOR UPDATE;
COMMIT;


-- ==========================================
-- 1. ฟีเจอร์: ยกเลิกบันทึกงาน (Unsave Job)
-- ==========================================
DELETE FROM SAVED_JOB 
WHERE student_id = ? AND job_id = ?;

-- ==========================================
-- 2. ฟีเจอร์: ดึงรายการงานที่บันทึกไว้ (Saved Jobs Page)
-- ==========================================
SELECT j.job_id, j.job_title, j.hourly_wage, j.location, sj.saved_at 
FROM JOB j
JOIN SAVED_JOB sj ON j.job_id = sj.job_id
WHERE sj.student_id = ?
ORDER BY sj.saved_at DESC;

-- ==========================================
-- 3. ฟีเจอร์: นายจ้างดูผู้สมัครเฉพาะงานของตัวเอง (Employer Applicants)
-- ==========================================
SELECT a.application_id, a.applied_at, a.status AS application_status, 
       s.full_name, s.email, s.resume_url, 
       j.job_title 
FROM APPLICATION a
JOIN STUDENT s ON a.student_id = s.student_id
JOIN JOB j ON a.job_id = j.job_id
WHERE j.employer_id = ?
ORDER BY a.applied_at DESC;

-- ==========================================
-- 4. ฟีเจอร์: นายจ้างเปลี่ยนสถานะใบสมัคร (Update Application Status)
-- ==========================================
-- สถานะที่ยอมรับ: 'Pending', 'Reviewed', 'Accepted', 'Rejected'
UPDATE APPLICATION 
SET status = ? 
WHERE application_id = ?;