-- Remote Orders Table
CREATE TABLE IF NOT EXISTS `remote_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_reference` VARCHAR(50) UNIQUE NOT NULL,
  `branch_id` INT(11) NOT NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `payment_method` ENUM('cash', 'mobile_money', 'card', 'online') DEFAULT 'cash',
  `expected_amount` DECIMAL(10, 2) NOT NULL,
  `status` ENUM('pending', 'confirmed', 'ready', 'finished', 'cancelled', 'expired') DEFAULT 'pending',
  `qr_code` TEXT,
  `qr_code_expires_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_reference` (`order_reference`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remote Order Items Table
CREATE TABLE IF NOT EXISTS `remote_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `remote_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Logs Table
CREATE TABLE IF NOT EXISTS `remote_order_audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `performed_by` VARCHAR(255),
  `user_id` INT(11) NULL,
  `old_status` VARCHAR(20),
  `new_status` VARCHAR(20),
  `notes` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Branches Table (if not exists)
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_name` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255),
  `phone` VARCHAR(20),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products Table (if not exists)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `image` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Branch Products (Product Availability per Branch)
CREATE TABLE IF NOT EXISTS `branch_products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `stock_quantity` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_branch_product` (`branch_id`, `product_id`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company Settings Table
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(255) NOT NULL,
  `logo` VARCHAR(255),
  `primary_color` VARCHAR(7) DEFAULT '#4F46E5',
  `secondary_color` VARCHAR(7) DEFAULT '#10B981',
  `qr_expiry_hours` INT(11) DEFAULT 24,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default company settings if not exists
INSERT IGNORE INTO `company_settings` (`id`, `company_name`, `qr_expiry_hours`) 
VALUES (1, 'My Business', 24);
