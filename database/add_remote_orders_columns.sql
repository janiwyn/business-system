-- Add missing columns to remote_orders table

ALTER TABLE `remote_orders` 
ADD COLUMN IF NOT EXISTS `processed_by` INT(11) NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `processed_at` DATETIME NULL AFTER `processed_by`;

-- Add foreign key constraint for processed_by (optional)
ALTER TABLE `remote_orders`
ADD CONSTRAINT `fk_remote_orders_processed_by` 
FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) 
ON DELETE SET NULL;
