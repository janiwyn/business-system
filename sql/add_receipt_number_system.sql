-- 1. Ensure receipt_no column exists in sales table
ALTER TABLE sales ADD COLUMN IF NOT EXISTS receipt_no VARCHAR(32) NULL;

-- 2. Create a separate table to track the last receipt number (auto-increment helper)
CREATE TABLE IF NOT EXISTS receipt_counter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_number INT NOT NULL DEFAULT 0,
    prefix VARCHAR(10) NOT NULL DEFAULT 'RP',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_prefix (prefix)
);

-- 3. Initialize the counter (starts at 0, first receipt will be RP-00001)
INSERT INTO receipt_counter (prefix, last_number) 
VALUES ('RP', 0) 
ON DUPLICATE KEY UPDATE last_number = last_number;

-- 4. Optional: Update existing NULL receipt_no values with sequential numbers
SET @counter = 0;
UPDATE sales 
SET receipt_no = CONCAT('RP-', LPAD((@counter := @counter + 1), 5, '0'))
WHERE receipt_no IS NULL OR receipt_no = ''
ORDER BY id ASC;

-- 5. Update the counter to match the highest assigned number
UPDATE receipt_counter 
SET last_number = (
    SELECT IFNULL(MAX(CAST(SUBSTRING(receipt_no, 4) AS UNSIGNED)), 0) 
    FROM sales 
    WHERE receipt_no LIKE 'RP-%'
)
WHERE prefix = 'RP';
