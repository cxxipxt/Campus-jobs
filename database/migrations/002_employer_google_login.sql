ALTER TABLE EMPLOYER
    MODIFY company_name VARCHAR(150) NULL,
    MODIFY password_hash VARCHAR(255) NULL,
    ADD COLUMN google_subject VARBINARY(255) NULL,
    ADD CONSTRAINT uq_employer_google_subject UNIQUE (google_subject);
