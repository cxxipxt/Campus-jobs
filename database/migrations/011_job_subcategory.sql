ALTER TABLE JOB ADD COLUMN subcategory VARCHAR(100) NULL, ADD INDEX idx_job_subcategory (subcategory);
