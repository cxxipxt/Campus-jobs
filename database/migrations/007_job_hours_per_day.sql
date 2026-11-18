-- Apply once to existing databases before deploying the hours filter.
ALTER TABLE JOB ADD COLUMN hours_per_day DECIMAL(4,2) NULL AFTER work_schedule,
 ADD CONSTRAINT chk_job_hours CHECK (hours_per_day IS NULL OR (hours_per_day > 0 AND hours_per_day <= 24));
