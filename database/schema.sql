-- Campus-jobs baseline v2. MySQL >= 8.0.16, InnoDB.
-- Run once against a NEW, EMPTY development database selected by the caller.
-- Deliberately no DROP, CREATE DATABASE, USE, or IF NOT EXISTS.
-- MySQL DDL auto-commits: stop on the first error and inspect partial results.
SET NAMES utf8mb4;
SET SESSION time_zone = '+00:00';
DROP DATABASE IF EXISTS campus_jobs;
CREATE DATABASE campus_jobs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_jobs;
CREATE TABLE STUDENT (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    university_username VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    major VARCHAR(150) NULL,
    resume_url VARCHAR(2048) NULL,
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
    CONSTRAINT chk_student_resume CHECK (resume_url IS NULL OR resume_url REGEXP '^https?://')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE EMPLOYER (
    employer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    PRIMARY KEY (employer_id),
    CONSTRAINT uq_employer_email UNIQUE (email),
    CONSTRAINT chk_employer_name CHECK (CHAR_LENGTH(TRIM(company_name)) > 0),
    CONSTRAINT chk_employer_email CHECK (CHAR_LENGTH(TRIM(email)) > 0),
    CONSTRAINT chk_employer_hash CHECK (CHAR_LENGTH(TRIM(password_hash)) > 0),
    CONSTRAINT chk_employer_phone CHECK (phone IS NULL OR CHAR_LENGTH(TRIM(phone)) > 0),
    CONSTRAINT chk_employer_address CHECK (address IS NULL OR CHAR_LENGTH(TRIM(address)) > 0)
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

-- Tables: APPLICATION & SAVED_JOB
--  Ter

CREATE TABLE IF NOT EXISTS APPLICATION (
    application_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Pending',
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (application_id),
    

    CONSTRAINT fk_application_student FOREIGN KEY (student_id) REFERENCES STUDENT(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_application_job FOREIGN KEY (job_id) REFERENCES JOB(job_id) ON DELETE CASCADE,
    
 
    CONSTRAINT uq_student_job_application UNIQUE (student_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS SAVED_JOB (
    saved_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (saved_id),

    CONSTRAINT fk_saved_job_student FOREIGN KEY (student_id) REFERENCES STUDENT(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_job_job FOREIGN KEY (job_id) REFERENCES JOB(job_id) ON DELETE CASCADE,
    CONSTRAINT uq_student_job_saved UNIQUE (student_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;