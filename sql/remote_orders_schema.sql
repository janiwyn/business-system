-- Remote Orders Table
CREATE TABLE IF NOT EXISTS `remote_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_reference` VARCHAR(50) NOT NULL,
  `branch_id` INT(11) NOT NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `payment_method` ENUM('cash', 'mobile_money', 'card') DEFAULT 'cash',
  `expected_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending', 'ready', 'finished', 'cancelled') DEFAULT 'pending',
  `qr_code` TEXT,
  `qr_code_expires_at` DATETIME,
  `processed_by` INT(11) DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_reference` (`order_reference`),
  KEY `branch_id` (`branch_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_remote_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remote Order Items Table
CREATE TABLE IF NOT EXISTS `remote_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_remote_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `remote_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_remote_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remote Order Audit Logs Table
CREATE TABLE IF NOT EXISTS `remote_order_audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `performed_by` VARCHAR(255),
  `user_id` INT(11) DEFAULT NULL,
  `old_status` VARCHAR(50),
  `new_status` VARCHAR(50),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_remote_audit_order` FOREIGN KEY (`order_id`) REFERENCES `remote_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
