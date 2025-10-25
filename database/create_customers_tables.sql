CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  contact VARCHAR(100) DEFAULT '',
  email VARCHAR(255) DEFAULT '',
  opening_date DATE DEFAULT CURRENT_DATE,
  amount_credited DECIMAL(12,2) DEFAULT 0,
  account_balance DECIMAL(12,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  date_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  products_bought TEXT,
  amount_paid DECIMAL(12,2) DEFAULT 0,
  amount_credited DECIMAL(12,2) DEFAULT 0,
  sold_by VARCHAR(255),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);



CREATE TABLE businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    admin_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address VARCHAR(255),
    date_registered DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'suspended') DEFAULT 'active'
);


ALTER TABLE users
ADD COLUMN business_id INT NULL,
ADD FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL;
ALTER TABLE users 
ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE businesses
ADD COLUMN subscription_start DATE NULL,
ADD COLUMN subscription_end DATE NULL,
ADD COLUMN subscription_status ENUM('active', 'pending', 'expired') DEFAULT 'pending';
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient ENUM('all','business') DEFAULT 'all',
    business_id INT NULL,
    title VARCHAR(255),
    message TEXT,
    date_sent DATETIME DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE customers ADD COLUMN opening_date DATE DEFAULT CURDATE();