ALTER TABLE EMPLOYER
    ADD COLUMN verification_status VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Draft' AFTER address,
    ADD COLUMN rejection_reason VARCHAR(1000) NULL AFTER verification_status,
    ADD COLUMN verified_at DATETIME NULL AFTER rejection_reason,
    ADD KEY idx_employer_verification_status (verification_status, employer_id),
    ADD CONSTRAINT chk_employer_verification_status
        CHECK (verification_status IN ('Draft', 'Pending', 'Verified', 'Rejected')),
    ADD CONSTRAINT chk_employer_pending_profile_complete CHECK (
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
    ADD CONSTRAINT chk_employer_rejection_reason CHECK (
        (verification_status = 'Rejected' AND rejection_reason IS NOT NULL AND CHAR_LENGTH(TRIM(rejection_reason)) > 0)
        OR (verification_status <> 'Rejected' AND rejection_reason IS NULL)
    ),
    ADD CONSTRAINT chk_employer_verified_at CHECK (
        (verification_status = 'Verified' AND verified_at IS NOT NULL)
        OR (verification_status <> 'Verified' AND verified_at IS NULL)
    );

UPDATE EMPLOYER
SET verification_status = 'Pending'
WHERE company_name IS NOT NULL AND CHAR_LENGTH(TRIM(company_name)) > 0
  AND business_type IS NOT NULL AND CHAR_LENGTH(TRIM(business_type)) > 0
  AND (business_type <> 'อื่น ๆ' OR
       (business_type_other IS NOT NULL AND CHAR_LENGTH(TRIM(business_type_other)) > 0))
  AND phone IS NOT NULL AND CHAR_LENGTH(TRIM(phone)) > 0
  AND contact_email IS NOT NULL AND CHAR_LENGTH(TRIM(contact_email)) > 0
  AND address IS NOT NULL AND CHAR_LENGTH(TRIM(address)) > 0;
