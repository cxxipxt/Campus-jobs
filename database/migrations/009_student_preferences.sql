ALTER TABLE STUDENT
    ADD COLUMN study_year TINYINT UNSIGNED NULL,
    ADD COLUMN activities TEXT NULL,
    ADD COLUMN experience TEXT NULL,
    ADD COLUMN portfolio_url VARCHAR(2048) NULL,
    ADD COLUMN languages TEXT NULL,
    ADD COLUMN certificates TEXT NULL,
    ADD COLUMN preferred_area VARCHAR(150) NULL,
    ADD COLUMN min_wage DECIMAL(10,2) UNSIGNED NULL;

CREATE TABLE STUDENT_AVAILABILITY (
    student_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY (student_id, weekday),
    FOREIGN KEY (student_id) REFERENCES STUDENT(student_id) ON DELETE CASCADE,
    CHECK (weekday BETWEEN 1 AND 7),
    CHECK (start_time < end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
