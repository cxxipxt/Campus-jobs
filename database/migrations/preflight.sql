-- READ ONLY. Run on original v1 before 001. All queries should return no rows.
-- Resolve findings and back up before migrating; never auto-merge accounts.
SELECT student_id, email
FROM STUDENT
WHERE LOWER(SUBSTRING_INDEX(TRIM(email), '@', -1)) NOT IN ('kmutt.ac.th', 'mail.kmutt.ac.th')
   OR CHAR_LENGTH(TRIM(email)) - CHAR_LENGTH(REPLACE(TRIM(email), '@', '')) <> 1
   OR CHAR_LENGTH(SUBSTRING_INDEX(TRIM(email), '@', 1)) NOT BETWEEN 1 AND 64
   OR SUBSTRING_INDEX(TRIM(email), '@', 1) REGEXP '[^A-Za-z0-9._+-]';

SELECT LOWER(SUBSTRING_INDEX(TRIM(email), '@', 1)) AS university_username,
       COUNT(*) AS accounts, GROUP_CONCAT(student_id ORDER BY student_id) AS student_ids
FROM STUDENT
GROUP BY LOWER(SUBSTRING_INDEX(TRIM(email), '@', 1))
HAVING COUNT(*) > 1;

-- Additional v2 checks: normalize optional blank values to NULL only after review.
SELECT student_id FROM STUDENT
WHERE full_name IS NULL OR CHAR_LENGTH(TRIM(full_name)) = 0
   OR (phone IS NOT NULL AND CHAR_LENGTH(TRIM(phone)) = 0)
   OR (major IS NOT NULL AND CHAR_LENGTH(TRIM(major)) = 0)
   OR (resume_url IS NOT NULL AND NOT REGEXP_LIKE(resume_url, '^https?://', 'c'));

SELECT employer_id FROM EMPLOYER
WHERE (phone IS NOT NULL AND CHAR_LENGTH(TRIM(phone)) = 0)
   OR (address IS NOT NULL AND CHAR_LENGTH(TRIM(address)) = 0);
