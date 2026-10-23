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
