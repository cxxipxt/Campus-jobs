-- Migration 005: Create STUDENT_OAUTH table
-- Requires the STUDENT table to exist.
-- Run this migration once.

CREATE TABLE `STUDENT_OAUTH` (
  `oauth_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_subject` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`oauth_id`),

  UNIQUE KEY `uq_student_oauth_provider_subject`
    (`provider`, `provider_subject`),

  UNIQUE KEY `uq_student_oauth_student_provider`
    (`student_id`, `provider`),

  CONSTRAINT `fk_student_oauth_student`
    FOREIGN KEY (`student_id`)
    REFERENCES `STUDENT` (`student_id`)
    ON DELETE CASCADE,

  CONSTRAINT `chk_student_oauth_provider`
    CHECK (`provider` IN ('microsoft', 'google'))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;