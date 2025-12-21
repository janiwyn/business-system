CREATE TABLE IF NOT EXISTS `store_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NULL, -- optional link to products.id (when known)
  `name` VARCHAR(255) NOT NULL,
  `barcode` VARCHAR(128) DEFAULT NULL,
  `selling-price` DECIMAL(12,2) DEFAULT 0,
  `buying-price` DECIMAL(12,2) DEFAULT 0,
  `stock` INT NOT NULL DEFAULT 0,
  `branch-id` INT NOT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_barcode_branch (`barcode`, `branch-id`),
  KEY idx_product_branch (`product_id`,`branch-id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;