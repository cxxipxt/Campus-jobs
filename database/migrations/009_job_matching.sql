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
