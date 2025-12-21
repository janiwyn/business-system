ALTER TABLE `payment_proofs` 
ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NULL AFTER `order_id`,
ADD KEY `idx_branch_id` (`branch_id`);

-- Update existing records to link branch from remote_orders
UPDATE `payment_proofs` pp
JOIN `remote_orders` ro ON pp.order_id = ro.id
SET pp.branch_id = ro.branch_id
WHERE pp.branch_id IS NULL;
