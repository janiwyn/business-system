CREATE TABLE supplier_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    date_time DATETIME NOT NULL,
    branch VARCHAR(255),
    products_supplied VARCHAR(255),
    quantity INT,
    unit_price DECIMAL(12,2),
    amount DECIMAL(12,2),
    payment_method VARCHAR(100),
    amount_paid DECIMAL(12,2),
    balance DECIMAL(12,2),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);
