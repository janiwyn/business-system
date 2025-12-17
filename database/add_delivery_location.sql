ALTER TABLE `remote_orders` 
ADD COLUMN `delivery_location` TEXT NULL AFTER `payment_method`;
