ALTER TABLE `remote_orders` 
ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME NULL AFTER `processed_at`;
