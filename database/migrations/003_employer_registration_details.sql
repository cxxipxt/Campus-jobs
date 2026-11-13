ALTER TABLE EMPLOYER
    ADD COLUMN username VARCHAR(80) NULL,
    ADD COLUMN business_type VARCHAR(100) NULL,
    ADD COLUMN contact_email VARCHAR(255) NULL,
    ADD CONSTRAINT uq_employer_username UNIQUE (username);
