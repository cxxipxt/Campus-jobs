-- Campus-jobs baseline v2. MySQL >= 8.0.16, InnoDB.
-- Run once against a NEW, EMPTY development database selected by the caller.
-- Deliberately no DROP, CREATE DATABASE, USE, or IF NOT EXISTS.
-- MySQL DDL auto-commits: stop on the first error and inspect partial results.
SET NAMES utf8mb4;
SET SESSION time_zone = '+00:00';

CREATE TABLE STUDENT (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    university_username VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    major VARCHAR(150) NULL,
    resume_url VARCHAR(2048) NULL,
    profile_completed TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (student_id),
    CONSTRAINT uq_student_university_username UNIQUE (university_username),
    CONSTRAINT uq_student_email UNIQUE (email),
    CONSTRAINT chk_student_name CHECK (CHAR_LENGTH(TRIM(full_name)) > 0),
    CONSTRAINT chk_student_email CHECK (CHAR_LENGTH(TRIM(email)) > 0),
    CONSTRAINT chk_student_username CHECK (
        CHAR_LENGTH(university_username) > 0
        AND university_username = LOWER(university_username)
        AND university_username = TRIM(university_username)
        AND LOCATE('@', university_username) = 0
    ),
    CONSTRAINT chk_student_phone CHECK (phone IS NULL OR CHAR_LENGTH(TRIM(phone)) > 0),
    CONSTRAINT chk_student_major CHECK (major IS NULL OR CHAR_LENGTH(TRIM(major)) > 0),
    CONSTRAINT chk_student_resume CHECK (resume_url IS NULL OR REGEXP_LIKE(resume_url, '^https?://', 'c')),
    CONSTRAINT chk_student_profile_completed CHECK (profile_completed IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE EMPLOYER (
    employer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NULL,
    company_name VARCHAR(150) NULL,
    business_type VARCHAR(100) NULL,
    business_type_other VARCHAR(100) NULL,
    contact_email VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    google_subject VARBINARY(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    verification_status VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Draft',
    review_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    rejection_reason VARCHAR(1000) NULL,
    verified_at DATETIME NULL,
    PRIMARY KEY (employer_id),
    CONSTRAINT uq_employer_username UNIQUE (username),
    CONSTRAINT uq_employer_email UNIQUE (email),
    CONSTRAINT uq_employer_google_subject UNIQUE (google_subject),
    KEY idx_employer_verification_status (verification_status, employer_id),
    CONSTRAINT chk_employer_name CHECK (CHAR_LENGTH(TRIM(company_name)) > 0),
    CONSTRAINT chk_employer_email CHECK (CHAR_LENGTH(TRIM(email)) > 0),
    CONSTRAINT chk_employer_hash CHECK (CHAR_LENGTH(TRIM(password_hash)) > 0),
    CONSTRAINT chk_employer_phone CHECK (phone IS NULL OR CHAR_LENGTH(TRIM(phone)) > 0),
    CONSTRAINT chk_employer_address CHECK (address IS NULL OR CHAR_LENGTH(TRIM(address)) > 0),
    CONSTRAINT chk_employer_verification_status CHECK (verification_status IN ('Draft', 'Pending', 'Verified', 'Rejected')),
    CONSTRAINT chk_employer_pending_profile_complete CHECK (
        verification_status <> 'Pending' OR (
            company_name IS NOT NULL AND CHAR_LENGTH(TRIM(company_name)) > 0
            AND business_type IS NOT NULL AND CHAR_LENGTH(TRIM(business_type)) > 0
            AND (business_type <> 'อื่น ๆ' OR
                 (business_type_other IS NOT NULL AND CHAR_LENGTH(TRIM(business_type_other)) > 0))
            AND phone IS NOT NULL AND CHAR_LENGTH(TRIM(phone)) > 0
            AND contact_email IS NOT NULL AND CHAR_LENGTH(TRIM(contact_email)) > 0
            AND address IS NOT NULL AND CHAR_LENGTH(TRIM(address)) > 0
        )
    ),
    CONSTRAINT chk_employer_rejection_reason CHECK (
        (verification_status = 'Rejected' AND rejection_reason IS NOT NULL AND CHAR_LENGTH(TRIM(rejection_reason)) > 0)
        OR (verification_status <> 'Rejected' AND rejection_reason IS NULL)
    ),
    CONSTRAINT chk_employer_verified_at CHECK (
        (verification_status = 'Verified' AND verified_at IS NOT NULL)
        OR (verification_status <> 'Verified' AND verified_at IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE JOB_CATEGORY (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (category_id),
    CONSTRAINT uq_category_name UNIQUE (category_name),
    CONSTRAINT chk_category_name CHECK (CHAR_LENGTH(TRIM(category_name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE JOB (
    job_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employer_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    job_title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    hourly_wage DECIMAL(10,2) NOT NULL,
    location VARCHAR(255) NOT NULL,
    work_schedule VARCHAR(255) NOT NULL,
    hours_per_day DECIMAL(4,2) NULL,
    CONSTRAINT chk_job_hours CHECK (hours_per_day IS NULL OR (hours_per_day > 0 AND hours_per_day <= 24)),
    status VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_job_employer (employer_id),
    KEY idx_job_category_status_wage (category_id, status, hourly_wage),
    KEY idx_job_status_created (status, created_at),
    CONSTRAINT fk_job_employer FOREIGN KEY (employer_id) REFERENCES EMPLOYER (employer_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_job_category FOREIGN KEY (category_id) REFERENCES JOB_CATEGORY (category_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_job_title CHECK (CHAR_LENGTH(TRIM(job_title)) > 0),
    CONSTRAINT chk_job_description CHECK (CHAR_LENGTH(TRIM(description)) > 0),
    CONSTRAINT chk_job_location CHECK (CHAR_LENGTH(TRIM(location)) > 0),
    CONSTRAINT chk_job_schedule CHECK (CHAR_LENGTH(TRIM(work_schedule)) > 0),
    CONSTRAINT chk_job_wage CHECK (hourly_wage >= 0),
    CONSTRAINT chk_job_status CHECK (status IN ('Open', 'Closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE STUDENT_SKILL (
    student_id INT UNSIGNED NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (student_id, skill_name),
    CONSTRAINT fk_student_skill_student FOREIGN KEY (student_id) REFERENCES STUDENT (student_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_student_skill_name CHECK (CHAR_LENGTH(TRIM(skill_name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE JOB_SKILL (
    job_id INT UNSIGNED NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (job_id, skill_name),
    CONSTRAINT fk_job_skill_job FOREIGN KEY (job_id) REFERENCES JOB (job_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_job_skill_name CHECK (CHAR_LENGTH(TRIM(skill_name)) > 0)
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

ALTER TABLE STUDENT
    ADD COLUMN study_year TINYINT UNSIGNED NULL,
    ADD COLUMN activities TEXT NULL,
    ADD COLUMN experience TEXT NULL,
    ADD COLUMN portfolio_url VARCHAR(2048) NULL,
    ADD COLUMN languages TEXT NULL,
    ADD COLUMN certificates TEXT NULL,
    ADD COLUMN preferred_area VARCHAR(150) NULL,
    ADD COLUMN min_wage DECIMAL(10,2) UNSIGNED NULL;

CREATE TABLE STUDENT_AVAILABILITY (
    student_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY (student_id, weekday),
    FOREIGN KEY (student_id) REFERENCES STUDENT(student_id) ON DELETE CASCADE,
    CHECK (weekday BETWEEN 1 AND 7),
    CHECK (start_time < end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE JOB_SKILL ADD COLUMN skill_type ENUM('required','preferred') NOT NULL DEFAULT 'required';
ALTER TABLE JOB ADD COLUMN days_per_week TINYINT UNSIGNED NULL,
 ADD CONSTRAINT chk_job_days_per_week CHECK (days_per_week BETWEEN 1 AND 7);
CREATE TABLE JOB_SHIFT (
 job_id INT UNSIGNED NOT NULL,
 weekday TINYINT UNSIGNED NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 PRIMARY KEY(job_id,weekday),
 CONSTRAINT fk_job_shift_job FOREIGN KEY(job_id) REFERENCES JOB(job_id) ON DELETE CASCADE,
 CONSTRAINT chk_job_shift_day CHECK(weekday BETWEEN 1 AND 7),
 CONSTRAINT chk_job_shift_times CHECK(start_time < end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE JOB ADD COLUMN subcategory VARCHAR(100) NULL, ADD INDEX idx_job_subcategory (subcategory);
