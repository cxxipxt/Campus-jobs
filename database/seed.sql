-- Shared starter categories. Run after schema.sql, in the same database.
-- Safe to repeat; does not overwrite existing categories.
SET NAMES utf8mb4;
SET SESSION time_zone = '+00:00';
START TRANSACTION;
INSERT INTO JOB_CATEGORY (category_name) VALUES
    ('ร้านอาหารและคาเฟ่'),
    ('ขายสินค้าและบริการ'),
    ('ติวเตอร์และการสอน'),
    ('งานสำนักงาน'),
    ('งานอีเวนต์'),
    ('งานออนไลน์'),
    ('อื่น ๆ')
ON DUPLICATE KEY UPDATE category_name = JOB_CATEGORY.category_name;
COMMIT;
-- No user accounts: create test accounts through registration with real hashes.
