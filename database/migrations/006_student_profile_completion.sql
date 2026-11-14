-- Migration 006: Persist whether a student has completed their profile setup.
-- Existing students are treated as complete; new SSO registrations set this to 0.
ALTER TABLE STUDENT
    ADD COLUMN profile_completed TINYINT(1) NOT NULL DEFAULT 1 AFTER resume_url,
    ADD CONSTRAINT chk_student_profile_completed CHECK (profile_completed IN (0, 1));
