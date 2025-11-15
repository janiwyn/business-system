CREATE TABLE petty_cash_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(15,2) NOT NULL,
    type ENUM('add','remove') NOT NULL,
    created_at DATETIME NOT NULL,
    approved_by VARCHAR(255) DEFAULT NULL
);

CREATE TABLE petty_cash_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    branch_id INT NOT NULL,
    purpose ENUM('company','personal') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance DECIMAL(15,2) NOT NULL,
    approved_by VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    action_type VARCHAR(32) DEFAULT NULL
); 