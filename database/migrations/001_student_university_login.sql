-- Upgrade ORIGINAL v1 -> v2. Run once; never run on a fresh v2 schema.
-- BACK UP first: this migration removes legacy STUDENT password/university fields.
-- Pause writes, review preflight.sql, resolve duplicate usernames first.
-- MySQL DDL commits individually: STOP on first error, do not use --force.
SET NAMES utf8mb4;
SET SESSION time_zone = '+00:00';

-- Stage username before removing any original columns.
ALTER TABLE STUDENT
    ADD COLUMN university_username VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL
    AFTER student_id;

-- Populate ONLY structurally valid university addresses. Unmapped rows stay NULL.
-- This is migration mapping, NOT evidence of a successful provider login.
UPDATE STUDENT
SET university_username = LOWER(SUBSTRING_INDEX(TRIM(email), '@', 1))
WHERE LOWER(SUBSTRING_INDEX(TRIM(email), '@', -1)) IN ('kmutt.ac.th', 'mail.kmutt.ac.th')
  AND CHAR_LENGTH(TRIM(email)) - CHAR_LENGTH(REPLACE(TRIM(email), '@', '')) = 1
  AND CHAR_LENGTH(SUBSTRING_INDEX(TRIM(email), '@', 1)) BETWEEN 1 AND 64
  AND SUBSTRING_INDEX(TRIM(email), '@', 1) NOT REGEXP '[^A-Za-z0-9._+-]';

-- Fails on duplicate names across the two domains. Resolve explicitly; do not
-- merge student IDs automatically (applications and saved jobs reference them).
ALTER TABLE STUDENT ADD CONSTRAINT uq_student_university_username UNIQUE (university_username);
-- Fails if any account was not mapped. Resolve those rows before continuing.
ALTER TABLE STUDENT
    MODIFY COLUMN university_username VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY COLUMN full_name VARCHAR(150) NOT NULL,
    ADD CONSTRAINT chk_student_username CHECK (
        CHAR_LENGTH(university_username) > 0
        AND university_username = LOWER(university_username)
        AND university_username = TRIM(university_username)
        AND LOCATE('@', university_username) = 0
    ),
    ADD CONSTRAINT chk_student_phone CHECK (phone IS NULL OR CHAR_LENGTH(TRIM(phone)) > 0),
    ADD CONSTRAINT chk_student_major CHECK (major IS NULL OR CHAR_LENGTH(TRIM(major)) > 0),
    ADD CONSTRAINT chk_student_resume CHECK (resume_url IS NULL OR REGEXP_LIKE(resume_url, '^https?://', 'c'));

ALTER TABLE EMPLOYER
    ADD CONSTRAINT chk_employer_phone CHECK (phone IS NULL OR CHAR_LENGTH(TRIM(phone)) > 0),
    ADD CONSTRAINT chk_employer_address CHECK (address IS NULL OR CHAR_LENGTH(TRIM(address)) > 0);

-- Original student IDs, emails, profiles and relationships stay unchanged.
ALTER TABLE STUDENT
    DROP CHECK chk_student_hash,
    DROP COLUMN password_hash,
    DROP COLUMN university;
