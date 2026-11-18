ALTER TABLE EMPLOYER
    ADD COLUMN verification_status VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Pending',
    ADD COLUMN rejection_reason VARCHAR(1000) NULL,
    ADD COLUMN verified_at DATETIME NULL,
    ADD KEY idx_employer_verification_status (verification_status, employer_id),
    ADD CONSTRAINT chk_employer_verification_status CHECK (verification_status IN ('Pending', 'Verified', 'Rejected')),
    ADD CONSTRAINT chk_employer_rejection_reason CHECK (
        (verification_status = 'Rejected' AND rejection_reason IS NOT NULL AND CHAR_LENGTH(TRIM(rejection_reason)) > 0)
        OR (verification_status <> 'Rejected' AND rejection_reason IS NULL)
    ),
    ADD CONSTRAINT chk_employer_verified_at CHECK (
        (verification_status = 'Verified' AND verified_at IS NOT NULL)
        OR (verification_status <> 'Verified' AND verified_at IS NULL)
    );
