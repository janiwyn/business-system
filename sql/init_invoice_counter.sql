-- Insert initial counter for INV prefix if it doesn't exist
INSERT INTO receipt_counter (prefix, last_number, updated_at)
SELECT 'INV', 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM receipt_counter WHERE prefix = 'INV');
