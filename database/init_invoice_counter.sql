-- Initialize invoice counter in receipt_counter table
INSERT INTO receipt_counter (prefix, last_number)
VALUES ('INV', 0)
ON DUPLICATE KEY UPDATE last_number = last_number;
